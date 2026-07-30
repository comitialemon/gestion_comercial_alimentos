<?php

namespace App\Http\Controllers\Operacion\Pedidos\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\Pedido;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InformePedidosSupervisorController extends Controller
{
    /**
     * Mostrar el informe de pedidos para supervisor
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // Obtener filtros
        $fechaInicio = $request->get('fecha_inicio', Carbon::now('America/La_Paz')->format('Y-m-d'));
        $fechaFin = $request->get('fecha_fin', Carbon::now('America/La_Paz')->addDays(7)->format('Y-m-d'));
        $sucursalId = $request->get('sucursal_id', null);
        $search = $request->get('search', '');

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
                      ->orWhere('Operador', 'LIKE', "%{$search}%")
                      ->orWhere('CodigoProducto', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('p.FechaDelPedido')
            ->orderBy('p.IdProducto')
            ->orderBy('p.IdSucursal')
            ->get();

        // ✅ AGRUPAR POR PRODUCTO
        $productosAgrupados = $pedidos->groupBy('IdProducto')->map(function($items, $productoId) {
            $primerItem = $items->first();
            
            // Obtener el detalle del producto
            $detalleProducto = $primerItem->DestalleProducto ?? '-';
            $codigoProducto = $primerItem->CodigoProducto ?? '-';
            
            // Obtener fechas únicas
            $fechas = $items->pluck('FechaDelPedido')->unique()->map(function($fecha) {
                return Carbon::parse($fecha)->format('d/m/Y');
            })->implode(', ');
            
            // Agrupar por sucursal dentro del producto
            $sucursales = $items->groupBy('IdSucursal')->map(function($sucursalItems, $sucursalId) {
                $sucursalData = $sucursalItems->first();
                return [
                    'IdSucursal' => $sucursalId,
                    'NombreSucursal' => $sucursalData->NombreSucursal ?? '-',
                    'Operador' => $sucursalData->Operador ?? '-',
                    'NombreSolicitud' => $sucursalData->NombreSolicitud ?? '-',
                    'pedidos' => $sucursalItems->map(function($item) {
                        return [
                            'IdPedidos' => $item->IdPedidos,
                            'FechaRealiza' => Carbon::parse($item->FechaRealiza)->format('d/m/Y H:i:s'),
                            'FechaDelPedido' => Carbon::parse($item->FechaDelPedido)->format('d/m/Y'),
                            'Unidades' => number_format($item->Unidades, 2, ',', '.'),
                            'IdProducto' => $item->IdProducto,
                        ];
                    }),
                    'total_unidades' => $sucursalItems->sum('Unidades'),
                ];
            })->values();

            // Total de unidades del producto
            $totalUnidades = $items->sum('Unidades');

            return [
                'IdProducto' => $productoId,
                'CodigoProducto' => $codigoProducto,
                'DestalleProducto' => $detalleProducto,
                'Fechas' => $fechas,
                'sucursales' => $sucursales,
                'total_unidades' => $totalUnidades,
                'total_pedidos' => $items->count(),
                'total_sucursales' => $sucursales->count(),
            ];
        })->values();

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

        // ✅ Totales generales
        $totales = [
            'total_productos' => $productosAgrupados->count(),
            'total_pedidos' => $pedidos->count(),
            'total_unidades' => $pedidos->sum('Unidades'),
            'total_sucursales' => $pedidos->groupBy('IdSucursal')->count(),
        ];

        return Inertia::render('Operacion/Pedidos/Reportes/InformePedidosSupervisor', [
            'productosAgrupados' => $productosAgrupados,
            'sucursales' => $sucursales,
            'totales' => $totales,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'sucursal_id' => $sucursalId,
                'search' => $search,
            ],
        ]);
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