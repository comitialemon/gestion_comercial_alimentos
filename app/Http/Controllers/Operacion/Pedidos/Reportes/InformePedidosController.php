<?php

namespace App\Http\Controllers\Operacion\Pedidos\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class InformePedidosController extends Controller
{
    /**
     * Mostrar el formulario con selector de fecha y vista previa
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        // Obtener datos del operador
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);
        
        // Obtener datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);
        
        // Fecha seleccionada (por defecto hoy)
        $fechaSeleccionada = $request->get('fecha', Carbon::now('America/La_Paz')->format('Y-m-d'));
        
        // Datos del informe
        $datos = null;
        $grupos = null;
        $solicitantes = null;
        $totalProductos = 0;
        
        if ($fechaSeleccionada) {
            $datos = $this->obtenerDatosInforme($clienteId, $fechaSeleccionada);
            $grupos = $datos['grupos'] ?? [];
            $solicitantes = $datos['solicitantes'] ?? [];
            $totalProductos = $datos['total_productos'] ?? 0;
        }
        
        return Inertia::render('Operacion/Pedidos/Reportes/InformePedidos', [
            'empresa' => $empresa,
            'operador' => $operador,
            'fechaSeleccionada' => $fechaSeleccionada,
            'grupos' => $grupos,
            'solicitantes' => $solicitantes,
            'totalProductos' => $totalProductos,
        ]);
    }
    
    /**
     * Obtener datos del informe para una fecha específica
     */
    private function obtenerDatosInforme($clienteId, $fecha)
    {
        // =============================================
        // 1. OBTENER PRODUCTOS POR FECHA AGRUPADOS POR IdGrupoAnalisis
        // =============================================
        $productosQuery = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_ventas_pedidos as p')
            ->join('inventario_productodetalle as ip', 'p.IdProducto', '=', 'ip.IdProducto')
            ->select([
                'p.FechaDelPedido',
                'ip.IdGrupoAnalisis',
                'ip.IdProducto',
                'ip.Descripcion',
                'ip.OrdenInformes',
                DB::raw('SUM(p.Unidades) as total_unidades')
            ])
            ->where('p.IdCliente', $clienteId)
            ->whereDate('p.FechaDelPedido', $fecha)
            ->groupBy('p.FechaDelPedido', 'ip.IdGrupoAnalisis', 'ip.IdProducto', 'ip.Descripcion', 'ip.OrdenInformes')
            ->orderBy('ip.IdGrupoAnalisis')
            ->orderBy('ip.OrdenInformes')
            ->get();
        
        // =============================================
        // 2. AGRUPAR POR IdGrupoAnalisis
        // =============================================
        $gruposMap = [];
        $todosProductos = [];
        
        foreach ($productosQuery as $producto) {
            $idGrupo = $producto->IdGrupoAnalisis ?? 0;
            $nombreGrupo = 'Grupo ' . $idGrupo;
            
            // Obtener el nombre del grupo desde inventario_productogrupoanalisis
            $grupoNombre = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productogrupoanalisis')
                ->where('IdGrupoAnalisis', $idGrupo)
                ->where('IdCliente', $clienteId)
                ->value('Grupo');
            
            if ($grupoNombre) {
                $nombreGrupo = $grupoNombre;
            }
            
            if (!isset($gruposMap[$idGrupo])) {
                $gruposMap[$idGrupo] = [
                    'IdGrupoAnalisis' => $idGrupo,
                    'Grupo' => $nombreGrupo,
                    'productos' => []
                ];
            }
            
            $gruposMap[$idGrupo]['productos'][] = [
                'IdProducto' => $producto->IdProducto,
                'Descripcion' => $producto->Descripcion,
                'OrdenInformes' => $producto->OrdenInformes ?? 0,
                'total_unidades' => $producto->total_unidades,
            ];
            
            $todosProductos[] = $producto->IdProducto;
        }
        
        // Convertir a array
        $grupos = array_values($gruposMap);
        
        // =============================================
        // 3. OBTENER SOLICITANTES (Sucursales/Operadores)
        // =============================================
        $solicitantesQuery = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_ventas_pedidos as p')
            ->join('todos_cliente_sucursal as cs', 'p.IdSucursal', '=', 'cs.IdClienteSucursal')
            ->select([
                'p.IdSucursal',
                'p.idOperador',
                'cs.Nombre as NombreSucursal'
            ])
            ->where('p.IdCliente', $clienteId)
            ->whereDate('p.FechaDelPedido', $fecha)
            ->groupBy('p.IdSucursal', 'p.idOperador', 'cs.Nombre')
            ->orderBy('cs.Nombre')
            ->get();
        
        $solicitantes = [];
        foreach ($solicitantesQuery as $sol) {
            $nombreSolicitante = '';
            $idSucursal = $sol->IdSucursal;
            $idOperador = $sol->idOperador;
            
            // Lógica igual que Scriptcase
            if ($idSucursal == 6) {
                // Sucursal 6: Mostrar nombre del operador
                $operador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_operador')
                    ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                    ->where('todos_operador.IdOperador', $idOperador)
                    ->first(['todos_identificador.Nombre']);
                $nombreSolicitante = $operador->Nombre ?? 'Sin nombre';
            } elseif ($idSucursal == 88 || $idSucursal == 89) {
                // Sucursales 88 y 89: Mostrar "Sucursal - Operador"
                $operador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_operador')
                    ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                    ->where('todos_operador.IdOperador', $idOperador)
                    ->first(['todos_identificador.Nombre']);
                $nombreOperador = $operador->Nombre ?? 'Sin nombre';
                $nombreSolicitante = $sol->NombreSucursal . ' - ' . $nombreOperador;
            } else {
                // Otras sucursales: Mostrar solo nombre de sucursal
                $nombreSolicitante = $sol->NombreSucursal;
            }
            
            $solicitantes[] = [
                'IdSucursal' => $idSucursal,
                'IdOperador' => $idOperador,
                'Solicitante' => $nombreSolicitante,
                'NombreSucursal' => $sol->NombreSucursal,
            ];
        }
        
        // =============================================
        // 4. CALCULAR UNIDADES POR SOLICITANTE Y PRODUCTO
        // =============================================
        foreach ($grupos as &$grupo) {
            foreach ($grupo['productos'] as &$producto) {
                $producto['unidades_por_solicitante'] = [];
                
                foreach ($solicitantes as $solicitante) {
                    $key = $solicitante['IdSucursal'] . '_' . $solicitante['IdOperador'];
                    
                    $unidades = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('operacion_ventas_pedidos')
                        ->where('IdCliente', $clienteId)
                        ->whereDate('FechaDelPedido', $fecha)
                        ->where('IdSucursal', $solicitante['IdSucursal'])
                        ->where('IdProducto', $producto['IdProducto'])
                        ->where('idOperador', $solicitante['IdOperador'])
                        ->sum('Unidades');
                    
                    $producto['unidades_por_solicitante'][$key] = $unidades;
                }
            }
        }
        
        return [
            'grupos' => $grupos,
            'solicitantes' => $solicitantes,
            'total_productos' => count($todosProductos),
        ];
    }
    
