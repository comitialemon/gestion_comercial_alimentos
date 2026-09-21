<?php

namespace App\Http\Controllers\Operacion\Pedidos\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MinimosPorClienteController extends Controller
{
    /**
     * ✅ VISTA PRINCIPAL
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $filtros = [
            'identificador_id' => $request->get('identificador_id', ''),
            'sucursal_id' => $request->get('sucursal_id', $sucursalId),
            'id_grupo' => $request->get('id_grupo', ''),
            'solo_sin_precio' => filter_var($request->get('solo_sin_precio', false), FILTER_VALIDATE_BOOLEAN),
            'solo_sin_minimo' => filter_var($request->get('solo_sin_minimo', false), FILTER_VALIDATE_BOOLEAN),
        ];

        $datos = $this->obtenerDatos($clienteId, $filtros);

        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        $grupos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productogrupoanalisis')
            ->where('IdCliente', $clienteId)
            ->orderBy('Grupo')
            ->get(['IdGrupoAnalisis as id', 'Grupo as nombre']);

        return Inertia::render('Operacion/Pedidos/Reportes/MinimosPorCliente', [
            'clientes' => $datos['clientes'],
            'sucursales' => $sucursales,
            'grupos' => $grupos,
            'filtros' => $filtros,
            'contadores' => $datos['contadores'],
        ]);
    }

    /**
     * ✅ BUSCAR CLIENTES PARA AUTOCOMPLETE
     */
    public function buscarClientes(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = $request->get('sucursal_id', session('cliente_sucursal_id'));
        $termino = $request->get('q', '');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_cliente as cc')
            ->join('todos_identificador as i', 'cc.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('cc.IdCliente', $clienteId)
            ->where('cc.ActivoInactivo', 1);

        if ($sucursalId) {
            $query->where('cc.IdSucursal', $sucursalId);
        }

        if (!empty($termino)) {
            $query->where(function ($q) use ($termino) {
                $q->where('i.Nombre', 'LIKE', '%' . $termino . '%')
                  ->orWhere('i.CI_NIT', 'LIKE', '%' . $termino . '%');
            });
        }

        $clientes = $query
            ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->groupBy('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->orderBy('i.Nombre')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'clientes' => $clientes,
        ]);
    }

    /**
     * ✅ EXPORTAR PDF
     */
    public function exportarPdf(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $filtros = [
            'identificador_id' => $request->get('identificador_id', ''),
            'sucursal_id' => $request->get('sucursal_id', $sucursalId),
            'id_grupo' => $request->get('id_grupo', ''),
            'solo_sin_precio' => filter_var($request->get('solo_sin_precio', false), FILTER_VALIDATE_BOOLEAN),
            'solo_sin_minimo' => filter_var($request->get('solo_sin_minimo', false), FILTER_VALIDATE_BOOLEAN),
        ];

        $datos = $this->obtenerDatos($clienteId, $filtros);

        if (empty($datos['clientes'])) {
            return redirect()->back()->with('error', 'No hay datos para exportar.');
        }

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);

        $fechaImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i');

        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        $y = 8;

