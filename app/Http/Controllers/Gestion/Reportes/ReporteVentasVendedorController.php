<?php

namespace App\Http\Controllers\Gestion\Reportes;

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

        // 🔥 Consulta SIMPLIFICADA - sin joins a tablas innecesarias
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_detalle as vd', 'v.IdVentas', '=', 'vd.idventas')
            ->join('inventario_relacion_ventainventario as rvi', 'vd.idrelacionventainventario', '=', 'rvi.IdDetalleProducto')
            ->leftJoin('impuestos_ventas_liquidacion as vl', 'v.IdVentas', '=', 'vl.IdVentas')
            ->leftJoin('conta_cuenta as cc', 'vl.IdCuenta', '=', 'cc.IdCuenta')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->where('v.IdEstado', 1);

        // Aplicar filtros de fecha
        $esRango = false;
        
        if ($request->filled('fecha')) {
            $query->whereDate('v.FechaVenta', $request->fecha);
        } elseif ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $esRango = true;
            $query->whereDate('v.FechaVenta', '>=', $request->fecha_desde)
                ->whereDate('v.FechaVenta', '<=', $request->fecha_hasta);
        }

        // 🔥 FILTRO POR GRUPO - ELIMINADO porque IdVentaGrupo ya no existe
        // if ($request->filled('grupo')) {
        //     $query->where('rvi.IdVentaGrupo', $request->grupo);
        // }

        if ($request->filled('metodo_pago')) {
            $query->where('cc.Descripcion', $request->metodo_pago);
        }

        // 🔥 SELECT SIMPLIFICADO
        $datosCrudos = $query->select(
                DB::raw('DATE(v.FechaVenta) as Fecha'),
                'v.NumeroFactura',
                'rvi.Detalle as ProductoVenta',
                'vd.unidades',
                'vd.preciounidades as PrecioUnidades',
                'vd.totalbolivianos as Total',
                DB::raw("COALESCE(cc.Descripcion, 'SIN MÉTODO DE PAGO') as MetodoPago")
            )
            ->orderBy('rvi.Detalle', 'asc')
            ->orderBy('Fecha', 'asc')
            ->get();

        if ($esRango) {
            // Obtener fechas únicas con ventas
            $fechasUnicas = $datosCrudos->unique('Fecha')->pluck('Fecha')->values();
            
            // Agrupar por producto
            $productosArray = [];
            $productosGroup = $datosCrudos->groupBy('ProductoVenta');
            
            foreach ($productosGroup as $producto => $items) {
                $fila = [
                    'Producto' => $producto,
                    'detalles' => []
                ];
                
                $totalUnidadesProducto = 0;
                $totalBsProducto = 0;
                
                foreach ($fechasUnicas as $fecha) {
                    $ventasFecha = $items->where('Fecha', $fecha);
                    $unidades = $ventasFecha->sum('unidades');
                    $total = $ventasFecha->sum('Total');
                    
                    $fila['detalles'][] = [
                        'fecha' => $fecha,
                        'unidades' => $unidades,
                        'total' => $total,
                    ];
                    
                    $totalUnidadesProducto += $unidades;
                    $totalBsProducto += $total;
                }
                
                $fila['totalUnidades'] = $totalUnidadesProducto;
                $fila['totalBs'] = $totalBsProducto;
                
                $productosArray[] = $fila;
            }
            
            // Calcular total por fecha
            $totalesPorFecha = [];
            foreach ($fechasUnicas as $fecha) {
                $unidadesFecha = 0;
                $totalFecha = 0;
                foreach ($productosArray as $producto) {
                    $detalle = collect($producto['detalles'])->firstWhere('fecha', $fecha);
                    if ($detalle) {
                        $unidadesFecha += $detalle['unidades'];
                        $totalFecha += $detalle['total'];
                    }
                }
                $totalesPorFecha[] = [
                    'fecha' => $fecha,
                    'unidades' => $unidadesFecha,
                    'total' => $totalFecha,
                ];
            }
            
            $resultado = [
                'tipo' => 'rango',
                'fechas' => $fechasUnicas,
                'productos' => $productosArray,
                'totalesPorFecha' => $totalesPorFecha,
                'totalGeneralUnidades' => collect($productosArray)->sum('totalUnidades'),
                'totalGeneralBs' => collect($productosArray)->sum('totalBs'),
            ];
        } else {
            // Modo día único
            $productosArray = [];
            $productosGroup = $datosCrudos->groupBy('ProductoVenta');
            
            foreach ($productosGroup as $producto => $items) {
                $productosArray[] = [
                    'Producto' => $producto,
                    'Unidades' => $items->sum('unidades'),
                    'Total' => $items->sum('Total'),
                ];
            }
            
            $resultado = [
                'tipo' => 'dia',
                'productos' => $productosArray,
                'totalGeneralUnidades' => collect($productosArray)->sum('Unidades'),
                'totalGeneralBs' => collect($productosArray)->sum('Total'),
            ];
        }

        // 🔥 GRUPOS - Ya no se usa porque IdVentaGrupo no existe, pero lo dejamos vacío
        $grupos = [];

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

        return Inertia::render('Gestion/Reportes/ReporteVentasVendedor/Index', [
            'reporte' => $resultado,
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
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // 🔥 CONSULTA SIMPLIFICADA
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_detalle as vd', 'v.IdVentas', '=', 'vd.idventas')
            ->join('inventario_relacion_ventainventario as rvi', 'vd.idrelacionventainventario', '=', 'rvi.IdDetalleProducto')
            ->leftJoin('impuestos_ventas_liquidacion as vl', 'v.IdVentas', '=', 'vl.IdVentas')
            ->leftJoin('conta_cuenta as cc', 'vl.IdCuenta', '=', 'cc.IdCuenta')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->where('v.IdEstado', 1)
            ->where('rvi.Detalle', $request->producto);

        if ($request->filled('fecha')) {
            $query->whereDate('v.FechaVenta', $request->fecha);
        }
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->whereDate('v.FechaVenta', '>=', $request->fecha_desde)
                  ->whereDate('v.FechaVenta', '<=', $request->fecha_hasta);
        }
        // 🔥 FILTRO POR GRUPO - ELIMINADO
        // if ($request->filled('grupo')) {
        //     $query->where('rvi.IdVentaGrupo', $request->grupo);
        // }
        if ($request->filled('metodo_pago')) {
            $query->where('cc.Descripcion', $request->metodo_pago);
        }

        $detalles = $query->select(
                DB::raw('DATE(v.FechaVenta) as FechaVenta'),
                'v.NumeroFactura',
                'rvi.Detalle as ProductoVenta',
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
}