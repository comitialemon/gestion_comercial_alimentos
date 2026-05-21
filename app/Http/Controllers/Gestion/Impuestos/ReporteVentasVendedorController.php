<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReporteVentasVendedorController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // Consulta base (sin agrupar, para contar)
        $baseQuery = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_detalle as vd', 'v.IdVentas', '=', 'vd.idventas')
            ->join('inventario_relacion_ventainventario as rvi', 'vd.idrelacionventainventario', '=', 'rvi.IdDetalleProducto')
            ->join('inventario_relacion_ventainventario_detalle as rvid', 'rvi.IdDetalleProducto', '=', 'rvid.IdDetalleProducto')
            ->join('inventario_productodetalle as pd', 'rvid.IdProducto', '=', 'pd.IdProducto')
            ->leftJoin('impuestos_ventas_liquidacion as vl', 'v.IdVentas', '=', 'vl.IdVentas')
            ->leftJoin('conta_cuenta as cc', 'vl.IdCuenta', '=', 'cc.IdCuenta')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->where('v.IdEstado', 1);

        // Aplicar filtros de fecha
        if ($request->filled('fecha')) {
            $baseQuery->whereDate('v.FechaVenta', $request->fecha);
        }
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $baseQuery->whereDate('v.FechaVenta', '>=', $request->fecha_desde)
                      ->whereDate('v.FechaVenta', '<=', $request->fecha_hasta);
        } elseif ($request->filled('fecha_desde')) {
            $baseQuery->whereDate('v.FechaVenta', '>=', $request->fecha_desde);
        } elseif ($request->filled('fecha_hasta')) {
            $baseQuery->whereDate('v.FechaVenta', '<=', $request->fecha_hasta);
        }

        // Aplicar filtros adicionales
        if ($request->filled('grupo')) {
            $baseQuery->where('rvi.IdVentaGrupo', $request->grupo);
        }
        if ($request->filled('metodo_pago')) {
            $baseQuery->where('cc.Descripcion', $request->metodo_pago);
        }

        // Consulta agrupada para la tabla principal
        $ventasAgrupadas = (clone $baseQuery)
            ->select(
                'rvi.Detalle as ProductoVenta',
                DB::raw('SUM(vd.unidades) as TotalUnidades'),
                DB::raw('SUM(vd.totalbolivianos) as TotalBolivianos')
            )
            ->groupBy('rvi.Detalle', 'rvi.IdDetalleProducto')
            ->orderBy('rvi.Detalle', 'asc')
            ->get();

        // Totales acumulados
        $totalUnidades = $ventasAgrupadas->sum('TotalUnidades');
        $totalBolivianos = $ventasAgrupadas->sum('TotalBolivianos');

        // Obtener grupos para el filtro
        $grupos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario_grupouno')
            ->where('IdCliente', $clienteId)
            ->orderBy('Detalle')
            ->get(['IdVentaGrupo as id', 'Detalle as nombre']);

        // Obtener métodos de pago disponibles
        $metodosPago = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_liquidacion as vl', 'v.IdVentas', '=', 'vl.IdVentas')
            ->join('conta_cuenta as cc', 'vl.IdCuenta', '=', 'cc.IdCuenta')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->where('v.IdEstado', 1)
            ->select('cc.Descripcion as nombre')
            ->distinct()
            ->pluck('nombre');

        return Inertia::render('Gestion/Impuestos/ReporteVentasVendedor/Index', [
            'ventasAgrupadas' => $ventasAgrupadas,
            'totalUnidades' => $totalUnidades,
            'totalBolivianos' => $totalBolivianos,
            'grupos' => $grupos,
            'metodosPago' => $metodosPago,
            'filtros' => [
                'fecha' => $request->fecha,
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
                'grupo' => $request->grupo,
                'metodo_pago' => $request->metodo_pago,
            ],
            'tieneFiltros' => $request->filled('fecha') || $request->filled('fecha_desde') || $request->filled('fecha_hasta') || $request->filled('grupo') || $request->filled('metodo_pago'),
        ]);
    }

    /**
     * Obtener detalle de un producto específico para el modal
     */
    public function getDetalleProducto(Request $request)
    {
        $request->validate([
            'producto' => 'required|string',
            'fecha' => 'nullable|date',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
            'grupo' => 'nullable|string',
            'metodo_pago' => 'nullable|string',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_detalle as vd', 'v.IdVentas', '=', 'vd.idventas')
            ->join('inventario_relacion_ventainventario as rvi', 'vd.idrelacionventainventario', '=', 'rvi.IdDetalleProducto')
            ->join('inventario_relacion_ventainventario_detalle as rvid', 'rvi.IdDetalleProducto', '=', 'rvid.IdDetalleProducto')
            ->join('inventario_productodetalle as pd', 'rvid.IdProducto', '=', 'pd.IdProducto')
            ->leftJoin('impuestos_ventas_liquidacion as vl', 'v.IdVentas', '=', 'vl.IdVentas')
            ->leftJoin('conta_cuenta as cc', 'vl.IdCuenta', '=', 'cc.IdCuenta')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->where('v.IdEstado', 1)
            ->where('rvi.Detalle', $request->producto);

        // Aplicar mismos filtros que en el reporte principal
        if ($request->filled('fecha')) {
            $query->whereDate('v.FechaVenta', $request->fecha);
        }
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->whereDate('v.FechaVenta', '>=', $request->fecha_desde)
                  ->whereDate('v.FechaVenta', '<=', $request->fecha_hasta);
        }
        if ($request->filled('grupo')) {
            $query->where('rvi.IdVentaGrupo', $request->grupo);
        }
        if ($request->filled('metodo_pago')) {
            $query->where('cc.Descripcion', $request->metodo_pago);
        }

        $detalles = $query->select(
                DB::raw('DATE(v.FechaVenta) as FechaVenta'),
                'v.NumeroFactura',
                'rvi.IdVentaGrupo',
                'rvi.Detalle as ProductoVenta',
                'pd.Descripcion as ProductoInventario',
                'vd.unidades',
                'vd.preciounidades as PrecioUnidades',
                'vd.totalbolivianos as Total',
                DB::raw("COALESCE(cc.Descripcion, 'SIN MÉTODO DE PAGO') as MetodoPago")
            )
            ->orderBy('v.FechaVenta', 'desc')
            ->get();

        $totalUnidades = $detalles->sum('unidades');
        $totalBolivianos = $detalles->sum('Total');

        return response()->json([
            'success' => true,
            'producto' => $request->producto,
            'detalles' => $detalles,
            'totalUnidades' => $totalUnidades,
            'totalBolivianos' => $totalBolivianos,
        ]);
    }

    public function export(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_detalle as vd', 'v.IdVentas', '=', 'vd.idventas')
            ->join('inventario_relacion_ventainventario as rvi', 'vd.idrelacionventainventario', '=', 'rvi.IdDetalleProducto')
            ->join('inventario_relacion_ventainventario_detalle as rvid', 'rvi.IdDetalleProducto', '=', 'rvid.IdDetalleProducto')
            ->join('inventario_productodetalle as pd', 'rvid.IdProducto', '=', 'pd.IdProducto')
            ->leftJoin('impuestos_ventas_liquidacion as vl', 'v.IdVentas', '=', 'vl.IdVentas')
            ->leftJoin('conta_cuenta as cc', 'vl.IdCuenta', '=', 'cc.IdCuenta')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->where('v.IdEstado', 1);

        if ($request->filled('fecha')) {
            $query->whereDate('v.FechaVenta', $request->fecha);
        }
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->whereDate('v.FechaVenta', '>=', $request->fecha_desde)
                  ->whereDate('v.FechaVenta', '<=', $request->fecha_hasta);
        }
        if ($request->filled('grupo')) {
            $query->where('rvi.IdVentaGrupo', $request->grupo);
        }
        if ($request->filled('metodo_pago')) {
            $query->where('cc.Descripcion', $request->metodo_pago);
        }

        $ventas = $query->select(
                'rvi.Detalle as ProductoVenta',
                DB::raw('SUM(vd.unidades) as TotalUnidades'),
                DB::raw('SUM(vd.totalbolivianos) as TotalBolivianos')
            )
            ->groupBy('rvi.Detalle', 'rvi.IdDetalleProducto')
            ->orderBy('rvi.Detalle', 'asc')
            ->get();

        $filename = 'reporte_ventas_agrupado_' . date('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://temp', 'w+');

        fputcsv($handle, ['Producto', 'Unidades', 'Total Bs'], ';');

        foreach ($ventas as $venta) {
            fputcsv($handle, [
                $venta->ProductoVenta,
                number_format($venta->TotalUnidades, 4, ',', '.'),
                number_format($venta->TotalBolivianos, 2, ',', '.'),
            ], ';');
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ]);
    }
}