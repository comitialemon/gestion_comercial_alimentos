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
     * 🔥 CON FORMATO VISUAL UNIFICADO
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

        // Construir query base
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

        // SELECT simplificado
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

        // Pre-cargar nombres de operadores
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
            
            // Agrupar por producto
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
        foreach ($reportePorAnio as $anioKey => $data) {
            $fechas = $data['fechas'];
            uasort($fechas, function($a, $b) {
                return strtotime($b['fecha_original']) - strtotime($a['fecha_original']);
            });
            $reportePorAnio[$anioKey]['fechas'] = array_values($fechas);
        }
        
        // Ordenar años (descendente)
        krsort($reportePorAnio);
        $reporteFinal = array_values($reportePorAnio);

        // 🔥 CALCULAR TOTALES GENERALES
        $totalGeneralVentas = 0;
        $totalGeneralUnidades = 0;
        foreach ($reporteFinal as $anioData) {
            $totalGeneralVentas += $anioData['total_anio'];
            $totalGeneralUnidades += $anioData['total_unidades_anio'];
        }

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
            'totales' => [
                'ventas' => $totalGeneralVentas,
                'unidades' => $totalGeneralUnidades,
            ],
        ]);
    }

    /**
     * 🔥 OBTENER VENTAS DETALLADAS DE UN OPERADOR PARA EL MODAL
     */
    public function getVentasPorOperador(Request $request)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            
            $request->validate([
                'operador_id' => 'required|integer',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date',
            ]);

            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas as v')
                ->join('impuestos_ventas_detalle as d', 'v.IdVentas', '=', 'd.idventas')
                ->join('inventario_relacion_ventainventario as r', 'd.idrelacionventainventario', '=', 'r.IdDetalleProducto')
                ->join('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->leftJoin('todos_identificador as cli', 'v.IdNIT', '=', 'cli.IdIdentificador')
                ->where('v.IdCliente', $clienteId)
                ->where('v.IdClienteSucursal', $sucursalId)
                ->where('v.IdEstado', 1)
                ->where('v.IdOperadorIngresa', $request->operador_id);

            if ($request->fecha_inicio) {
                $query->where('v.FechaVenta', '>=', $request->fecha_inicio);
            }
            if ($request->fecha_fin) {
                $query->where('v.FechaVenta', '<=', $request->fecha_fin . ' 23:59:59');
            }

            $ventas = $query->orderBy('v.FechaVenta', 'desc')
                ->orderBy('v.NumeroFactura', 'desc')
                ->get([
                    'v.NumeroFactura',
                    'v.FechaVenta',
                    'v.TicketDia',
                    'v.ImporteVenta',
                    'v.Observacion',
                    'd.unidades',
                    'd.preciounidades',
                    'd.totalbolivianos',
                    'r.Detalle as producto',
                    'r.Codigo as producto_codigo',
                    'i.Nombre as operador_nombre',
                    'cli.Nombre as cliente_nombre',
                    'cli.CI_NIT as cliente_nit',
                    DB::raw("DATE_FORMAT(v.FechaVenta, '%d/%m/%Y') as fecha")
                ]);

            // Agrupar por factura
            $facturas = [];
            foreach ($ventas as $venta) {
                $key = $venta->NumeroFactura . '_' . $venta->FechaVenta;
                if (!isset($facturas[$key])) {
                    $facturas[$key] = [
                        'numero_factura' => $venta->NumeroFactura,
                        'fecha' => $venta->fecha,
                        'fecha_original' => $venta->FechaVenta,
                        'ticket_dia' => $venta->TicketDia,
                        'importe_total' => (float) $venta->ImporteVenta,
                        'operador_nombre' => $venta->operador_nombre,
                        'cliente_nombre' => $venta->cliente_nombre ?? 'CONSUMIDOR FINAL',
                        'cliente_nit' => $venta->cliente_nit ?? '0',
                        'observacion' => $venta->Observacion,
                        'productos' => [],
                        'total_productos' => 0
                    ];
                }
                $facturas[$key]['productos'][] = [
                    'producto' => $venta->producto,
                    'codigo' => $venta->producto_codigo,
                    'unidades' => (float) $venta->unidades,
                    'precio_unitario' => (float) $venta->preciounidades,
                    'total' => (float) $venta->totalbolivianos
                ];
                $facturas[$key]['total_productos'] += 1;
            }

            // Calcular totales
            $totalVentas = 0;
            $totalUnidades = 0;
            foreach ($facturas as &$factura) {
                $totalVentas += $factura['importe_total'];
                foreach ($factura['productos'] as $prod) {
                    $totalUnidades += $prod['unidades'];
                }
            }

            // Obtener nombre del operador
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $request->operador_id)
                ->first(['i.Nombre']);

            return response()->json([
                'success' => true,
                'operador_nombre' => $operador->Nombre ?? 'Desconocido',
                'facturas' => array_values($facturas),
                'total_ventas' => $totalVentas,
                'total_unidades' => $totalUnidades,
                'total_facturas' => count($facturas)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en getVentasPorOperador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las ventas: ' . $e->getMessage()
            ], 500);
        }
    }
}