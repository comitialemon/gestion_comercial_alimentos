<?php

namespace App\Http\Controllers\Gestion\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ReporteUnidadesVentasController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        return Inertia::render('Gestion/Reportes/ReporteUnidadesVentasSupervisor', [
            'empresa' => $empresa,
            'sucursales' => $sucursales,
            'sucursalId' => $sucursalId,
        ]);
    }

    public function getData(Request $request)
    {
        try {
            $request->validate([
                'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date',
            ]);

            $clienteId = session('cliente_id');
            $sucursalId = $request->sucursal_id;
            $fechaInicio = $request->fecha_inicio;
            $fechaFin = $request->fecha_fin;

            // Obtener todos los operadores
            $operadores = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->pluck('i.Nombre', 'o.IdOperador');

            // 🔥 CONSULTA SIMPLIFICADA - SIN leftJoin innecesarios
            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->join('impuestos_ventas_detalle', 'impuestos_ventas.IdVentas', '=', 'impuestos_ventas_detalle.idventas')
                ->join('inventario_relacion_ventainventario', 'impuestos_ventas_detalle.idrelacionventainventario', '=', 'inventario_relacion_ventainventario.IdDetalleProducto')
                ->where('impuestos_ventas.IdCliente', $clienteId)
                ->where('impuestos_ventas.IdEstado', 1)
                ->where('impuestos_ventas.IdClienteSucursal', $sucursalId);

            if ($fechaInicio && !empty($fechaInicio)) {
                $query->where('impuestos_ventas.FechaVenta', '>=', $fechaInicio);
            }
            if ($fechaFin && !empty($fechaFin)) {
                $query->where('impuestos_ventas.FechaVenta', '<=', $fechaFin . ' 23:59:59');
            }

            $totalRegistros = $query->count();

            // Calcular días del rango
            $diasRango = 0;
            if ($fechaInicio && $fechaFin) {
                $inicio = new \DateTime($fechaInicio);
                $fin = new \DateTime($fechaFin);
                $diasRango = $inicio->diff($fin)->days;
            }

            $esRangoGrande = ($diasRango > 90) || ($totalRegistros > 5000);

            // 🔥 SELECT SIMPLIFICADO - sin descripcion_producto
            $resultados = $query->select([
                    'impuestos_ventas.FechaVenta',
                    DB::raw("DATE_FORMAT(impuestos_ventas.FechaVenta, '%d/%m/%Y') as fecha_formateada"),
                    'impuestos_ventas.NumeroFactura',
                    'inventario_relacion_ventainventario.Detalle as producto',
                    'impuestos_ventas_detalle.unidades',
                    'impuestos_ventas_detalle.preciounidades',
                    'impuestos_ventas_detalle.totalbolivianos',
                    'impuestos_ventas.IdClienteSucursal',
                    'impuestos_ventas.IdOperadorIngresa'
                ])
                ->orderBy('impuestos_ventas.FechaVenta', 'desc')
                ->orderBy('inventario_relacion_ventainventario.Detalle')
                ->orderBy('impuestos_ventas.NumeroFactura')
                ->get();

            // Agrupar resultados
            $reporteAgrupado = [];
            $contadorDetalles = 0;
            
            foreach ($resultados as $row) {
                $fecha = $row->fecha_formateada;
                $producto = $row->producto;
                
                if (!isset($reporteAgrupado[$fecha])) {
                    $reporteAgrupado[$fecha] = [
                        'fecha' => $fecha,
                        'fecha_original' => $row->FechaVenta,
                        'productos' => [],
                        'total_unidades_fecha' => 0,
                        'total_ventas_fecha' => 0
                    ];
                }
                
                if (!isset($reporteAgrupado[$fecha]['productos'][$producto])) {
                    $reporteAgrupado[$fecha]['productos'][$producto] = [
                        'producto' => $producto,
                        'detalles' => [],
                        'total_unidades_producto' => 0,
                        'total_ventas_producto' => 0
                    ];
                }
                
                $operadorNombre = $operadores[$row->IdOperadorIngresa] ?? 'Desconocido';
                
                $detalle = [
                    'numero_factura' => $row->NumeroFactura,
                    'detalle_producto' => $row->producto,
                    'unidades' => (float) $row->unidades,
                    'precio_unitario' => (float) $row->preciounidades,
                    'total_bolivianos' => (float) $row->totalbolivianos,
                    'operador' => $operadorNombre
                ];
                
                $reporteAgrupado[$fecha]['productos'][$producto]['detalles'][] = $detalle;
                $reporteAgrupado[$fecha]['productos'][$producto]['total_unidades_producto'] += (float) $row->unidades;
                $reporteAgrupado[$fecha]['productos'][$producto]['total_ventas_producto'] += (float) $row->totalbolivianos;
                $reporteAgrupado[$fecha]['total_unidades_fecha'] += (float) $row->unidades;
                $reporteAgrupado[$fecha]['total_ventas_fecha'] += (float) $row->totalbolivianos;
                $contadorDetalles++;
            }
            
            foreach ($reporteAgrupado as $fecha => $data) {
                $reporteAgrupado[$fecha]['productos'] = array_values($data['productos']);
            }
            
            $totalUnidadesGeneral = 0;
            $totalVentasGeneral = 0;
            foreach ($reporteAgrupado as $fecha) {
                $totalUnidadesGeneral += $fecha['total_unidades_fecha'];
                $totalVentasGeneral += $fecha['total_ventas_fecha'];
            }

            return response()->json([
                'success' => true,
                'reporte' => array_values($reporteAgrupado),
                'totales' => [
                    'unidades' => $totalUnidadesGeneral,
                    'ventas' => $totalVentasGeneral,
                    'fechas' => count($reporteAgrupado),
                    'productos' => $resultados->groupBy('producto')->count(),
                    'detalles' => $contadorDetalles
                ],
                'advertencia' => $esRangoGrande ? [
                    'mensaje' => "El reporte tiene {$totalRegistros} registros en {$diasRango} días. La carga puede ser lenta.",
                    'total_registros' => $totalRegistros,
                    'dias_rango' => $diasRango
                ] : null
            ]);

        } catch (\Exception $e) {
            Log::error('Error en ReporteUnidadesVentas::getData', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}