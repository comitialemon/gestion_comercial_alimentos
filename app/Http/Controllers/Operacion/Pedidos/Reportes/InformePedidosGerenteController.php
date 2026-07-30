<?php

namespace App\Http\Controllers\Operacion\Pedidos\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\Pedido;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InformePedidosGerenteController extends Controller
{
    /**
     * Mostrar el formulario de filtros del informe
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // Obtener filtros de la URL
        $fechaInicio = $request->get('fecha_inicio', Carbon::now('America/La_Paz')->format('Y-m-d'));
        $fechaFin = $request->get('fecha_fin', Carbon::now('America/La_Paz')->addDays(30)->format('Y-m-d'));
        $sucursalId = $request->get('sucursal_id', null);
        $search = $request->get('search', '');
        
        // ✅ Solo ejecutar la consulta si hay fechas
        $datos = null;
        $totales = null;
        
        if ($fechaInicio && $fechaFin) {
            $datos = $this->obtenerDatos($clienteId, $fechaInicio, $fechaFin, $sucursalId, $search);
            $totales = $this->calcularTotales($datos);
        }
        
        // ✅ Sucursales para el filtro
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('NumeroSucursal')
            ->get(['IdClienteSucursal as id', 'NumeroSucursal', 'Nombre'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'texto' => $item->NumeroSucursal . ' - ' . $item->Nombre,
                ];
            });

        return Inertia::render('Operacion/Pedidos/Reportes/InformePedidosGerente', [
            'datos' => $datos,
            'totales' => $totales,
            'sucursales' => $sucursales,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'sucursal_id' => $sucursalId,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Obtener los datos del informe
     */
    private function obtenerDatos($clienteId, $fechaInicio, $fechaFin, $sucursalId = null, $search = '')
    {
        // ✅ CONSULTA PRINCIPAL
        $pedidos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_ventas_pedidos as p')
            ->select([
                'p.IdPedidos',
                'p.FechaRealiza',
                'p.FechaDelPedido',
                'p.IdProducto',
                'p.Unidades',
                'p.IdCliente',
                'p.IdSucursal',
                'p.idOperador',
                // Subconsulta para Descripción del producto
                DB::raw('(SELECT i.Descripcion FROM inventario_productodetalle i WHERE i.IdProducto = p.IdProducto) as DestalleProducto'),
                // Subconsulta para Código del producto
                DB::raw('(SELECT i.Codigo FROM inventario_productodetalle i WHERE i.IdProducto = p.IdProducto) as CodigoProducto'),
                // Subconsulta para Nombre de sucursal
                DB::raw('(SELECT s.Nombre FROM todos_cliente_sucursal s WHERE s.IdClienteSucursal = p.IdSucursal) as NombreSucursal'),
                // Subconsulta para Operador
                DB::raw('(SELECT id.Nombre FROM todos_identificador id INNER JOIN todos_operador o ON id.IdIdentificador = o.IdIdentificador WHERE o.IdOperador = p.idOperador) as Operador'),
                // NombreSolicitud (igual que Scriptcase)
                DB::raw('IF(
                    (SELECT s.Nombre FROM todos_cliente_sucursal s WHERE s.IdClienteSucursal = p.IdSucursal) = "Mayorista",
                    (SELECT id.Nombre FROM todos_identificador id INNER JOIN todos_operador o ON id.IdIdentificador = o.IdIdentificador WHERE o.IdOperador = p.idOperador),
                    (SELECT s.Nombre FROM todos_cliente_sucursal s WHERE s.IdClienteSucursal = p.IdSucursal)
                ) as NombreSolicitud')
            ])
            ->where('p.IdCliente', $clienteId)
            ->whereDate('p.FechaDelPedido', '>=', $fechaInicio)
            ->whereDate('p.FechaDelPedido', '<=', $fechaFin)
            ->when($sucursalId, function($query, $sucursalId) {
                return $query->where('p.IdSucursal', $sucursalId);
            })
            ->when($search, function($query, $search) {
                return $query->where(function($q) use ($search) {
                    $q->where('DestalleProducto', 'LIKE', "%{$search}%")
                      ->orWhere('p.IdProducto', 'LIKE', "%{$search}%")
                      ->orWhere('NombreSucursal', 'LIKE', "%{$search}%")
                      ->orWhere('CodigoProducto', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('p.IdSucursal')
            ->orderBy('p.IdProducto')
            ->orderBy('p.FechaDelPedido')
            ->get();

        // ✅ AGRUPAR POR SUCURSAL → PRODUCTO → PEDIDOS
        $datosAgrupados = $pedidos->groupBy('IdSucursal')->map(function($sucursalItems, $sucursalId) {
            $sucursalData = $sucursalItems->first();
            
            // Obtener sucursal y operador (si es Mayorista)
            $nombreSucursal = $sucursalData->NombreSucursal ?? '-';
            $operador = $sucursalData->Operador ?? '-';
            $nombreSolicitud = $sucursalData->NombreSolicitud ?? '-';
            
            // ✅ AGRUPAR POR PRODUCTO dentro de la sucursal
            $productos = $sucursalItems->groupBy('IdProducto')->map(function($productoItems, $productoId) {
                $productoData = $productoItems->first();
                
                return [
                    'IdProducto' => $productoId,
                    'CodigoProducto' => $productoData->CodigoProducto ?? '-',
                    'DestalleProducto' => $productoData->DestalleProducto ?? '-',
                    'total_unidades' => $productoItems->sum('Unidades'),
                    'total_pedidos' => $productoItems->count(),
                    'pedidos' => $productoItems->map(function($item) {
                        return [
                            'IdPedidos' => $item->IdPedidos,
                            'FechaRealiza' => Carbon::parse($item->FechaRealiza)->format('d/m/Y H:i:s'),
                            'FechaDelPedido' => Carbon::parse($item->FechaDelPedido)->format('d/m/Y'),
                            'Unidades' => number_format($item->Unidades, 2, ',', '.'),
                        ];
                    })->values(),
                ];
            })->values();
            
            return [
                'IdSucursal' => $sucursalId,
                'NombreSucursal' => $nombreSucursal,
                'Operador' => $operador,
                'NombreSolicitud' => $nombreSolicitud,
                'total_unidades' => $sucursalItems->sum('Unidades'),
                'total_pedidos' => $sucursalItems->count(),
                'total_productos' => $productos->count(),
                'productos' => $productos,
            ];
        })->values();

        return $datosAgrupados;
    }

    /**
     * Calcular totales generales
     */
    private function calcularTotales($datos)
    {
        if (!$datos) {
            return [
                'total_sucursales' => 0,
                'total_productos' => 0,
                'total_pedidos' => 0,
                'total_unidades' => 0,
            ];
        }

        return [
            'total_sucursales' => $datos->count(),
            'total_productos' => $datos->sum('total_productos'),
            'total_pedidos' => $datos->sum('total_pedidos'),
            'total_unidades' => $datos->sum('total_unidades'),
        ];
    }

    /**
     * Exportar a Excel
     */
    public function exportar(Request $request)
    {
        // Implementar con Laravel Excel
    }

    /**
     * Generar PDF
     */
    public function pdf(Request $request)
    {
        // Implementar con DomPDF
    }
}