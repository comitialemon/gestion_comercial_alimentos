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
}