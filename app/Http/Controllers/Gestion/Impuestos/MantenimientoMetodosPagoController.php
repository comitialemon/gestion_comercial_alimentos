<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class MantenimientoMetodosPagoController extends Controller
{
    /**
     * Listado de facturas pendientes (no liquidadas)
     */
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

    /**
     * Obtener los métodos de pago de una factura
     */
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

        // Obtener los conceptos disponibles para los selects
        $conceptos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_liquidacion_concepto')
            ->where('IdCliente', $clienteId)
            ->orderBy('Concepto')
            ->get(['IdCuenta', 'Concepto']);

        // Obtener el total de la venta
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

    /**
     * Actualizar los métodos de pago de una factura
     */
    public function updateMetodosPago(Request $request, $idVenta)
    {
        $request->validate([
            'pagos' => 'required|array',
            'pagos.*.IdVentasLiquidacion' => 'required|exists:impuestos_ventas_liquidacion,IdVentasLiquidacion',
            'pagos.*.IdCuenta' => 'required|exists:conta_cuenta,IdCuenta',
            'pagos.*.Bolivianos' => 'required|numeric|min:0',
        ]);

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            foreach ($request->pagos as $pago) {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion')
                    ->where('IdVentasLiquidacion', $pago['IdVentasLiquidacion'])
                    ->update([
                        'IdCuenta' => $pago['IdCuenta'],
                        'Bolivianos' => $pago['Bolivianos'],
                    ]);
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Métodos de pago actualizados correctamente',
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage(),
            ], 500);
        }
    }
}