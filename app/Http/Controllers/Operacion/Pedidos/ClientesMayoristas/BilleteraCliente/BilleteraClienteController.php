<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente\BilleteraOperador;
use App\Models\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente\BilleteraMovimiento;
use App\Models\Operacion\Pedidos\ClientesMayoristas\OperadorPedidoCliente;
use App\Models\Gestion\Todos\Operador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class BilleteraClienteController extends Controller
{
    // ============================================================
    // LISTADO DE BILLETERAS (Admin / Encargado)
    // ============================================================
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $query = BilleteraOperador::where('IdCliente', $clienteId)
            ->with([
                'operador.identificador',
                'operadorPedidoCliente',
                'sucursal',
            ]);

        if ($request->filled('sucursal_id')) {
            $query->where('IdSucursal', $request->sucursal_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('operador.identificador', function ($q) use ($search) {
                $q->where('Nombre', 'like', "%{$search}%")
                  ->orWhere('CI_NIT', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            if ($request->estado === 'activas') $query->where('ActivoInactivo', 0);
            if ($request->estado === 'inactivas') $query->where('ActivoInactivo', 1);
        }

        $billeteras = $query->orderBy('FechaCreacion', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Operadores PedidoClientes SIN billetera (del mismo cliente + sucursal)
        $idsConBilletera = BilleteraOperador::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->pluck('IdOperadorPedidoCliente')
            ->toArray();

        $operadoresSinBilletera = OperadorPedidoCliente::where('ActivoInactivo', 1)
            ->whereNotIn('IdOperadorPedidoCliente', $idsConBilletera)
            ->with(['operador.identificador'])
            ->get()
            ->map(function ($opc) {
                return [
                    'IdOperadorPedidoCliente' => $opc->IdOperadorPedidoCliente,
                    'IdOperador' => $opc->IdOperador,
                    'Nombre' => $opc->operador->identificador->Nombre ?? 'Sin nombre',
                    'CI_NIT' => $opc->operador->identificador->CI_NIT ?? '-',
                ];
            });

        return Inertia::render('Operacion/ClientesMayoristas/BilleteraCliente/Index', [
            'billeteras' => $billeteras,
            'operadoresSinBilletera' => $operadoresSinBilletera,
            'filtros' => [
                'search' => $request->search,
                'estado' => $request->estado,
                'sucursal_id' => $request->sucursal_id,
            ],
        ]);
    }

    // ============================================================
    // CREAR BILLETERA
    // ============================================================
    public function store(Request $request)
    {
        $request->validate([
            'IdOperadorPedidoCliente' => 'required|exists:operacion_pedidos_operadores_clientes,IdOperadorPedidoCliente',
            'SaldoMinimoAlerta' => 'nullable|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // 🔥 Validar duplicado por CONTEXTO (operador + cliente + sucursal)
        $existe = BilleteraOperador::where('IdOperadorPedidoCliente', $request->IdOperadorPedidoCliente)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Este operador ya tiene una billetera creada en esta sucursal.'
            ], 422);
        }

        $operadorPC = OperadorPedidoCliente::findOrFail($request->IdOperadorPedidoCliente);

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            // ✅ NORMALIZAR: null / vacío / negativo → 0
            $saldoMinimo = (float) ($request->SaldoMinimoAlerta ?? 0);
            if ($saldoMinimo < 0) $saldoMinimo = 0;

            $billetera = BilleteraOperador::create([
                'IdOperadorPedidoCliente' => $operadorPC->IdOperadorPedidoCliente,
                'IdOperador' => $operadorPC->IdOperador,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'SaldoActual' => 0,
                'SaldoInicial' => 0,
                'TotalCargado' => 0,
                'TotalConsumido' => 0,
                'ActivoInactivo' => 0,
                'MotivoInactivacion' => null,
                'PermiteAutoRecarga' => 0,
                'SaldoMinimoAlerta' => $saldoMinimo,
                'IdOperadorInserta' => $operadorId,
                'FechaCreacion' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            // 🔥 Invalidar cache del navbar con contexto
            Cache::forget("billetera_operador_{$billetera->IdOperador}_{$billetera->IdCliente}_{$billetera->IdSucursal}");

            return response()->json([
                'success' => true,
                'message' => 'Billetera creada correctamente.',
                'billetera' => $billetera,
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al crear billetera: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear billetera: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // VER BILLETERA (con historial)
    // ============================================================
    public function show($id, Request $request)
    {
        $clienteId = session('cliente_id');

        $billetera = BilleteraOperador::where('IdCliente', $clienteId)
            ->with(['operador.identificador', 'operadorPedidoCliente', 'sucursal'])
            ->findOrFail($id);

        $movimientosQuery = BilleteraMovimiento::where('IdBilletera', $billetera->IdBilletera)
            ->with(['operadorInserta.identificador', 'pedido']);

        if ($request->filled('tipo')) {
            $movimientosQuery->where('Tipo', $request->tipo);
        }

        if ($request->filled('desde')) {
            $movimientosQuery->whereDate('FechaMovimiento', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $movimientosQuery->whereDate('FechaMovimiento', '<=', $request->hasta);
        }

        $movimientos = $movimientosQuery
            ->orderBy('FechaMovimiento', 'desc')
            ->orderBy('IdMovimiento', 'desc')
            ->paginate(50);

        return Inertia::render('Operacion/ClientesMayoristas/BilleteraCliente/Show', [
            'billetera' => $billetera,
            'movimientos' => $movimientos,
            'filtros' => [
                'tipo' => $request->tipo,
                'desde' => $request->desde,
                'hasta' => $request->hasta,
            ],
        ]);
    }

    // ============================================================
    // CARGAR SALDO
    // ============================================================
    public function cargarSaldo(Request $request, $id)
    {
        $request->validate([
            'Monto' => 'required|numeric|min:0.01',
            'OrigenPago' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'ReferenciaPago' => 'nullable|string|max:100',
            'Observaciones' => 'nullable|string|max:500',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $billetera = BilleteraOperador::where('IdCliente', $clienteId)->findOrFail($id);

        try {
            $movimiento = $billetera->cargarSaldo(
                (float) $request->Monto,
                'Recarga de saldo - ' . $request->OrigenPago,
                $request->OrigenPago,
                $request->ReferenciaPago,
                $request->Observaciones,
                $operadorId
            );

            // 🔥 Invalidar cache con contexto
            Cache::forget("billetera_operador_{$billetera->IdOperador}_{$billetera->IdCliente}_{$billetera->IdSucursal}");

            return response()->json([
                'success' => true,
                'message' => 'Saldo cargado correctamente.',
                'movimiento' => $movimiento->fresh(),
                'saldo_actual' => (float) $billetera->fresh()->SaldoActual,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cargar saldo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ============================================================
    // AJUSTAR SALDO
    // ============================================================
    public function ajustarSaldo(Request $request, $id)
    {
        $request->validate([
            'Monto' => 'required|numeric|not_in:0',
            'Motivo' => 'required|string|min:5|max:500',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $billetera = BilleteraOperador::where('IdCliente', $clienteId)->findOrFail($id);

        try {
            $movimiento = $billetera->ajustar(
                (float) $request->Monto,
                $request->Motivo,
                $operadorId
            );

            // 🔥 Invalidar cache con contexto
            Cache::forget("billetera_operador_{$billetera->IdOperador}_{$billetera->IdCliente}_{$billetera->IdSucursal}");

            return response()->json([
                'success' => true,
                'message' => 'Ajuste registrado correctamente.',
                'movimiento' => $movimiento->fresh(),
                'saldo_actual' => (float) $billetera->fresh()->SaldoActual,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al ajustar saldo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // ============================================================
    // ACTIVAR / INACTIVAR
    // ============================================================
    public function activar($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $billetera = BilleteraOperador::where('IdCliente', $clienteId)->findOrFail($id);

        $billetera->activar($operadorId);

        // 🔥 Invalidar cache con contexto
        Cache::forget("billetera_operador_{$billetera->IdOperador}_{$billetera->IdCliente}_{$billetera->IdSucursal}");

        return response()->json([
            'success' => true,
            'message' => 'Billetera activada correctamente.',
        ]);
    }

    public function inactivar(Request $request, $id)
    {
        $request->validate([
            'MotivoInactivacion' => 'nullable|string|max:255',
            'ConfirmarSaldoCongelado' => 'nullable|boolean',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $billetera = BilleteraOperador::where('IdCliente', $clienteId)->findOrFail($id);

        if ($billetera->tieneSaldo() && !$request->boolean('ConfirmarSaldoCongelado')) {
            return response()->json([
                'success' => false,
                'requiere_confirmacion' => true,
                'saldo_actual' => (float) $billetera->SaldoActual,
                'message' => sprintf(
                    'Esta billetera tiene Bs. %s. Si la inactivas, el saldo quedará congelado hasta reactivarla. ¿Continuar?',
                    number_format((float) $billetera->SaldoActual, 2)
                ),
            ], 422);
        }

        $billetera->inactivar($operadorId, $request->MotivoInactivacion);

        // 🔥 Invalidar cache con contexto
        Cache::forget("billetera_operador_{$billetera->IdOperador}_{$billetera->IdCliente}_{$billetera->IdSucursal}");

        return response()->json([
            'success' => true,
            'message' => 'Billetera inactivada correctamente.',
        ]);
    }

    // ============================================================
    // MI BILLETERA (vista del operador logueado)
    // ============================================================
    public function miBilletera(Request $request)
    {
        $operadorId = session('operador_id');
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        // 🔥 Filtrar por CONTEXTO (operador + cliente + sucursal)
        $billetera = BilleteraOperador::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->with(['operador.identificador', 'sucursal'])
            ->first();

        if (!$billetera) {
            return Inertia::render('Operacion/ClientesMayoristas/BilleteraCliente/MiBilletera', [
                'billetera' => null,
                'movimientos' => null,
            ]);
        }

        $movimientos = BilleteraMovimiento::where('IdBilletera', $billetera->IdBilletera)
            ->orderBy('FechaMovimiento', 'desc')
            ->orderBy('IdMovimiento', 'desc')
            ->paginate(50);

        return Inertia::render('Operacion/ClientesMayoristas/BilleteraCliente/MiBilletera', [
            'billetera' => $billetera,
            'movimientos' => $movimientos,
        ]);
    }

    // ============================================================
    // API: Obtener saldo actual del operador (para el header)
    // ============================================================
    public function apiMiSaldo()
    {
        $operadorId = session('operador_id');
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        if (!$operadorId || !$clienteId || !$sucursalId) {
            return response()->json(['tiene_billetera' => false]);
        }

        // 🔥 Cache key con contexto
        $cacheKey = "billetera_operador_{$operadorId}_{$clienteId}_{$sucursalId}";

        $data = Cache::remember(
            $cacheKey,
            1, // 1 segundo (para actualizaciones rápidas)
            function () use ($operadorId, $clienteId, $sucursalId) {
                // 🔥 Filtrar por CONTEXTO
                $billetera = BilleteraOperador::where('IdOperador', $operadorId)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->select('IdBilletera', 'SaldoActual', 'ActivoInactivo', 'SaldoMinimoAlerta')
                    ->first();

                if (!$billetera) {
                    return ['tiene_billetera' => false];
                }

                $saldo = (float) $billetera->SaldoActual;

                $saldoBajo = (float) $billetera->SaldoMinimoAlerta > 0
                    && $saldo <= (float) $billetera->SaldoMinimoAlerta;

                return [
                    'tiene_billetera' => true,
                    'activa' => (int) $billetera->ActivoInactivo === 0,
                    'saldo' => $saldo,
                    'saldo_formateado' => number_format($saldo, 2, ',', '.'),
                    'saldo_bajo' => $saldoBajo,
                ];
            }
        );

        return response()->json($data);
    }

    // ============================================================
    // OBTENER OPERADORES DISPONIBLES (para crear billetera)
    // ============================================================
    public function operadoresDisponibles(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        // 🔥 Filtrar por CONTEXTO
        $idsConBilletera = BilleteraOperador::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->pluck('IdOperadorPedidoCliente')
            ->toArray();

        $operadores = OperadorPedidoCliente::where('ActivoInactivo', 1)
            ->whereNotIn('IdOperadorPedidoCliente', $idsConBilletera)
            ->with(['operador.identificador'])
            ->get()
            ->map(function ($opc) {
                return [
                    'IdOperadorPedidoCliente' => $opc->IdOperadorPedidoCliente,
                    'IdOperador' => $opc->IdOperador,
                    'Nombre' => $opc->operador->identificador->Nombre ?? 'Sin nombre',
                    'CI_NIT' => $opc->operador->identificador->CI_NIT ?? '-',
                ];
            });

        return response()->json($operadores);
    }

    // ============================================================
    // PDF: MOVIMIENTOS POR PERSONA
    // ============================================================
    public function exportarPdfMovimientos(Request $request, $id)
    {
        $clienteId = session('cliente_id');

        $billetera = BilleteraOperador::where('IdCliente', $clienteId)
            ->with(['operador.identificador', 'sucursal'])
            ->findOrFail($id);

        $query = BilleteraMovimiento::where('IdBilletera', $billetera->IdBilletera)
            ->with(['operadorInserta.identificador', 'pedido']);

        // Aplicar los mismos filtros que en la vista
        if ($request->filled('tipo')) {
            $query->where('Tipo', $request->tipo);
        }
        if ($request->filled('desde')) {
            $query->whereDate('FechaMovimiento', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('FechaMovimiento', '<=', $request->hasta);
        }

        $movimientos = $query->orderBy('FechaMovimiento', 'asc')->get();

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion']);

        $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        $y = 10;

        // --- HEADER ---
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 6, 'ESTADO DE CUENTA - BILLETERA', 0, 1, 'C');
        $y += 7;

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 5, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'), 0, 1, 'C');
        $y += 6;

        if (!empty($empresa->NIT)) {
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 4, 'NIT: ' . $empresa->NIT, 0, 1, 'C');
            $y += 4;
        }

        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(10, $y, 206, $y);
        $y += 6;

        // --- INFO BILLETERA ---
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(10, $y);
        $pdf->Cell(30, 5, 'Operador:', 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(70, 5, $billetera->operador->identificador->Nombre ?? 'Sin nombre', 0, 0);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(30, 5, 'Saldo Actual:', 0, 0);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(50, 5, 'Bs. ' . number_format($billetera->SaldoActual, 2, ',', '.'), 0, 1);
        $y += 5;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(10, $y);
        $pdf->Cell(30, 5, 'CI/NIT:', 0, 0);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(70, 5, $billetera->operador->identificador->CI_NIT ?? '-', 0, 0);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(30, 5, 'Estado:', 0, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor($billetera->ActivoInactivo == 0 ? 0 : 200, $billetera->ActivoInactivo == 0 ? 128 : 0, 0);
        $pdf->Cell(50, 5, $billetera->ActivoInactivo == 0 ? 'ACTIVA' : 'INACTIVA', 0, 1);
        $y += 5;

        // Fechas de filtro
        $textoFiltros = "Movimientos";
        if ($request->filled('desde') || $request->filled('hasta')) {
            $textoFiltros .= " (";
            if ($request->filled('desde')) $textoFiltros .= "Desde: " . Carbon::parse($request->desde)->format('d/m/Y') . " ";
            if ($request->filled('hasta')) $textoFiltros .= "Hasta: " . Carbon::parse($request->hasta)->format('d/m/Y');
            $textoFiltros .= ")";
        }

        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 5, $textoFiltros, 0, 1);
        $y += 8;

        // --- TABLA CABECERA ---
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(180, 180, 180);

        $pdf->SetXY(10, $y);
        $pdf->Cell(20, 5, 'FECHA', 'TB', 0, 'C', 1);
        $pdf->Cell(20, 5, 'TIPO', 'TB', 0, 'C', 1);
        $pdf->Cell(80, 5, 'GLOSA / DETALLE', 'TB', 0, 'L', 1);
        $pdf->Cell(26, 5, 'INGRESO', 'TB', 0, 'C', 1);
        $pdf->Cell(26, 5, 'EGRESO', 'TB', 0, 'C', 1);
        $pdf->Cell(24, 5, 'SALDO', 'TB', 1, 'C', 1);
        $y += 5;

        // --- FILAS ---
        $pdf->SetFont('helvetica', '', 7);

        foreach ($movimientos as $mov) {
            $monto = (float) $mov->Monto;
            $esPositivo = $monto >= 0;
            $ingreso = $esPositivo ? number_format($monto, 2) : '';
            $egreso = !$esPositivo ? number_format(abs($monto), 2) : '';

            $fecha = Carbon::parse($mov->FechaMovimiento)->format('d/m/Y');
            $tipo = $mov->Tipo ?? 'MOV';
            $glosa = $mov->Glosa ?? '-';
            $saldo = number_format($mov->SaldoDespues, 2);

            $alturaFecha = $pdf->getStringHeight(20, $fecha);
            $alturaTipo = $pdf->getStringHeight(20, $tipo);
            $alturaGlosa = $pdf->getStringHeight(80, $glosa);
            $alturaMax = max($alturaFecha, $alturaTipo, $alturaGlosa, 4.5);

            if ((270 - $y) < $alturaMax + 5) {
                $pdf->AddPage();
                $y = 15;
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetXY(10, $y);
                $pdf->Cell(20, 5, 'FECHA', 'TB', 0, 'C', 1);
                $pdf->Cell(20, 5, 'TIPO', 'TB', 0, 'C', 1);
                $pdf->Cell(80, 5, 'GLOSA / DETALLE', 'TB', 0, 'L', 1);
                $pdf->Cell(26, 5, 'INGRESO', 'TB', 0, 'C', 1);
                $pdf->Cell(26, 5, 'EGRESO', 'TB', 0, 'C', 1);
                $pdf->Cell(24, 5, 'SALDO', 'TB', 1, 'C', 1);
                $y += 5;
                $pdf->SetFont('helvetica', '', 7);
            }

            $x_inicial = 10;

            // Columna Fecha
            $pdf->SetXY($x_inicial, $y);
            $pdf->MultiCell(20, $alturaMax, $fecha, 'LR', 'C', false, 0, '', '', true, 0, false, true, $alturaMax, 'M');

            // Columna Tipo
            $x_inicial += 20;
            $pdf->SetXY($x_inicial, $y);
            $pdf->MultiCell(20, $alturaMax, $tipo, 'LR', 'C', false, 0, '', '', true, 0, false, true, $alturaMax, 'M');

            // Columna Glosa
            $x_inicial += 20;
            $pdf->SetXY($x_inicial, $y);
            $pdf->MultiCell(80, $alturaMax, ' ' . $glosa, 'LR', 'L', false, 0, '', '', true, 0, false, true, $alturaMax, 'M');

            // Columna Ingreso
            $x_inicial += 80;
            $pdf->SetTextColor(0, 128, 0);
            $pdf->SetXY($x_inicial, $y);
            $pdf->MultiCell(26, $alturaMax, $ingreso, 'LR', 'R', false, 0, '', '', true, 0, false, true, $alturaMax, 'M');

            // Columna Egreso
            $x_inicial += 26;
            $pdf->SetTextColor(200, 0, 0);
            $pdf->SetXY($x_inicial, $y);
            $pdf->MultiCell(26, $alturaMax, $egreso, 'LR', 'R', false, 0, '', '', true, 0, false, true, $alturaMax, 'M');

            // Columna Saldo
            $x_inicial += 26;
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($x_inicial, $y);
            $pdf->MultiCell(24, $alturaMax, $saldo, 'LR', 'R', false, 1, '', '', true, 0, false, true, $alturaMax, 'M');

            $y += $alturaMax;
        }

        // --- TOTALES ---
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(235, 245, 255);
        $pdf->SetXY(10, $y);

        $pdf->Cell(120, 6, 'TOTALES DEL PERIODO', 'LRB', 0, 'R', 1);

        $totalIngresos = $movimientos->where('Monto', '>', 0)->sum('Monto');
        $totalEgresos = abs($movimientos->where('Monto', '<', 0)->sum('Monto'));

        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(26, 6, number_format($totalIngresos, 2), 'LRB', 0, 'R', 1);
        $pdf->SetTextColor(200, 0, 0);
        $pdf->Cell(26, 6, number_format($totalEgresos, 2), 'LRB', 0, 'R', 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(24, 6, number_format($billetera->SaldoActual, 2), 'LRB', 1, 'R', 1);
        $y += 8;

        // --- PIE ---
        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 4, 'Documento generado el ' . Carbon::now('America/La_Paz')->format('d/m/Y H:i') . ' por ' . session('operador_nombre', 'Sistema'), 0, 1, 'C');

        if (ob_get_length()) ob_end_clean();

        $nombreArchivo = 'Estado_Cuenta_' . ($billetera->operador->identificador->CI_NIT ?? 'Billetera') . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit;
    }
    // ============================================================
    // 🔥 NUEVO: PDF DEL REPORTE DE UN PEDIDO INDIVIDUAL
    // Mismo formato que InformePedidosClientesMayoristas::exportarPdfDetalle
    // pero con SOLO el pedido seleccionado
    // ============================================================
    public function exportarPdfPedido($idPedido)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // Buscar el pedido con contexto
        $pedido = \App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdPedidoCliente', $idPedido)
            ->with([
                'sucursal',
                'operador.identificador',
                'detalles.producto',
                'detalles.contenedor.tipoContenedor',
                'detalles.subClienteOperador.identificador',
            ])
            ->first();

        if (!$pedido) {
            return redirect()->back()->with('error', 'Pedido no encontrado.');
        }

        // Datos de cabecera
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $operadorLogueado = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);

        $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);

        // ============================================================
        // GENERAR PDF (mismo formato que exportarPdfDetalle)
        // ============================================================
        $pdf->AddPage();

        // HEADER EMPRESA
        $y = 8;

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 5, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'), 0, 1, 'C');
        $y += 5;

        $pdf->SetFont('helvetica', '', 7.5);
        if (!empty($pedido->sucursal->Nombre)) {
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 3.5, $pedido->sucursal->Nombre, 0, 1, 'C');
            $y += 3.5;
        }
        if (!empty($pedido->sucursal->Direccion)) {
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 3.5, $pedido->sucursal->Direccion, 0, 1, 'C');
            $y += 3.5;
        }
        if (!empty($empresa->NIT)) {
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 3.5, 'NIT: ' . $empresa->NIT, 0, 1, 'C');
            $y += 3.5;
        }

        $y += 2;
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(10, $y, 206, $y);
        $y += 5;

        // TÍTULO
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 7, 'PEDIDO DE PRODUCTOS', 0, 1, 'C');
        $y += 7;

        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 5, 'N° ' . ($pedido->NumeroPedido ?? '000000'), 0, 1, 'C');
        $y += 8;

        // INFO PEDIDO EN 2 COLUMNAS
        $pdf->SetFont('helvetica', '', 8);

        $colIzq_label = 12;
        $colIzq_valor = 45;
        $colDer_label = 108;
        $colDer_valor = 138;
        $yInfo = $y;
        $altoFila = 5;

        // COLUMNA IZQUIERDA
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($colIzq_label, $yInfo);
        $pdf->Cell(33, $altoFila, 'Fecha Pedido:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($colIzq_valor, $yInfo);
        $pdf->Cell(60, $altoFila, $pedido->FechaPedido ? Carbon::parse($pedido->FechaPedido)->format('d/m/Y H:i') : '-', 0, 0, 'L');
        $yInfo += $altoFila;

        if ($pedido->FechaEntrega) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colIzq_label, $yInfo);
            $pdf->Cell(33, $altoFila, 'Fecha Entrega:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colIzq_valor, $yInfo);
            $pdf->Cell(60, $altoFila, Carbon::parse($pedido->FechaEntrega)->format('d/m/Y'), 0, 0, 'L');
            $yInfo += $altoFila;
        }

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($colIzq_label, $yInfo);
        $pdf->Cell(33, $altoFila, 'Operador:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($colIzq_valor, $yInfo);
        $pdf->Cell(60, $altoFila, $pedido->operador->identificador->Nombre ?? 'Sin operador', 0, 0, 'L');
        $yInfo += $altoFila;

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($colIzq_label, $yInfo);
        $pdf->Cell(33, $altoFila, 'Sucursal:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($colIzq_valor, $yInfo);
        $pdf->Cell(60, $altoFila, $pedido->sucursal->Nombre ?? 'Sin sucursal', 0, 0, 'L');
        $yInfo += $altoFila;

        // COLUMNA DERECHA
        $yInfoDer = $y;

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY($colDer_label, $yInfoDer);
        $pdf->Cell(30, $altoFila, 'Estado:', 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($colDer_valor, $yInfoDer);
        $pdf->Cell(58, $altoFila, $pedido->EstadoPedido ?? 'Pendiente', 0, 0, 'L');
        $yInfoDer += $altoFila;

        $configOperador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_operadores_clientes')
            ->where('IdOperador', $pedido->IdOperador)
            ->first();

        $destino = null;
        $tipoUbicacion = null;

        if ($configOperador) {
            if (isset($configOperador->Ciudad) && $configOperador->Ciudad == 1) {
                $tipoUbicacion = 'Ciudad';
            } elseif (isset($configOperador->Provincia) && $configOperador->Provincia == 1) {
                $tipoUbicacion = 'Provincia';
            }
            $destino = $configOperador->Destino ?? null;
        }

        if ($tipoUbicacion) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colDer_label, $yInfoDer);
            $pdf->Cell(30, $altoFila, 'Tipo:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colDer_valor, $yInfoDer);
            $pdf->Cell(58, $altoFila, $tipoUbicacion, 0, 0, 'L');
            $yInfoDer += $altoFila;
        }

        if ($destino) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colDer_label, $yInfoDer);
            $pdf->Cell(30, $altoFila, 'Destino:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colDer_valor, $yInfoDer);
            $pdf->Cell(58, $altoFila, $destino, 0, 0, 'L');
            $yInfoDer += $altoFila;
        }

        $y = max($yInfo, $yInfoDer) + 3;

        // OBSERVACIONES
        if (!empty($pedido->Observaciones)) {
            $pdf->SetDrawColor(251, 191, 36);
            $pdf->SetFillColor(255, 251, 235);

            $pdf->SetFont('helvetica', '', 7.5);
            $alturaTexto = $pdf->getStringHeight(180, $pedido->Observaciones);
            $alturaCaja = max(12, 6 + $alturaTexto + 3);

            $pdf->RoundedRect(10, $y, 196, $alturaCaja, 1.5, '1111', 'DF');

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(146, 64, 14);
            $pdf->SetXY(12, $y + 2);
            $pdf->Cell(100, 4, 'OBSERVACIONES:', 0, 0, 'L');

            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(80, 40, 10);
            $pdf->SetXY(12, $y + 6.5);
            $pdf->MultiCell(192, 3, $pedido->Observaciones, 0, 'L');

            $y += $alturaCaja + 4;

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);
        }

        // ============================================================
        // SEPARAR REJILLAS DE CONTENEDORES NORMALES
        // ============================================================
        $detallesNormales = collect();
        $detallesRejillas = collect();

        foreach ($pedido->detalles as $detalle) {
            $contenedor = $detalle->contenedor;
            if ($contenedor && $contenedor->EsProductoUnico) {
                $detallesRejillas->push($detalle);
            } else {
                $detallesNormales->push($detalle);
            }
        }

        // NORMALES
        $detallesAgrupadosNormales = $detallesNormales
            ->groupBy('OrdenContenedor')
            ->map(function ($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;

                $totalUnidadesContenedor = $items->sum('Cantidad');
                $subtotal = $items->sum(function ($item) {
                    return $item->Cantidad * $item->Precio;
                });

                $subCliente = $primerItem->subClienteOperador;
                $subClienteNombre = $subCliente
                    ? ($subCliente->Alias ?: ($subCliente->identificador->Nombre ?? null))
                    : null;

                return [
                    'OrdenContenedor' => intval($orden),
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'EsProductoUnico' => false,
                    'SubClienteNombre' => $subClienteNombre,
                    'CantidadRejillas' => 0,
                    'productos' => $items->map(function ($item) {
                        return [
                            'IdProducto' => $item->IdProducto,
                            'Codigo' => $item->producto ? $item->producto->Codigo : '-',
                            'Descripcion' => $item->producto ? $item->producto->Descripcion : '-',
                            'Cantidad' => $item->Cantidad,
                            'CantidadPorRejilla' => (float) $item->Cantidad,
                            'CantidadRejillas' => 0,
                            'Precio' => $item->Precio,
                            'Subtotal' => $item->Cantidad * $item->Precio,
                        ];
                    })->values()->toArray(),
                    'total_unidades' => $totalUnidadesContenedor,
                    'subtotal' => $subtotal,
                ];
            })
            ->values();

        // REJILLAS
        $detallesAgrupadosRejillas = $detallesRejillas
            ->groupBy(function ($item) {
                $subClienteId = $item->IdSubClienteOperador ?? 'sin_sub';
                return $item->IdContenedor . '_' . $item->IdProducto . '_' . $subClienteId;
            })
            ->map(function ($items) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;

                $producto = $primerItem->producto;
                $cantidadPorRejilla = (float) $primerItem->Cantidad;
                $cantidadRejillas = $items->count();
                $totalUnidades = $items->sum('Cantidad');
                $precioUnitario = (float) $primerItem->Precio;
                $subtotal = $items->sum(function ($item) {
                    return $item->Cantidad * $item->Precio;
                });

                $subCliente = $primerItem->subClienteOperador;
                $subClienteNombre = $subCliente
                    ? ($subCliente->Alias ?: ($subCliente->identificador->Nombre ?? null))
                    : null;

                $ordenMin = $items->min('OrdenContenedor');

                return [
                    'OrdenContenedor' => intval($ordenMin),
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'CapacidadTotal' => 0,
                    'EsProductoUnico' => true,
                    'SubClienteNombre' => $subClienteNombre,
                    'CantidadRejillas' => $cantidadRejillas,
                    'productos' => [[
                        'IdProducto' => $primerItem->IdProducto,
                        'Codigo' => $producto ? $producto->Codigo : '-',
                        'Descripcion' => $producto ? $producto->Descripcion : '-',
                        'Cantidad' => $totalUnidades,
                        'CantidadPorRejilla' => $cantidadPorRejilla,
                        'CantidadRejillas' => $cantidadRejillas,
                        'Precio' => $precioUnitario,
                        'Subtotal' => $subtotal,
                    ]],
                    'total_unidades' => $totalUnidades,
                    'subtotal' => $subtotal,
                ];
            })
            ->values();

        // Unir y ordenar
        $detallesAgrupados = $detallesAgrupadosNormales
            ->concat($detallesAgrupadosRejillas)
            ->sortBy('OrdenContenedor')
            ->values();

        // CABECERA TABLA
        if ((270 - $y) < 20) {
            $pdf->AddPage();
            $y = 15;
        }

        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(180, 180, 180);

        $pdf->SetXY(10, $y);
        $pdf->Cell(8, 5, '#', 'TB', 0, 'C', 1);
        $pdf->Cell(72, 5, 'PRODUCTO', 'TB', 0, 'L', 1);
        $pdf->Cell(24, 5, 'CANTIDAD', 'TB', 0, 'C', 1);
        $pdf->Cell(30, 5, 'PRECIO UNIT.', 'TB', 0, 'C', 1);
        $pdf->Cell(62, 5, 'SUBTOTAL', 'TB', 1, 'C', 1);
        $y += 5;

        $pdf->SetFont('helvetica', '', 7);
        $contador = 0;

        // LISTA POR CONTENEDOR
        foreach ($detallesAgrupados as $idx => $grupo) {
            $esRejilla = !empty($grupo['EsProductoUnico']);
            $espacioNecesario = 6 + 4 + 4 + 4.5;

            if ((270 - $y) < $espacioNecesario) {
                $pdf->AddPage();
                $y = 15;

                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetFillColor(240, 240, 240);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetDrawColor(180, 180, 180);

                $pdf->SetXY(10, $y);
                $pdf->Cell(8, 5, '#', 'TB', 0, 'C', 1);
                $pdf->Cell(72, 5, 'PRODUCTO', 'TB', 0, 'L', 1);
                $pdf->Cell(24, 5, 'CANTIDAD', 'TB', 0, 'C', 1);
                $pdf->Cell(30, 5, 'PRECIO UNIT.', 'TB', 0, 'C', 1);
                $pdf->Cell(62, 5, 'SUBTOTAL', 'TB', 1, 'C', 1);
                $y += 5;

                $pdf->SetFont('helvetica', '', 7);
            }

            if ($idx > 0 && (270 - $y) > $espacioNecesario) {
                $y += 3;
            }

            // HEADER CONTENEDOR
            $pdf->SetFont('helvetica', 'B', 8.5);

            if ($esRejilla) {
                $pdf->SetFillColor(237, 233, 254);
                $pdf->SetTextColor(88, 28, 135);
            } else {
                $pdf->SetFillColor(225, 238, 255);
                $pdf->SetTextColor(20, 50, 110);
            }

            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 6, '', 'LTR', 1, 'L', 1);

            $pdf->SetXY(10, $y);

            if ($esRejilla) {
                $pdf->Cell(110, 6, '  [' . $grupo['Codigo'] . ']', 'L', 0, 'L', 1);
                $pdf->Cell(86, 6, '  ' . $grupo['CantidadRejillas'] . ' rejilla(s)   ', 'R', 1, 'R', 1);
            } else {
                $pdf->Cell(110, 6, '  [' . $grupo['Codigo'] . ']  #' . $grupo['OrdenContenedor'], 'L', 0, 'L', 1);
                $pdf->Cell(86, 6, 'Cap: ' . number_format($grupo['CapacidadTotal'], 0, ',', '.') . ' und   ', 'R', 1, 'R', 1);
            }
            $y += 6;

            if (!empty($grupo['SubClienteNombre'])) {
                $pdf->SetFont('helvetica', 'I', 7);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 4, '  Subcliente: ' . $grupo['SubClienteNombre'], 'LR', 1, 'L', 1);
                $y += 4;
                $pdf->SetFont('helvetica', '', 7);
            }

            $pdf->SetTextColor(0, 0, 0);

            // FILAS DE PRODUCTOS
            $pdf->SetFont('helvetica', '', 7);
            $fill = false;

            foreach ($grupo['productos'] as $producto) {
                if ((270 - $y) < 8) {
                    $pdf->AddPage();
                    $y = 15;

                    $pdf->SetFont('helvetica', 'B', 7);
                    $pdf->SetFillColor(240, 240, 240);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetDrawColor(180, 180, 180);

                    $pdf->SetXY(10, $y);
                    $pdf->Cell(8, 5, '#', 'TB', 0, 'C', 1);
                    $pdf->Cell(72, 5, 'PRODUCTO', 'TB', 0, 'L', 1);
                    $pdf->Cell(24, 5, 'CANTIDAD', 'TB', 0, 'C', 1);
                    $pdf->Cell(30, 5, 'PRECIO UNIT.', 'TB', 0, 'C', 1);
                    $pdf->Cell(62, 5, 'SUBTOTAL', 'TB', 1, 'C', 1);
                    $y += 5;

                    $pdf->SetFont('helvetica', '', 7);
                    $fill = false;
                }

                $contador++;
                $nombreProducto = $producto['Descripcion'] ?? '-';
                if (mb_strlen($nombreProducto, 'UTF-8') > 42) {
                    $nombreProducto = mb_substr($nombreProducto, 0, 40, 'UTF-8') . '...';
                }

                if ($esRejilla) {
                    $nombreProducto = $nombreProducto . '  (' . $producto['CantidadRejillas'] . ' rej. x ' . number_format($producto['CantidadPorRejilla'], 0, ',', '.') . ')';
                }

                $pdf->SetXY(10, $y);
                $pdf->Cell(8, 4, $contador . '.', 'LR', 0, 'C', $fill);
                $pdf->Cell(72, 4, ' ' . $nombreProducto, 'LR', 0, 'L', $fill);
                $pdf->Cell(24, 4, number_format($producto['Cantidad'], 0, ',', '.'), 'LR', 0, 'C', $fill);
                $pdf->Cell(30, 4, number_format($producto['Precio'], 2, ',', '.'), 'LR', 0, 'C', $fill);
                $pdf->Cell(62, 4, number_format($producto['Subtotal'], 2, ',', '.'), 'LR', 1, 'C', $fill);
                $y += 4;
                $fill = !$fill;
            }

            // TOTAL CONTENEDOR
            if ((270 - $y) < 8) {
                $pdf->AddPage();
                $y = 15;
            }

            $pdf->SetFont('helvetica', 'B', 7);

            if ($esRejilla) {
                $pdf->SetFillColor(237, 233, 254);
            } else {
                $pdf->SetFillColor(235, 245, 255);
            }

            $textoTotal = $esRejilla
                ? 'TOTAL ' . $grupo['Codigo']
                : 'TOTAL ' . $grupo['Codigo'] . ' #' . $grupo['OrdenContenedor'];

            $pdf->SetXY(10, $y);
            $pdf->Cell(8, 4.5, '', 'LRB', 0, 'C', 1);
            $pdf->Cell(72, 4.5, $textoTotal, 'LRB', 0, 'R', 1);
            $pdf->Cell(24, 4.5, number_format($grupo['total_unidades'], 0, ',', '.'), 'LRB', 0, 'C', 1);
            $pdf->Cell(30, 4.5, '', 'LRB', 0, 'C', 1);
            $pdf->Cell(62, 4.5, 'Bs. ' . number_format($grupo['subtotal'], 2, ',', '.'), 'LRB', 1, 'C', 1);
            $y += 4.5;
        }

        $y += 5;

        // TOTALES DEL PEDIDO
        if ((270 - $y) < 20) {
            $pdf->AddPage();
            $y = 15;
        }

        $totalUnidades = $pedido->detalles->sum('Cantidad');
        $totalContenedores = $detallesAgrupados->count();
        $totalGeneral = $pedido->detalles->sum(function ($item) {
            return $item->Cantidad * $item->Precio;
        });

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetXY(10, $y);
        $pdf->Cell(98, 5, 'Total Contenedores: ' . number_format($totalContenedores, 0, ',', '.'), 0, 0, 'L');
        $pdf->Cell(98, 5, 'Total Unidades: ' . number_format($totalUnidades, 0, ',', '.'), 0, 0, 'L');
        $y += 6;

        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(10, $y, 206, $y);
        $y += 4;

        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(20, 50, 110);
        $pdf->SetXY(10, $y);
        $pdf->Cell(136, 7, 'TOTAL GENERAL DEL PEDIDO', 0, 0, 'R');
        $pdf->SetXY(146, $y);
        $pdf->Cell(60, 7, 'Bs. ' . number_format($totalGeneral, 2, ',', '.'), 0, 1, 'R');
        $y += 10;

        // FIRMAS
        $y += 15;

        if ((270 - $y) < 12) {
            $pdf->AddPage();
            $y = 20;
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);

        $pdf->SetXY(25, $y);
        $pdf->Cell(70, 0.3, '', 'T', 0, 'C');
        $pdf->SetXY(115, $y);
        $pdf->Cell(70, 0.3, '', 'T', 1, 'C');

        $pdf->SetXY(25, $y + 1);
        $pdf->Cell(70, 4, 'Firma Operador', 0, 0, 'C');
        $pdf->SetXY(115, $y + 1);
        $pdf->Cell(70, 4, 'Firma Recibido', 0, 1, 'C');

        // SALIDA
        if (ob_get_length()) {
            ob_end_clean();
        }

        $nombreArchivo = 'Reporte_Pedido_' . ($pedido->NumeroPedido ?? '000000') . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit;
    }
}