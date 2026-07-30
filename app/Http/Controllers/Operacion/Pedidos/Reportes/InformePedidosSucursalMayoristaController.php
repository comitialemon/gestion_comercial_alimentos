<?php

namespace App\Http\Controllers\Operacion\Pedidos\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class InformePedidosSucursalMayoristaController extends Controller
{
    /**
     * Mostrar el informe de pedidos para sucursal mayorista
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        // Obtener datos de la sucursal
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre', 'NumeroSucursal']);
        
        // Obtener datos del operador
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);
        
        // Obtener filtros
        $fechaInicio = $request->get('fecha_inicio', Carbon::now('America/La_Paz')->format('Y-m-d'));
        $fechaFin = $request->get('fecha_fin', Carbon::now('America/La_Paz')->addDays(30)->format('Y-m-d'));
        $search = $request->get('search', '');
        
        // =============================================
        // CONSULTA PRINCIPAL
        // =============================================
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
                DB::raw('(SELECT ip.Descripcion FROM inventario_productodetalle ip WHERE ip.IdProducto = p.IdProducto) as DestalleProducto'),
                // Subconsulta para Código del producto
                DB::raw('(SELECT ip.Codigo FROM inventario_productodetalle ip WHERE ip.IdProducto = p.IdProducto) as CodigoProducto'),
                // Subconsulta para Nombre de sucursal
                DB::raw('(SELECT cs.Nombre FROM todos_cliente_sucursal cs WHERE cs.IdClienteSucursal = p.IdSucursal) as NombreSucursal'),
                // Subconsulta para Operador
                DB::raw('(SELECT id.Nombre FROM todos_identificador id INNER JOIN todos_operador o ON id.IdIdentificador = o.IdIdentificador WHERE o.IdOperador = p.idOperador) as Operador')
            ])
            ->where('p.IdCliente', $clienteId)
            ->where('p.IdSucursal', $sucursalId)
            ->where('p.idOperador', $operadorId)
            ->whereDate('p.FechaDelPedido', '>=', $fechaInicio)
            ->whereDate('p.FechaDelPedido', '<=', $fechaFin)
            ->when($search, function($query, $search) {
                return $query->where(function($q) use ($search) {
                    $q->where('DestalleProducto', 'LIKE', "%{$search}%")
                      ->orWhere('p.IdProducto', 'LIKE', "%{$search}%")
                      ->orWhere('CodigoProducto', 'LIKE', "%{$search}%")
                      ->orWhere('Operador', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('p.FechaDelPedido', 'desc')
            ->orderBy('p.IdPedidos', 'desc')
            ->get();
        
        // =============================================
        // CALCULAR TOTALES
        // =============================================
        $totales = [
            'total_pedidos' => $pedidos->count(),
            'total_unidades' => $pedidos->sum('Unidades'),
            'total_productos' => $pedidos->groupBy('IdProducto')->count(),
        ];
        
        // =============================================
        // AGRUPAR POR FECHA PARA EL RESUMEN
        // =============================================
        $resumenPorFecha = $pedidos->groupBy(function($item) {
            return Carbon::parse($item->FechaDelPedido)->format('Y-m-d');
        })->map(function($items, $fecha) {
            return [
                'fecha' => $fecha,
                'fecha_formateada' => Carbon::parse($fecha)->format('d/m/Y'),
                'total_pedidos' => $items->count(),
                'total_unidades' => $items->sum('Unidades'),
            ];
        })->values();
        
        return Inertia::render('Operacion/Pedidos/Reportes/InformePedidosSucursalMayorista', [
            'pedidos' => $pedidos,
            'sucursal' => $sucursal,
            'operador' => $operador,
            'totales' => $totales,
            'resumenPorFecha' => $resumenPorFecha,
            'filtros' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'search' => $search,
            ],
        ]);
    }
    
    /**
     * Exportar a Excel
     */
    public function exportarExcel(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        $fechaInicio = $request->get('fecha_inicio', Carbon::now('America/La_Paz')->format('Y-m-d'));
        $fechaFin = $request->get('fecha_fin', Carbon::now('America/La_Paz')->addDays(30)->format('Y-m-d'));
        $search = $request->get('search', '');
        
        // Obtener datos
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
                DB::raw('(SELECT ip.Descripcion FROM inventario_productodetalle ip WHERE ip.IdProducto = p.IdProducto) as DestalleProducto'),
                DB::raw('(SELECT ip.Codigo FROM inventario_productodetalle ip WHERE ip.IdProducto = p.IdProducto) as CodigoProducto'),
                DB::raw('(SELECT cs.Nombre FROM todos_cliente_sucursal cs WHERE cs.IdClienteSucursal = p.IdSucursal) as NombreSucursal'),
                DB::raw('(SELECT id.Nombre FROM todos_identificador id INNER JOIN todos_operador o ON id.IdIdentificador = o.IdIdentificador WHERE o.IdOperador = p.idOperador) as Operador')
            ])
            ->where('p.IdCliente', $clienteId)
            ->where('p.IdSucursal', $sucursalId)
            ->where('p.idOperador', $operadorId)
            ->whereDate('p.FechaDelPedido', '>=', $fechaInicio)
            ->whereDate('p.FechaDelPedido', '<=', $fechaFin)
            ->when($search, function($query, $search) {
                return $query->where(function($q) use ($search) {
                    $q->where('DestalleProducto', 'LIKE', "%{$search}%")
                      ->orWhere('p.IdProducto', 'LIKE', "%{$search}%")
                      ->orWhere('CodigoProducto', 'LIKE', "%{$search}%");
                });
            })
            ->orderBy('p.FechaDelPedido', 'desc')
            ->get();
        
        // =============================================
        // GENERAR EXCEL
        // =============================================
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        
        $fila = 1;
        
        // Título
        $worksheet->setCellValue('A' . $fila, 'INFORME DE PEDIDOS - SUCURSAL MAYORISTA');
        $worksheet->mergeCells('A' . $fila . ':G' . $fila);
        $worksheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(14);
        $worksheet->getStyle('A' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila++;
        
        // Información
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);
        
        $worksheet->setCellValue('A' . $fila, 'Sucursal: ' . ($sucursal->Nombre ?? '-'));
        $worksheet->mergeCells('A' . $fila . ':G' . $fila);
        $fila++;
        
        $worksheet->setCellValue('A' . $fila, 'Fecha Inicio: ' . Carbon::parse($fechaInicio)->format('d/m/Y') . ' - Fecha Fin: ' . Carbon::parse($fechaFin)->format('d/m/Y'));
        $worksheet->mergeCells('A' . $fila . ':G' . $fila);
        $fila++;
        $fila++;
        
        // Encabezados
        $headers = ['Nº', 'Fecha Realiza', 'Fecha Pedido', 'Producto', 'Código', 'Unidades', 'Sucursal'];
        $col = 'A';
        foreach ($headers as $header) {
            $worksheet->setCellValue($col . $fila, $header);
            $worksheet->getStyle($col . $fila)->getFont()->setBold(true);
            $worksheet->getStyle($col . $fila)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');
            $col++;
        }
        $fila++;
        
        // Datos
        $numero = 1;
        foreach ($pedidos as $pedido) {
            $worksheet->setCellValue('A' . $fila, $numero);
            $worksheet->setCellValue('B' . $fila, Carbon::parse($pedido->FechaRealiza)->format('d/m/Y H:i:s'));
            $worksheet->setCellValue('C' . $fila, Carbon::parse($pedido->FechaDelPedido)->format('d/m/Y'));
            $worksheet->setCellValue('D' . $fila, $pedido->DestalleProducto ?? '-');
            $worksheet->setCellValue('E' . $fila, $pedido->CodigoProducto ?? '-');
            $worksheet->setCellValue('F' . $fila, $pedido->Unidades);
            $worksheet->setCellValue('G' . $fila, $pedido->NombreSucursal ?? '-');
            $fila++;
            $numero++;
        }
        
        // Autoajustar columnas
        foreach (range('A', 'G') as $col) {
            $worksheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Descargar
        $nombreArchivo = 'Pedidos_SucursalMayorista_' . date('Y-m-d') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        $writer->save('php://output');
        exit();
    }
}