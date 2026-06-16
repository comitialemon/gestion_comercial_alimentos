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
        // 🔥 QUITAR validación de IdVentasLiquidacion (puede ser 0 para nuevos)
        $request->validate([
            'pagos' => 'required|array|min:1',
            'pagos.*.IdCuenta' => 'required|exists:conta_cuenta,IdCuenta',
            'pagos.*.Bolivianos' => 'required|numeric|min:0',
        ]);

        // 🔥 CALCULAR TOTAL DE LOS MONTOS
        $totalPagado = collect($request->pagos)->sum('Bolivianos');
        $totalVenta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $idVenta)
            ->value('ImporteVenta');

        Log::info('=== UPDATE METODOS PAGO ===');
        Log::info('Venta ID: ' . $idVenta);
        Log::info('Total pagado: ' . $totalPagado);
        Log::info('Total venta: ' . $totalVenta);

        // 🔥 VALIDAR QUE NO SUPERE EL TOTAL
        if ($totalPagado > $totalVenta) {
            return response()->json([
                'success' => false,
                'message' => "El total pagado ({$totalPagado}) supera el total de la factura ({$totalVenta})",
                'total_pagado' => $totalPagado,
                'total_venta' => $totalVenta,
            ], 422);
        }

        // 🔥 VALIDAR QUE NO SEAN TODOS CERO
        if ($totalPagado == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debe asignar al menos un método de pago con monto mayor a 0',
            ], 422);
        }

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $pagosActualizados = [];

            foreach ($request->pagos as $pago) {
                Log::info('Procesando pago:', $pago);
                
                // 🔥 SI ES NUEVO (IdVentasLiquidacion == 0) → INSERT
                if (empty($pago['IdVentasLiquidacion']) || $pago['IdVentasLiquidacion'] == 0) {
                    Log::info('🔹 NUEVO método de pago - INSERT');
                    
                    $newId = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->insertGetId([
                            'IdVentas' => $idVenta,
                            'IdDiario' => 0,
                            'IdIdentificador' => 0,
                            'IdCuenta' => $pago['IdCuenta'],
                            'Bolivianos' => $pago['Bolivianos'],
                            // ❌ ELIMINAR 'EfectivoRecibido' porque no existe en tu tabla
                        ]);
                    
                    Log::info('✅ Nuevo método creado con ID: ' . $newId);
                    
                    $pagosActualizados[] = [
                        'IdVentasLiquidacion' => $newId,
                        'IdCuenta' => $pago['IdCuenta'],
                        'Bolivianos' => $pago['Bolivianos'],
                    ];
                } else {
                    // 🔥 SI EXISTE (IdVentasLiquidacion > 0) → UPDATE
                    Log::info('🔹 Método EXISTENTE - UPDATE ID: ' . $pago['IdVentasLiquidacion']);
                    
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->where('IdVentasLiquidacion', $pago['IdVentasLiquidacion'])
                        ->update([
                            'IdCuenta' => $pago['IdCuenta'],
                            'Bolivianos' => $pago['Bolivianos'],
                        ]);
                    
                    $pagosActualizados[] = [
                        'IdVentasLiquidacion' => $pago['IdVentasLiquidacion'],
                        'IdCuenta' => $pago['IdCuenta'],
                        'Bolivianos' => $pago['Bolivianos'],
                    ];
                }
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('✅ Métodos de pago actualizados correctamente');

            return response()->json([
                'success' => true,
                'message' => 'Métodos de pago actualizados correctamente',
                'total_pagado' => $totalPagado,
                'total_venta' => $totalVenta,
                'pagos' => $pagosActualizados,
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            
            Log::error('❌ Error al actualizar métodos de pago: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage(),
            ], 500);
        }
    }
}