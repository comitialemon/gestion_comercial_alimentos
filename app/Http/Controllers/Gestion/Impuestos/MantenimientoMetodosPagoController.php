<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MantenimientoMetodosPagoController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $facturas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('IdOperadorIngresa', $operadorId)
            ->where('IdEstado', 1)
            ->where('LiquidadoVendedor', 0)
            ->where('NumeroFactura', '!=', 0)
            ->orderBy('FechaVenta', 'desc')
            ->orderBy('NumeroFactura', 'desc')
            ->select('IdVentas', 'FechaVenta', 'NumeroFactura', 'ImporteVenta')
            ->get();

        return Inertia::render('Gestion/Impuestos/MantenimientoMetodosPago/Index', [
            'facturas' => $facturas,
        ]);
    }

    public function getMetodosPago($idVenta)
    {
        $clienteId = session('cliente_id');

        $metodosPago = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_liquidacion as vl')
            ->join('impuestos_ventas_liquidacion_concepto as c', function($join) use ($clienteId) {
                $join->on('vl.IdCuenta', '=', 'c.IdCuenta')
                     ->where('c.IdCliente', '=', $clienteId);
            })
            ->where('vl.IdVentas', $idVenta)
            ->select(
                'vl.IdVentasLiquidacion',
                'vl.IdCuenta',
                'vl.Bolivianos',
                'c.Concepto'
            )
            ->get();

        $conceptos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_liquidacion_concepto')
            ->where('IdCliente', $clienteId)
            ->orderBy('Concepto')
            ->get(['IdCuenta', 'Concepto']);

        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $idVenta)
            ->first(['ImporteVenta', 'NumeroFactura']);

        return response()->json([
            'success' => true,
            'metodosPago' => $metodosPago,
            'conceptos' => $conceptos,
            'totalVenta' => $venta->ImporteVenta ?? 0,
            'numeroFactura' => $venta->NumeroFactura ?? '',
        ]);
    }

    public function updateMetodosPago(Request $request, $idVenta)
    {
        $request->validate([
            'pagos' => 'required|array|min:1',
            'pagos.*.IdCuenta' => 'required|exists:conta_cuenta,IdCuenta',
            'pagos.*.Bolivianos' => 'required|numeric|min:0',
        ]);

        $totalPagado = collect($request->pagos)->sum('Bolivianos');
        $totalVenta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $idVenta)
            ->value('ImporteVenta');

        Log::info('=== VALIDACIÓN DE MONTOS ===');
        Log::info('Total pagado: ' . $totalPagado);
        Log::info('Total venta: ' . $totalVenta);
        Log::info('Diferencia: ' . ($totalPagado - $totalVenta));

        // 🔥 VALIDACIÓN ESTRICTA: DEBE SER EXACTAMENTE IGUAL
        if (abs($totalPagado - $totalVenta) > 0.01) { // Margen de 0.01 por problemas de redondeo
            $diferencia = $totalPagado - $totalVenta;
            $mensaje = $diferencia > 0 
                ? "El total pagado ({$totalPagado} Bs) EXCEDE al total de la factura ({$totalVenta} Bs) en {$diferencia} Bs"
                : "El total pagado ({$totalPagado} Bs) es MENOR al total de la factura ({$totalVenta} Bs) en " . abs($diferencia) . " Bs";
            
            return response()->json([
                'success' => false,
                'message' => $mensaje . '. Los montos deben ser EXACTAMENTE IGUALES.',
                'total_pagado' => $totalPagado,
                'total_venta' => $totalVenta,
                'diferencia' => $diferencia,
            ], 422);
        }

        if ($totalPagado == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debe asignar al menos un método de pago con monto mayor a 0',
            ], 422);
        }

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            // Obtener IDs de los métodos de pago que vienen en la solicitud
            $idsEnSolicitud = collect($request->pagos)
                ->filter(function($pago) {
                    return !empty($pago['IdVentasLiquidacion']) && $pago['IdVentasLiquidacion'] > 0;
                })
                ->pluck('IdVentasLiquidacion')
                ->toArray();

            // Eliminar los métodos de pago que NO están en la solicitud
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion')
                ->where('IdVentas', $idVenta)
                ->whereNotIn('IdVentasLiquidacion', $idsEnSolicitud)
                ->delete();

            // Procesar los métodos de pago (insertar o actualizar)
            foreach ($request->pagos as $pago) {
                if (empty($pago['IdVentasLiquidacion']) || $pago['IdVentasLiquidacion'] == 0) {
                    // NUEVO
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->insert([
                            'IdVentas' => $idVenta,
                            'IdDiario' => 0,
                            'IdIdentificador' => 0,
                            'IdCuenta' => $pago['IdCuenta'],
                            'Bolivianos' => $pago['Bolivianos'],
                        ]);
                } else {
                    // ACTUALIZAR
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->where('IdVentasLiquidacion', $pago['IdVentasLiquidacion'])
                        ->update([
                            'IdCuenta' => $pago['IdCuenta'],
                            'Bolivianos' => $pago['Bolivianos'],
                        ]);
                }
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('✅ Métodos de pago actualizados correctamente');

            return response()->json([
                'success' => true,
                'message' => 'Métodos de pago actualizados correctamente',
                'total_pagado' => $totalPagado,
                'total_venta' => $totalVenta,
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            
            Log::error('❌ Error al actualizar métodos de pago: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage(),
            ], 500);
        }
    }
}