<?php

namespace App\Http\Controllers\Gestion\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteVentasSupervisorPorOperadorController extends Controller
{
    /**
     * Muestra el reporte agrupado por Año -> Fecha -> Vendedor -> Producto
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // Obtener datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        // Obtener datos de la sucursal
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre', 'NumeroSucursal']);

        // Obtener lista de operadores para el filtro (solo los que tienen ventas)
        $operadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->join('impuestos_ventas as v', 'o.IdOperador', '=', 'v.IdOperadorIngresa')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdEstado', 1)
            ->select('o.IdOperador as id', 'i.Nombre as nombre')
            ->distinct()
            ->orderBy('i.Nombre')
            ->get();

        // Obtener fechas disponibles para los filtros (años)
        $anios = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('IdEstado', 1)
            ->selectRaw('YEAR(FechaVenta) as anio')
            ->distinct()
            ->orderBy('anio', 'desc')
            ->get()
            ->pluck('anio');

        // Filtros
        $fechaInicio = $request->get('fecha_inicio');
        $fechaFin = $request->get('fecha_fin');
        $operadorId = $request->get('operador_id');
        $anio = $request->get('anio');

        // Construir query base - 🔥 SIMPLIFICADA
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->join('impuestos_ventas_detalle', 'impuestos_ventas.IdVentas', '=', 'impuestos_ventas_detalle.idventas')
            ->join('inventario_relacion_ventainventario', 'impuestos_ventas_detalle.idrelacionventainventario', '=', 'inventario_relacion_ventainventario.IdDetalleProducto')
            ->where('impuestos_ventas.IdEstado', 1)
            ->where('impuestos_ventas.IdCliente', $clienteId)
            ->where('impuestos_ventas.IdClienteSucursal', $sucursalId);

        // Aplicar filtros
        if ($fechaInicio) {
            $query->where('impuestos_ventas.FechaVenta', '>=', $fechaInicio);
        }
        if ($fechaFin) {
            $query->where('impuestos_ventas.FechaVenta', '<=', $fechaFin . ' 23:59:59');
        }
        if ($operadorId) {
            $query->where('impuestos_ventas.IdOperadorIngresa', $operadorId);
        }
        if ($anio) {
            $query->whereYear('impuestos_ventas.FechaVenta', $anio);
        }

        // 🔥 SELECT SIMPLIFICADO - sin campos que ya no existen
        $ventas = $query->orderBy('impuestos_ventas.FechaVenta', 'desc')
            ->orderBy('impuestos_ventas.NumeroFactura', 'desc')
            ->get([
                'impuestos_ventas.NumeroFactura',
                'impuestos_ventas.IdEstado',
                'impuestos_ventas.IdCliente',
                'impuestos_ventas.IdClienteSucursal',
                'impuestos_ventas.IdOperadorIngresa',
                'impuestos_ventas.FechaVenta',
                DB::raw("DATE_FORMAT(impuestos_ventas.FechaVenta, '%d/%m/%Y') as Fecha"),
                'inventario_relacion_ventainventario.Detalle as producto',
                'impuestos_ventas_detalle.unidades',
                'impuestos_ventas_detalle.preciounidades',
                'impuestos_ventas_detalle.totalbolivianos'
            ]);

        // Pre-cargar nombres de operadores (evitar consultas dentro del loop)
        $nombresOperadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->whereIn('o.IdOperador', $ventas->pluck('IdOperadorIngresa')->unique())
            ->pluck('i.Nombre', 'o.IdOperador');

        // Agrupar por Año -> Fecha -> Operador -> Producto
        $reportePorAnio = [];
        
        foreach ($ventas as $venta) {
            $anioVenta = date('Y', strtotime($venta->FechaVenta));
            $fecha = $venta->Fecha;
            $operadorIdVenta = $venta->IdOperadorIngresa;
            
            // Inicializar año
            if (!isset($reportePorAnio[$anioVenta])) {
                $reportePorAnio[$anioVenta] = [
                    'anio' => $anioVenta,
                    'fechas' => [],
                    'total_anio' => 0,
                    'total_unidades_anio' => 0
                ];
            }
            
            // Inicializar fecha
            if (!isset($reportePorAnio[$anioVenta]['fechas'][$fecha])) {
                $reportePorAnio[$anioVenta]['fechas'][$fecha] = [
                    'fecha' => $fecha,
                    'fecha_original' => $venta->FechaVenta,
                    'operadores' => [],
                    'total_fecha' => 0,
                    'total_unidades_fecha' => 0
                ];
            }
            
            // Inicializar operador
            if (!isset($reportePorAnio[$anioVenta]['fechas'][$fecha]['operadores'][$operadorIdVenta])) {
                $reportePorAnio[$anioVenta]['fechas'][$fecha]['operadores'][$operadorIdVenta] = [
                    'id' => $operadorIdVenta,
                    'nombre' => $nombresOperadores[$operadorIdVenta] ?? 'Desconocido',
                    'productos' => [],
                    'total_ventas' => 0,
                    'total_unidades' => 0
                ];
            }
            
            // 🔥 Agrupar por producto (usando el nombre del producto como clave)
            $productoKey = $venta->producto;
            
            if (!isset($reportePorAnio[$anioVenta]['fechas'][$fecha]['operadores'][$operadorIdVenta]['productos'][$productoKey])) {
                $reportePorAnio[$anioVenta]['fechas'][$fecha]['operadores'][$operadorIdVenta]['productos'][$productoKey] = [
                    'detalle' => $venta->producto,
                    'unidades' => 0,
                    'precio_unitario' => $venta->preciounidades,
                    'total' => 0,
                    'numero_factura' => $venta->NumeroFactura
                ];
            }
            
            $reportePorAnio[$anioVenta]['fechas'][$fecha]['operadores'][$operadorIdVenta]['productos'][$productoKey]['unidades'] += $venta->unidades;
            $reportePorAnio[$anioVenta]['fechas'][$fecha]['operadores'][$operadorIdVenta]['productos'][$productoKey]['total'] += $venta->totalbolivianos;
            $reportePorAnio[$anioVenta]['fechas'][$fecha]['operadores'][$operadorIdVenta]['total_ventas'] += $venta->totalbolivianos;
            $reportePorAnio[$anioVenta]['fechas'][$fecha]['operadores'][$operadorIdVenta]['total_unidades'] += $venta->unidades;
            $reportePorAnio[$anioVenta]['fechas'][$fecha]['total_fecha'] += $venta->totalbolivianos;
            $reportePorAnio[$anioVenta]['fechas'][$fecha]['total_unidades_fecha'] += $venta->unidades;
            $reportePorAnio[$anioVenta]['total_anio'] += $venta->totalbolivianos;
            $reportePorAnio[$anioVenta]['total_unidades_anio'] += $venta->unidades;
        }
        
        // Ordenar fechas dentro de cada año (descendente)
        foreach ($reportePorAnio as $anio => $data) {
            $fechas = $data['fechas'];
            uasort($fechas, function($a, $b) {
                return strtotime($b['fecha_original']) - strtotime($a['fecha_original']);
            });
            $reportePorAnio[$anio]['fechas'] = array_values($fechas);
        }
        
        // Ordenar años (descendente)
        krsort($reportePorAnio);
        $reporteFinal = array_values($reportePorAnio);

        return Inertia::render('Gestion/Reportes/ReporteVentasSupervisorPorOperador', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'reporte' => $reporteFinal,
            'operadores' => $operadores,
            'anios' => $anios,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'operador_id' => $operadorId,
                'anio' => $anio,
            ],
        ]);
    }
}