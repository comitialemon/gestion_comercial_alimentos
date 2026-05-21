<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReporteVentasSucursalController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        // Consulta base
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_detalle as vd', 'v.IdVentas', '=', 'vd.idventas')
            ->join('inventario_relacion_ventainventario as rvi', 'vd.idrelacionventainventario', '=', 'rvi.IdDetalleProducto')
            ->join('inventario_relacion_ventainventario_detalle as rvid', 'rvi.IdDetalleProducto', '=', 'rvid.IdDetalleProducto')
            ->join('inventario_productodetalle as pd', 'rvid.IdProducto', '=', 'pd.IdProducto')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
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

        // Obtener datos crudos
        $datosCrudos = $query->select(
                DB::raw('DATE(v.FechaVenta) as Fecha'),
                'v.NumeroFactura',
                'rvi.IdVentaGrupo',
                'rvi.Detalle as ProductoVenta',
                'pd.Descripcion as ProductoInventario',
                'vd.unidades',
                'vd.preciounidades as PrecioUnidades',
                'vd.totalbolivianos as Total',
                'v.IdOperadorIngresa'
            )
            ->orderBy('rvi.Detalle', 'asc')
            ->orderBy('Fecha', 'asc')
            ->get();

        // Obtener operadores para el filtro
        $operadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('todos_operador as op', 'v.IdOperadorIngresa', '=', 'op.IdOperador')
            ->join('todos_identificador as i', 'op.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdEstado', 1)
            ->select('v.IdOperadorIngresa as id', 'i.Nombre as nombre')
            ->distinct()
            ->get();

        // Aplicar filtro de operador si existe
        if ($request->filled('operador')) {
            $datosCrudos = $datosCrudos->filter(function($item) use ($request) {
                return $item->IdOperadorIngresa == $request->operador;
            });
        }

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

        return Inertia::render('Gestion/Impuestos/ReporteVentasSucursal/Index', [
            'reporte' => $resultado,
            'operadores' => $operadores,
            'filtros' => [
                'fecha' => $request->fecha,
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
                'operador' => $request->operador,
            ],
            'tieneFiltros' => $request->filled('fecha') || $request->filled('fecha_desde') || $request->filled('fecha_hasta') || $request->filled('operador'),
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

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_detalle as vd', 'v.IdVentas', '=', 'vd.idventas')
            ->join('inventario_relacion_ventainventario as rvi', 'vd.idrelacionventainventario', '=', 'rvi.IdDetalleProducto')
            ->join('inventario_relacion_ventainventario_detalle as rvid', 'rvi.IdDetalleProducto', '=', 'rvid.IdDetalleProducto')
            ->join('inventario_productodetalle as pd', 'rvid.IdProducto', '=', 'pd.IdProducto')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdEstado', 1)
            ->where('rvi.Detalle', $request->producto);

        if ($request->filled('fecha')) {
            $query->whereDate('v.FechaVenta', $request->fecha);
        }
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->whereDate('v.FechaVenta', '>=', $request->fecha_desde)
                  ->whereDate('v.FechaVenta', '<=', $request->fecha_hasta);
        }
        if ($request->filled('operador')) {
            $query->where('v.IdOperadorIngresa', $request->operador);
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
                'v.IdOperadorIngresa'
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