    /**
     * Exportar a PDF (usando TCPDF)
     */
    public function exportarPdf(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);
        
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        $fecha = $request->fecha;
        
        // Obtener datos
        $datos = $this->obtenerDatosInforme($clienteId, $fecha);
        $grupos = $datos['grupos'] ?? [];
        $solicitantes = $datos['solicitantes'] ?? [];
        
        // Datos de cabecera
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);
        
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);
        
        // =============================================
        // GENERAR PDF CON TCPDF
        // =============================================
        $pdf = new \TCPDF('L', 'mm', 'Legal', true, 'UTF-8', false);
        $pdf->SetCreator('Sistema');
        $pdf->SetAuthor('Sistema');
        $pdf->SetTitle('Informe de Pedidos');
        $pdf->SetSubject('Pedidos de Productos');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        
        $fechaFormateada = Carbon::parse($fecha)->format('d/m/Y');
        $fechaHoraImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i:s');
        $nombreOperador = $operador->nombre ?? 'Sin operador';
        $nombreEmpresa = $empresa->Nombre ?? '';
        
        // Configuración de columnas
        $maxColumnasPorPagina = 12;
        $anchoColumnaNumero = 3;
        $anchoColumnaSolicitante = 20;
        $anchoColumnaProducto = (100 - $anchoColumnaNumero - $anchoColumnaSolicitante) / $maxColumnasPorPagina;
        
        foreach ($grupos as $grupo) {
            $productos = $grupo['productos'];
            $nombreGrupo = $grupo['Grupo'];
            
            // Dividir productos en chunks
            $chunks = array_chunk($productos, $maxColumnasPorPagina);
            $totalPaginas = count($chunks);
            $paginaNumero = 1;
            
            foreach ($chunks as $productosPagina) {
                $pdf->AddPage();
                
                // =============================================
                // HTML DEL PDF
                // =============================================
                $html = '
                <style>
                    .titulo-principal { 
                        font-size: 16px; 
                        font-weight: bold; 
                        text-align: center; 
                        margin: 0;
                        padding: 0;
                        line-height: 1;
                    }
                    .grupo { 
                        font-size: 12px; 
                        font-weight: bold; 
                        text-align: center;
                        margin: 0;
                        padding: 0;
                        line-height: 1;
                    }
                    .info-table {
                        width: 100%;
                        font-size: 10px;
                        margin: 5px 0;
                    }
                    .info-table td {
                        vertical-align: top;
                        padding: 2px;
                        border: none;
                    }
                    .left-align { text-align: left; }
                    .right-align { text-align: right; }
                    .pagina-info {
                        font-size: 10px;
                        text-align: right;
                        margin: 0;
                        padding: 0;
                    }
                    .data-table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        font-size: 7px;
                        margin-top: 3px;
                    }
                    .data-table th { 
                        background-color: #f2f2f2; 
                        border: 1px solid #ddd; 
                        padding: 3px; 
                        text-align: center;
                        font-weight: bold;
                    }
                    .data-table td { 
                        border: 1px solid #ddd; 
                        padding: 3px; 
                    }
                    .numero-col {
                        width: ' . $anchoColumnaNumero . '%;
                        text-align: center;
                        font-weight: bold;
                        font-size: 6px;
                    }
                    .solicitante { 
                        text-align: left; 
                        font-weight: bold;
                        width: ' . $anchoColumnaSolicitante . '%;
                    }
                    .producto-col {
                        width: ' . $anchoColumnaProducto . '%;
                        text-align: right;
                    }
                    .total { 
                        background-color: #f2f2f2; 
                        font-weight: bold;
                    }
                </style>
                
                <div style="margin-bottom: 3px;">
                    <div class="titulo-principal">PEDIDO DE PRODUCTOS</div>
                    <div class="grupo">' . utf8_encode($nombreGrupo) . ' - Página ' . $paginaNumero . ' de ' . $totalPaginas . '</div>
                    
                    <table class="info-table">
                        <tr>
                            <td class="left-align" width="50%">
                                <strong>Fecha del Pedido:</strong> ' . $fechaFormateada . '<br/>
                                <strong>Impreso el:</strong> ' . $fechaHoraImpresion . '
                            </td>
                            <td class="right-align" width="50%">
                                <strong>Operador:</strong> ' . utf8_encode($nombreOperador) . '
                            </td>
                        </tr>
                    </table>
                </div>
                ';
                
                // Tabla de datos
                $html .= '<table class="data-table">
                            <tr>
                                <th class="numero-col">Nº</th>
                                <th class="solicitante">Solicitante</th>';
                
                foreach ($productosPagina as $producto) {
                    $html .= '<th class="producto-col">' . ($producto['OrdenInformes'] ?? '') . ':' . utf8_encode($producto['Descripcion']) . '</th>';
                }
                
                $html .= '</tr>';
                
                // Totales por página
                $totalesPagina = array_fill(0, count($productosPagina), 0);
                $numeroFila = 1;
                
                foreach ($solicitantes as $solicitante) {
                    $html .= '<tr>';
                    $html .= '<td class="numero-col">' . $numeroFila . '</td>';
                    $html .= '<td class="solicitante">' . utf8_encode($solicitante['Solicitante']) . '</td>';
                    
                    foreach ($productosPagina as $colIndex => $producto) {
                        $key = $solicitante['IdSucursal'] . '_' . $solicitante['IdOperador'];
                        $unidades = $producto['unidades_por_solicitante'][$key] ?? 0;
                        
                        $html .= '<td class="producto-col">' . number_format($unidades, 2) . '</td>';
                        $totalesPagina[$colIndex] += $unidades;
                    }
                    
                    $html .= '</tr>';
                    $numeroFila++;
                }
                
                // Fila de totales
                $html .= '<tr class="total">';
                $html .= '<td class="numero-col"></td>';
                $html .= '<td class="solicitante">TOTALES</td>';
                foreach ($totalesPagina as $total) {
                    $html .= '<td class="producto-col">' . number_format($total, 2) . '</td>';
                }
                $html .= '</tr>';
                
                $html .= '</table>';
                
                // Información de productos mostrados
                $inicioCol = (($paginaNumero - 1) * $maxColumnasPorPagina) + 1;
                $finCol = min(($paginaNumero * $maxColumnasPorPagina), count($productos));
                $html .= '<div class="pagina-info">Mostrando productos ' . $inicioCol . ' a ' . $finCol . ' de ' . count($productos) . '</div>';
                
                $pdf->writeHTML($html, true, false, true, false, '');
                $paginaNumero++;
            }
        }
        
        // Salida del PDF
        $nombreArchivo = 'Pedidos_' . $fecha . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit();
    }
    
    /**
     * Exportar a Excel
     */
    public function exportarExcel(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
        ]);
        
        $clienteId = session('cliente_id');
        $fecha = $request->fecha;
        
        // Obtener datos
        $datos = $this->obtenerDatosInforme($clienteId, $fecha);
        $grupos = $datos['grupos'] ?? [];
        $solicitantes = $datos['solicitantes'] ?? [];
        
        // =============================================
        // GENERAR EXCEL
        // =============================================
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        
        $fila = 1;
        
        // Título
        $worksheet->setCellValue('A' . $fila, 'PEDIDO DE PRODUCTOS');
        $worksheet->mergeCells('A' . $fila . ':Z' . $fila);
        $worksheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(14);
        $worksheet->getStyle('A' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila++;
        
        // Fecha
        $worksheet->setCellValue('A' . $fila, 'Fecha: ' . Carbon::parse($fecha)->format('d/m/Y'));
        $worksheet->mergeCells('A' . $fila . ':Z' . $fila);
        $fila++;
        $fila++;
        
        foreach ($grupos as $grupo) {
            $productos = $grupo['productos'];
            $nombreGrupo = $grupo['Grupo'];
            
            // Grupo
            $worksheet->setCellValue('A' . $fila, $nombreGrupo);
            $worksheet->mergeCells('A' . $fila . ':Z' . $fila);
            $worksheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(12);
            $worksheet->getStyle('A' . $fila)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
            $fila++;
            
            // Encabezados
            $worksheet->setCellValue('A' . $fila, 'Nº');
            $worksheet->setCellValue('B' . $fila, 'Solicitante');
            
            $col = 3;
            foreach ($productos as $producto) {
                $worksheet->setCellValue($this->getColumnLetter($col) . $fila, ($producto['OrdenInformes'] ?? '') . ':' . $producto['Descripcion']);
                $col++;
            }
            
            $worksheet->getStyle('A' . $fila . ':' . $this->getColumnLetter($col - 1) . $fila)->getFont()->setBold(true);
            $worksheet->getStyle('A' . $fila . ':' . $this->getColumnLetter($col - 1) . $fila)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D9E1F2');
            $fila++;
            
            // Datos
            $numeroFila = 1;
            foreach ($solicitantes as $solicitante) {
                $worksheet->setCellValue('A' . $fila, $numeroFila);
                $worksheet->setCellValue('B' . $fila, $solicitante['Solicitante']);
                
                $col = 3;
                foreach ($productos as $producto) {
                    $key = $solicitante['IdSucursal'] . '_' . $solicitante['IdOperador'];
                    $unidades = $producto['unidades_por_solicitante'][$key] ?? 0;
                    
                    $worksheet->setCellValue($this->getColumnLetter($col) . $fila, $unidades);
                    $col++;
                }
                
                $fila++;
                $numeroFila++;
            }
            
            // Totales
            $worksheet->setCellValue('A' . $fila, '');
            $worksheet->setCellValue('B' . $fila, 'TOTALES');
            $worksheet->getStyle('B' . $fila)->getFont()->setBold(true);
            
            $col = 3;
            foreach ($productos as $producto) {
                $total = 0;
                foreach ($solicitantes as $solicitante) {
                    $key = $solicitante['IdSucursal'] . '_' . $solicitante['IdOperador'];
                    $total += $producto['unidades_por_solicitante'][$key] ?? 0;
                }
                
                $worksheet->setCellValue($this->getColumnLetter($col) . $fila, $total);
                $worksheet->getStyle($this->getColumnLetter($col) . $fila)->getFont()->setBold(true);
                $col++;
            }
            
            $worksheet->getStyle('A' . $fila . ':' . $this->getColumnLetter($col - 1) . $fila)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');
            $fila++;
            $fila++;
        }
        
        // Autoajustar columnas
        for ($col = 1; $col <= 26; $col++) {
            $worksheet->getColumnDimension($this->getColumnLetter($col))->setAutoSize(true);
        }
        
        // Descargar
        $nombreArchivo = 'Pedidos_' . $fecha . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        $writer->save('php://output');
        exit();
    }
    
    /**
     * Obtener letra de columna para Excel
     */
    private function getColumnLetter($index)
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)) . $letters;
            $index = floor($index / 26);
        }
        return $letters;
    }
}