        // Header empresa
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 6, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'), 0, 1, 'C');
        $y += 6;

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        if (!empty($empresa->NIT)) {
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 3.5, 'NIT: ' . $empresa->NIT, 0, 1, 'C');
            $y += 3.5;
        }

        $y += 2;
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(10, $y, 206, $y);
        $y += 5;

        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 7, 'MÍNIMOS Y PRECIOS POR CLIENTE', 0, 1, 'C');
        $y += 7;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 3.5, 'Fecha de impresión: ' . $fechaImpresion . '  ·  Generado por: ' . ($operador->nombre ?? '-'), 0, 1, 'C');
        $y += 6;

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(240, 245, 255);
        $pdf->SetTextColor(30, 60, 120);
        $textoContadores = 'Clientes: ' . $datos['contadores']['total_clientes']
            . '  ·  Grupos: ' . $datos['contadores']['total_grupos']
            . '  ·  Productos: ' . $datos['contadores']['total_productos']
            . '  ·  Sin mínimo: ' . $datos['contadores']['total_sin_minimo']
            . '  ·  Sin precio: ' . $datos['contadores']['total_sin_precio'];
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 5, $textoContadores, 1, 1, 'C', 1);
        $y += 8;

        foreach ($datos['clientes'] as $cliente) {
            // Header del cliente
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(230, 240, 255);
            $pdf->SetTextColor(20, 50, 110);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 6, '  ' . $cliente['Nombre'] . '  ·  CI: ' . $cliente['CI_NIT'], 'LTR', 1, 'L', 1);
            $y += 6;

            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 4, '  Sucursal: ' . $cliente['Sucursal'] . '  ·  ' . count($cliente['Grupos']) . ' grupo(s)  ·  ' . $cliente['TotalProductos'] . ' producto(s)', 'LR', 1, 'L', 1);
            $y += 4;

            foreach ($cliente['Grupos'] as $grupo) {
                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->SetTextColor(20, 50, 110);

                $estadoGrupo = $grupo['Configurado'] ? 'OK Configurado' : 'Sin configurar';
                $minimoGrupo = $grupo['Configurado'] ? number_format($grupo['CantidadMinimaGrupo'], 0, ',', '.') . ' und' : 'Sin configurar';

                $pdf->SetXY(12, $y);
                $pdf->Cell(80, 5, '[' . $grupo['NombreGrupo'] . ']', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->Cell(58, 5, 'Mínimo del grupo: ' . $minimoGrupo, 0, 0, 'L');
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell(46, 5, $estadoGrupo, 0, 1, 'R');
                $y += 5;

                // Tabla de productos
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->SetXY(14, $y);
                $pdf->Cell(8, 4, '#', 'TB', 0, 'C', 1);
                $pdf->Cell(80, 4, 'PRODUCTO', 'TB', 0, 'L', 1);
                $pdf->Cell(28, 4, 'S/F', 'TB', 0, 'C', 1);
                $pdf->Cell(28, 4, 'C/F', 'TB', 0, 'C', 1);
                $pdf->Cell(28, 4, 'MIN PRODUCTO', 'TB', 0, 'C', 1);
                $pdf->Cell(20, 4, 'ESTADO', 'TB', 1, 'C', 1);
                $y += 4;

                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(60, 60, 60);
                $fill = false;
                $contador = 0;

                foreach ($grupo['Productos'] as $producto) {
                    $contador++;
                    $nombreProducto = $producto['Descripcion'] ?? '-';
                    if (mb_strlen($nombreProducto, 'UTF-8') > 45) {
                        $nombreProducto = mb_substr($nombreProducto, 0, 43, 'UTF-8') . '...';
                    }

                    $sf = $producto['TienePrecio'] ? number_format($producto['PrecioSinFactura'], 2, ',', '.') : '0.00';
                    $cf = $producto['TienePrecio'] ? number_format($producto['PrecioConFactura'], 2, ',', '.') : '0.00';
                    $min = $producto['TienePrecio'] ? number_format($producto['PedidoMinimo'], 0, ',', '.') : '0';

                    $pdf->SetXY(14, $y);
                    $pdf->Cell(8, 4, $contador, 'LR', 0, 'C', $fill);
                    $pdf->Cell(80, 4, ' ' . $nombreProducto, 'LR', 0, 'L', $fill);
                    $pdf->Cell(28, 4, $sf, 'LR', 0, 'C', $fill);
                    $pdf->Cell(28, 4, $cf, 'LR', 0, 'C', $fill);
                    $pdf->Cell(28, 4, $min, 'LR', 0, 'C', $fill);

                    if ($producto['TienePrecio']) {
                        $pdf->SetTextColor(20, 130, 80);
                        $pdf->Cell(20, 4, 'OK', 'LR', 1, 'C', $fill);
                    } else {
                        $pdf->SetTextColor(200, 100, 20);
                        $pdf->Cell(20, 4, 'S/P', 'LR', 1, 'C', $fill);
                    }
                    $pdf->SetTextColor(60, 60, 60);
                    $y += 4;
                    $fill = !$fill;
                }

                $pdf->SetXY(14, $y);
                $pdf->Cell(192, 0.3, '', 'T', 1);
                $y += 3;
            }

            $y += 3;
        }

        $nombreArchivo = 'Minimos_Clientes_' . Carbon::now('America/La_Paz')->format('Y-m-d') . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit;
    }

    /**
     * ✅ EXPORTAR EXCEL REAL (.xls) usando PhpSpreadsheet
     */
    public function exportarExcel(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $filtros = [
            'identificador_id' => $request->get('identificador_id', ''),
            'sucursal_id' => $request->get('sucursal_id', $sucursalId),
            'id_grupo' => $request->get('id_grupo', ''),
            'solo_sin_precio' => filter_var($request->get('solo_sin_precio', false), FILTER_VALIDATE_BOOLEAN),
            'solo_sin_minimo' => filter_var($request->get('solo_sin_minimo', false), FILTER_VALIDATE_BOOLEAN),
        ];

        $datos = $this->obtenerDatos($clienteId, $filtros);

        if (empty($datos['clientes'])) {
            return redirect()->back()->with('error', 'No hay datos para exportar.');
        }

        // ============================================================
        // GENERAR EXCEL REAL CON PHPSPREADSHEET
        // ============================================================
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        
        // ============================================================
        // HOJA 1: RESUMEN
        // ============================================================
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Resumen');

        $fila = 1;

        // Título
        $sheet1->setCellValue('A' . $fila, 'RESUMEN DE MÍNIMOS POR CLIENTE');
        $sheet1->mergeCells('A' . $fila . ':G' . $fila);
        $sheet1->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(14);
        $sheet1->getStyle('A' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila++;
        
        // Subtítulo
        $sheet1->setCellValue('A' . $fila, 'Fecha de impresión: ' . Carbon::now('America/La_Paz')->format('d/m/Y H:i'));
        $sheet1->mergeCells('A' . $fila . ':G' . $fila);
        $sheet1->getStyle('A' . $fila)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila++;
        $fila++;

        // Encabezados
        $sheet1->setCellValue('A' . $fila, 'N°');
        $sheet1->setCellValue('B' . $fila, 'CI/NIT');
        $sheet1->setCellValue('C' . $fila, 'CLIENTE');
        $sheet1->setCellValue('D' . $fila, 'SUCURSAL');
        $sheet1->setCellValue('E' . $fila, 'GRUPO');
        $sheet1->setCellValue('F' . $fila, 'MÍNIMO');
        $sheet1->setCellValue('G' . $fila, 'ESTADO');

        $sheet1->getStyle('A' . $fila . ':G' . $fila)->getFont()->setBold(true);
        $sheet1->getStyle('A' . $fila . ':G' . $fila)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');
        $fila++;

        // Datos
        $contador = 0;
        foreach ($datos['clientes'] as $cli) {
            foreach ($cli['Grupos'] as $grp) {
                $contador++;
                $minimo = $grp['Configurado'] ? number_format($grp['CantidadMinimaGrupo'], 0, ',', '.') : '-';
                $estado = $grp['Configurado'] ? 'Configurado' : 'Sin configurar';

                $sheet1->setCellValue('A' . $fila, $contador);
                $sheet1->setCellValue('B' . $fila, $cli['CI_NIT'] ?? '');
                $sheet1->setCellValue('C' . $fila, $cli['Nombre'] ?? '');
                $sheet1->setCellValue('D' . $fila, $cli['Sucursal'] ?? '');
                $sheet1->setCellValue('E' . $fila, $grp['NombreGrupo'] ?? '');
                $sheet1->setCellValue('F' . $fila, $minimo);
                $sheet1->setCellValue('G' . $fila, $estado);

                // Colorear estado
                if ($grp['Configurado']) {
                    $sheet1->getStyle('G' . $fila)->getFont()->getColor()->setRGB('1B5E20');
                } else {
                    $sheet1->getStyle('G' . $fila)->getFont()->getColor()->setRGB('E65100');
                }

                $fila++;
            }
        }

        // Autoajustar columnas
        foreach (range('A', 'G') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // ============================================================
        // HOJA 2: DETALLE
        // ============================================================
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Detalle');

        $fila2 = 1;

        $sheet2->setCellValue('A' . $fila2, 'DETALLE DE PRODUCTOS CON PRECIOS Y MÍNIMOS');
        $sheet2->mergeCells('A' . $fila2 . ':J' . $fila2);
        $sheet2->getStyle('A' . $fila2)->getFont()->setBold(true)->setSize(14);
        $sheet2->getStyle('A' . $fila2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila2++;
        
        $sheet2->setCellValue('A' . $fila2, 'Fecha de impresión: ' . Carbon::now('America/La_Paz')->format('d/m/Y H:i'));
        $sheet2->mergeCells('A' . $fila2 . ':J' . $fila2);
        $sheet2->getStyle('A' . $fila2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila2++;
        $fila2++;

        // Encabezados
        $sheet2->setCellValue('A' . $fila2, 'N°');
        $sheet2->setCellValue('B' . $fila2, 'CI/NIT');
        $sheet2->setCellValue('C' . $fila2, 'CLIENTE');
        $sheet2->setCellValue('D' . $fila2, 'GRUPO');
        $sheet2->setCellValue('E' . $fila2, 'MÍN GRUPO');
        $sheet2->setCellValue('F' . $fila2, 'PRODUCTO');
        $sheet2->setCellValue('G' . $fila2, 'S/F');
        $sheet2->setCellValue('H' . $fila2, 'C/F');
        $sheet2->setCellValue('I' . $fila2, 'MÍN PROD');
        $sheet2->setCellValue('J' . $fila2, 'ESTADO');

        $sheet2->getStyle('A' . $fila2 . ':J' . $fila2)->getFont()->setBold(true);
        $sheet2->getStyle('A' . $fila2 . ':J' . $fila2)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');
        $fila2++;

        $contador2 = 0;
        foreach ($datos['clientes'] as $cli) {
            foreach ($cli['Grupos'] as $grp) {
                $minGrupo = $grp['Configurado'] ? number_format($grp['CantidadMinimaGrupo'], 0, ',', '.') : '-';

                foreach ($grp['Productos'] as $prod) {
                    $contador2++;
                    $sf = $prod['TienePrecio'] ? number_format($prod['PrecioSinFactura'], 2, ',', '.') : '0.00';
                    $cf = $prod['TienePrecio'] ? number_format($prod['PrecioConFactura'], 2, ',', '.') : '0.00';
                    $minProd = $prod['TienePrecio'] ? number_format($prod['PedidoMinimo'], 0, ',', '.') : '0';
                    $estado = $prod['TienePrecio'] ? 'OK' : 'Sin precio';

                    $sheet2->setCellValue('A' . $fila2, $contador2);
                    $sheet2->setCellValue('B' . $fila2, $cli['CI_NIT'] ?? '');
                    $sheet2->setCellValue('C' . $fila2, $cli['Nombre'] ?? '');
                    $sheet2->setCellValue('D' . $fila2, $grp['NombreGrupo'] ?? '');
                    $sheet2->setCellValue('E' . $fila2, $minGrupo);
                    $sheet2->setCellValue('F' . $fila2, $prod['Descripcion'] ?? '');
                    $sheet2->setCellValue('G' . $fila2, $sf);
                    $sheet2->setCellValue('H' . $fila2, $cf);
                    $sheet2->setCellValue('I' . $fila2, $minProd);
                    $sheet2->setCellValue('J' . $fila2, $estado);

                    if ($prod['TienePrecio']) {
                        $sheet2->getStyle('J' . $fila2)->getFont()->getColor()->setRGB('1B5E20');
                    } else {
                        $sheet2->getStyle('J' . $fila2)->getFont()->getColor()->setRGB('E65100');
                    }

                    $fila2++;
                }
            }
        }

        foreach (range('A', 'J') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        // ============================================================
        // DESCARGAR
        // ============================================================
        $nombreArchivo = 'Minimos_Clientes_' . Carbon::now('America/La_Paz')->format('Y-m-d') . '.xls';

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
        $writer->save('php://output');
        exit();
    }

    // ============================================================
    // MÉTODOS PRIVADOS
    // ============================================================

    private function obtenerDatos($clienteId, $filtros)
    {
        $sucursalId = $filtros['sucursal_id'];
        $identificadorId = $filtros['identificador_id'];
        $idGrupo = $filtros['id_grupo'];
        $soloSinPrecio = $filtros['solo_sin_precio'];
        $soloSinMinimo = $filtros['solo_sin_minimo'];

        $clientesQuery = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_cliente as cc')
            ->join('todos_identificador as i', 'cc.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('cc.IdCliente', $clienteId)
            ->where('cc.ActivoInactivo', 1);

        if ($sucursalId) {
            $clientesQuery->where('cc.IdSucursal', $sucursalId);
        }

        if ($identificadorId) {
            $clientesQuery->where('cc.IdIdentificador', $identificadorId);
        }

        $clientes = $clientesQuery
            ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->groupBy('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->orderBy('i.Nombre')
            ->get();

        $resultado = [];
        $contadores = [
            'total_clientes' => 0,
            'total_grupos' => 0,
            'total_productos' => 0,
            'total_sin_minimo' => 0,
            'total_sin_precio' => 0,
        ];

        foreach ($clientes as $cliente) {
            $gruposDelCliente = $this->obtenerGruposDelCliente($cliente->IdIdentificador, $clienteId, $sucursalId, $idGrupo);

            if (empty($gruposDelCliente)) continue;

            $minimos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_cliente_grupo')
                ->where('IdIdentificador', $cliente->IdIdentificador)
                ->where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 1)
                ->pluck('CantidadMinimaGrupo', 'IdGrupoAnalisis')
                ->toArray();

            $gruposData = [];
            $totalProductosCliente = 0;

            foreach ($gruposDelCliente as $grupo) {
                $idGrupoActual = $grupo['IdGrupoAnalisis'];
                $configurado = isset($minimos[$idGrupoActual]);

                $productos = $this->obtenerProductosDelGrupo(
                    $cliente->IdIdentificador,
                    $clienteId,
                    $sucursalId,
                    $idGrupoActual
                );

                if ($soloSinPrecio) {
                    $productos = array_values(array_filter($productos, function ($p) {
                        return !$p['TienePrecio'];
                    }));
                }

                if (empty($productos)) continue;

                $gruposData[] = [
                    'IdGrupoAnalisis' => $idGrupoActual,
                    'NombreGrupo' => $grupo['NombreGrupo'],
                    'CantidadMinimaGrupo' => $configurado ? floatval($minimos[$idGrupoActual]) : 0,
                    'Configurado' => $configurado,
                    'Productos' => $productos,
                ];

                $totalProductosCliente += count($productos);
                $contadores['total_grupos']++;
                $contadores['total_productos'] += count($productos);

                if (!$configurado) $contadores['total_sin_minimo']++;

                foreach ($productos as $p) {
                    if (!$p['TienePrecio']) $contadores['total_sin_precio']++;
                }
            }

            if ($soloSinMinimo) {
                $gruposData = array_values(array_filter($gruposData, function ($g) {
                    return !$g['Configurado'];
                }));
            }

            if (empty($gruposData)) continue;

            $resultado[] = [
                'IdIdentificador' => $cliente->IdIdentificador,
                'Nombre' => $cliente->Nombre,
                'CI_NIT' => $cliente->CI_NIT,
                'Sucursal' => $sucursalId ? $this->obtenerNombreSucursal($sucursalId) : 'Todas',
                'Grupos' => $gruposData,
                'TotalGrupos' => count($gruposData),
                'TotalProductos' => $totalProductosCliente,
            ];

            $contadores['total_clientes']++;
        }

        return [
            'clientes' => $resultado,
            'contadores' => $contadores,
        ];
    }

    private function obtenerGruposDelCliente($identificadorId, $clienteId, $sucursalId, $idGrupoFiltro = null)
    {
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_cliente as cc')
            ->join('operacion_pedidos_clientes_contenedor_grupo as cg', 'cc.IdContenedor', '=', 'cg.IdContenedor')
            ->join('inventario_productogrupoanalisis as g', 'cg.IdGrupoAnalisis', '=', 'g.IdGrupoAnalisis')
            ->where('cc.IdIdentificador', $identificadorId)
            ->where('cc.IdCliente', $clienteId)
            ->where('cc.ActivoInactivo', 1);

        if ($sucursalId) $query->where('cc.IdSucursal', $sucursalId);
        if ($idGrupoFiltro) $query->where('cg.IdGrupoAnalisis', $idGrupoFiltro);

        return $query
            ->select('g.IdGrupoAnalisis', 'g.Grupo as NombreGrupo')
            ->groupBy('g.IdGrupoAnalisis', 'g.Grupo')
            ->orderBy('g.Grupo')
            ->get()
            ->map(function ($item) {
                return [
                    'IdGrupoAnalisis' => $item->IdGrupoAnalisis,
                    'NombreGrupo' => $item->NombreGrupo,
                ];
            })
            ->toArray();
    }

    private function obtenerProductosDelGrupo($identificadorId, $clienteId, $sucursalId, $idGrupo)
    {
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle')
            ->where('IdCliente', $clienteId)
            ->where('IdGrupoAnalisis', $idGrupo)
            ->where('ActivoInactivo', 0)
            ->orderBy('OrdenInformes')
            ->orderBy('Descripcion')
            ->get(['IdProducto', 'Codigo', 'Descripcion']);

        $precios = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_precio_productos')
            ->where('IdIdentificador', $identificadorId)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->get()
            ->keyBy('IdProducto');

        $resultado = [];
        foreach ($productos as $p) {
            $precio = $precios->get($p->IdProducto);
            $tienePrecio = $precio ? true : false;

            $resultado[] = [
                'IdProducto' => $p->IdProducto,
                'Codigo' => $p->Codigo,
                'Descripcion' => $p->Descripcion,
                'PrecioSinFactura' => $tienePrecio ? floatval($precio->PrecioSinFactura) : 0,
                'PrecioConFactura' => $tienePrecio ? floatval($precio->PrecioConFactura) : 0,
                'PedidoMinimo' => $tienePrecio ? intval($precio->PedidoMinimo) : 0,
                'TienePrecio' => $tienePrecio,
                'IdPrecioCliente' => $tienePrecio ? $precio->IdPrecioCliente : null,
            ];
        }

        return $resultado;
    }

    private function obtenerNombreSucursal($sucursalId)
    {
        $suc = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);

        return $suc ? $suc->Nombre : '-';
    }
}