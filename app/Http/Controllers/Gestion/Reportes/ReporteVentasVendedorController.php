<?php

namespace App\Http\Controllers\Gestion\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReporteVentasVendedorController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

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

        $esRango = false;
        
        if ($request->filled('fecha')) {
            $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha, 'America/La_Paz')->toDateString();
            $query->whereDate('v.FechaVenta', $fecha);
        } elseif ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $esRango = true;
            $fechaDesde = Carbon::createFromFormat('Y-m-d', $request->fecha_desde, 'America/La_Paz')->toDateString();
            $fechaHasta = Carbon::createFromFormat('Y-m-d', $request->fecha_hasta, 'America/La_Paz')->toDateString();
            $query->whereDate('v.FechaVenta', '>=', $fechaDesde)
                ->whereDate('v.FechaVenta', '<=', $fechaHasta);
        }

        if ($request->filled('metodo_pago')) {
            $query->where('cc.Descripcion', $request->metodo_pago);
        }

        // 🔥 CORREGIDO: Ordenar fechas de forma DESCENDENTE (más reciente primero)
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
            ->orderBy('Fecha', 'desc')  // ✅ DESCENDENTE
            ->get();

        if ($esRango) {
            // 🔥 CORREGIDO: Obtener fechas únicas ordenadas DESCENDENTE
            $fechasUnicas = $datosCrudos->unique('Fecha')->pluck('Fecha')->values()->sortDesc()->values();
            
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
            
            // 🔥 CORREGIDO: Totales por fecha ordenados DESCENDENTE
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
            $fecha = Carbon::createFromFormat('Y-m-d', $request->fecha, 'America/La_Paz')->toDateString();
            $query->whereDate('v.FechaVenta', $fecha);
        }
        
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $fechaDesde = Carbon::createFromFormat('Y-m-d', $request->fecha_desde, 'America/La_Paz')->toDateString();
            $fechaHasta = Carbon::createFromFormat('Y-m-d', $request->fecha_hasta, 'America/La_Paz')->toDateString();
            $query->whereDate('v.FechaVenta', '>=', $fechaDesde)
                  ->whereDate('v.FechaVenta', '<=', $fechaHasta);
        }
        
        if ($request->filled('metodo_pago')) {
            $query->where('cc.Descripcion', $request->metodo_pago);
        }

        // 🔥 CORREGIDO: Ordenar de forma DESCENDENTE (más reciente primero)
        $detalles = $query->select(
                DB::raw('DATE(v.FechaVenta) as FechaVentaRaw'),
                'v.NumeroFactura',
                'rvi.Detalle as ProductoVenta',
                'vd.unidades',
                'vd.preciounidades as PrecioUnidades',
                'vd.totalbolivianos as Total',
                DB::raw("COALESCE(cc.Descripcion, 'SIN MÉTODO DE PAGO') as MetodoPago")
            )
            ->orderBy('v.FechaVenta', 'desc')  // ✅ DESCENDENTE
            ->get();

        // Formatear fechas
        $detallesFormateados = $detalles->map(function($item) {
            if (empty($item->FechaVentaRaw)) {
                $fechaFormateada = '-';
            } else {
                $fechaStr = $item->FechaVentaRaw;
                if (is_string($fechaStr) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaStr)) {
                    $partes = explode('-', $fechaStr);
                    $fechaFormateada = $partes[2] . '/' . $partes[1] . '/' . $partes[0];
                } else {
                    try {
                        $fechaFormateada = Carbon::parse($fechaStr)->format('d/m/Y');
                    } catch (\Exception $e) {
                        $fechaFormateada = $fechaStr;
                    }
                }
            }

            return [
                'FechaVenta' => $fechaFormateada,
                'NumeroFactura' => $item->NumeroFactura,
                'ProductoVenta' => $item->ProductoVenta,
                'unidades' => $item->unidades,
                'PrecioUnidades' => $item->PrecioUnidades,
                'Total' => $item->Total,
                'MetodoPago' => $item->MetodoPago,
            ];
        });

        $totalUnidades = $detalles->sum('unidades');
        $totalBolivianos = $detalles->sum('Total');

        return response()->json([
            'success' => true,
            'producto' => $request->producto,
            'detalles' => $detallesFormateados,
            'totalUnidades' => $totalUnidades,
            'totalBolivianos' => $totalBolivianos,
        ]);
    }

    
}