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

        // 🔥 Verificar que la venta existe y pertenece al cliente
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $idVenta)
            ->where('IdCliente', $clienteId)
            ->first();

        if (!$venta) {
            return response()->json([
                'success' => false,
                'message' => 'Venta no encontrada'
            ], 404);
        }

        // 🔥 Si la venta está liquidada, no se puede modificar
        if ($venta->LiquidadoVendedor == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Esta venta ya está liquidada y no se puede modificar'
            ], 422);
        }

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

        return response()->json([
            'success' => true,
            'metodosPago' => $metodosPago,
            'conceptos' => $conceptos,
            'totalVenta' => (float) $venta->ImporteVenta,
            'numeroFactura' => $venta->NumeroFactura ?? '',
        ]);
    }

    public function updateMetodosPago(Request $request, $idVenta)
    {
        try {
            $request->validate([
                'pagos' => 'required|array|min:1',
                'pagos.*.IdCuenta' => 'required|integer|exists:impuestos_ventas_liquidacion_concepto,IdCuenta',
                'pagos.*.Bolivianos' => 'required|numeric|min:0',
            ]);

            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');

            // 🔥 Verificar que la venta existe y pertenece al cliente y operador
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $idVenta)
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $sucursalId)
                ->where('IdOperadorIngresa', $operadorId)
                ->first();

            if (!$venta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada o no pertenece a este operador'
                ], 404);
            }

            // 🔥 Si la venta está liquidada, no se puede modificar
            if ($venta->LiquidadoVendedor == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta venta ya está liquidada y no se puede modificar'
                ], 422);
            }

            // 🔥 VALIDACIÓN 1: No permitir IdCuenta duplicados
            $idsCuenta = collect($request->pagos)->pluck('IdCuenta')->toArray();
            $idsUnicos = array_unique($idsCuenta);
            
            if (count($idsCuenta) !== count($idsUnicos)) {
                $duplicados = array_diff_assoc($idsCuenta, $idsUnicos);
                $idsDuplicados = array_unique($duplicados);
                
                $nombresDuplicados = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion_concepto')
                    ->whereIn('IdCuenta', $idsDuplicados)
                    ->pluck('Concepto')
                    ->implode(', ');
                
                return response()->json([
                    'success' => false,
                    'message' => "No se permiten métodos de pago duplicados. Los siguientes están repetidos: {$nombresDuplicados}",
                ], 422);
            }

            // 🔥 Calcular montos con precisión
            $totalPagado = 0;
            foreach ($request->pagos as $pago) {
                $totalPagado += round((float) $pago['Bolivianos'], 2);
            }
            $totalVenta = (float) $venta->ImporteVenta;

            Log::info('=== VALIDACIÓN DE MONTOS ===', [
                'venta_id' => $idVenta,
                'total_pagado' => $totalPagado,
                'total_venta' => $totalVenta,
                'diferencia' => $totalPagado - $totalVenta,
                'cantidad_pagos' => count($request->pagos)
            ]);

            // 🔥 VALIDACIÓN 2: No puede ser 0
            if ($totalPagado == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe asignar al menos un método de pago con monto mayor a 0',
                ], 422);
            }

            // 🔥 VALIDACIÓN 3: No puede exceder el total (con margen de 0.01)
            if ($totalPagado > $totalVenta + 0.01) {
                $diferencia = round($totalPagado - $totalVenta, 2);
                return response()->json([
                    'success' => false,
                    'message' => "El total pagado ({$totalPagado} Bs) EXCEDE al total de la factura ({$totalVenta} Bs) en {$diferencia} Bs",
                    'total_pagado' => $totalPagado,
                    'total_venta' => $totalVenta,
                ], 422);
            }

            // 🔥 VALIDACIÓN 4: Debe ser igual (con margen de 0.01)
            if (abs($totalPagado - $totalVenta) > 0.01) {
                $diferencia = round($totalPagado - $totalVenta, 2);
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

            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            // 🔥 Obtener IDs de los métodos de pago que vienen en la solicitud
            $idsEnSolicitud = collect($request->pagos)
                ->filter(function($pago) {
                    return !empty($pago['IdVentasLiquidacion']) && $pago['IdVentasLiquidacion'] > 0;
                })
                ->pluck('IdVentasLiquidacion')
                ->toArray();

            // 🔥 Eliminar los métodos de pago que NO están en la solicitud
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion')
                ->where('IdVentas', $idVenta)
                ->when(!empty($idsEnSolicitud), function($query) use ($idsEnSolicitud) {
                    return $query->whereNotIn('IdVentasLiquidacion', $idsEnSolicitud);
                })
                ->delete();

            // 🔥 Procesar los métodos de pago (insertar o actualizar)
            foreach ($request->pagos as $pago) {
                $monto = round((float) $pago['Bolivianos'], 2);
                
                if (empty($pago['IdVentasLiquidacion']) || $pago['IdVentasLiquidacion'] == 0) {
                    // NUEVO
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->insert([
                            'IdVentas' => $idVenta,
                            'IdDiario' => 0,
                            'IdIdentificador' => 0,
                            'IdCuenta' => $pago['IdCuenta'],
                            'Bolivianos' => $monto,
                        ]);
                } else {
                    // ACTUALIZAR
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->where('IdVentasLiquidacion', $pago['IdVentasLiquidacion'])
                        ->update([
                            'IdCuenta' => $pago['IdCuenta'],
                            'Bolivianos' => $monto,
                        ]);
                }
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('✅ Métodos de pago actualizados correctamente', [
                'venta_id' => $idVenta,
                'total_pagado' => $totalPagado,
                'total_venta' => $totalVenta,
                'cantidad_pagos' => count($request->pagos),
                'operador_id' => $operadorId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Métodos de pago actualizados correctamente',
                'total_pagado' => $totalPagado,
                'total_venta' => $totalVenta,
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            
            Log::error('❌ Error al actualizar métodos de pago', [
                'venta_id' => $idVenta,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage(),
            ], 500);
        }
    }
}