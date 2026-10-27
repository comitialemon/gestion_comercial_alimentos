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

        // Operadores PedidoClientes SIN billetera
        $idsConBilletera = BilleteraOperador::where('IdCliente', $clienteId)
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

        $existe = BilleteraOperador::where('IdOperadorPedidoCliente', $request->IdOperadorPedidoCliente)->exists();
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Este operador ya tiene una billetera creada.'
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
                'SaldoMinimoAlerta' => $saldoMinimo, // ✅ siempre número
                'IdOperadorInserta' => $operadorId,
                'FechaCreacion' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Cache::forget("billetera_operador_{$billetera->IdOperador}");

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

            Cache::forget("billetera_operador_{$billetera->IdOperador}");

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

            Cache::forget("billetera_operador_{$billetera->IdOperador}");

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

        Cache::forget("billetera_operador_{$billetera->IdOperador}");

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

        Cache::forget("billetera_operador_{$billetera->IdOperador}");

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

        $billetera = BilleteraOperador::where('IdOperador', $operadorId)
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
        if (!$operadorId) {
            return response()->json(['tiene_billetera' => false]);
        }

        $data = Cache::remember(
            "billetera_operador_{$operadorId}",
            1, // ← 🔥 CAMBIO: de 5 a 1 segundo
            function () use ($operadorId) {
                $billetera = BilleteraOperador::where('IdOperador', $operadorId)
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

        $idsConBilletera = BilleteraOperador::where('IdCliente', $clienteId)
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
    //IMPRIMIR MOVIMIENTOS POR PERSONA EN PDF
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

            // Calcular la altura de la fila para que se ajuste al texto más largo
            // MultiCell usa el ancho para calcular cuántas líneas ocupará
            $alturaFecha = $pdf->getStringHeight(20, $fecha);
            $alturaTipo = $pdf->getStringHeight(20, $tipo);
            $alturaGlosa = $pdf->getStringHeight(80, $glosa);
            $alturaMax = max($alturaFecha, $alturaTipo, $alturaGlosa, 4.5); // mínimo 4.5mm

            if ((270 - $y) < $alturaMax + 5) {
                $pdf->AddPage();
                $y = 15;
                // Redibujar cabecera
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
}