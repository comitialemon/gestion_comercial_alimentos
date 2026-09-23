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
     *    - Hoja 1: RESUMEN GENERAL
     *    - Hojas 2..N: UNA HOJA POR CLIENTE (agrupada por Grupo de Análisis)
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

        // ✅ EMPRESA + OPERADOR
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', session('operador_id'))
            ->first(['todos_identificador.Nombre as nombre']);

        $fechaImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i');

        // ============================================================
        // CREAR SPREADSHEET
        // ============================================================
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // ============================================================
        // ✅ HOJA 1: RESUMEN GENERAL
        // ============================================================
        $sheetResumen = $spreadsheet->getActiveSheet();
        $sheetResumen->setTitle('Resumen');

        $fila = 1;

        // Título
        $sheetResumen->setCellValue('A' . $fila, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'));
        $sheetResumen->mergeCells('A' . $fila . ':H' . $fila);
        $sheetResumen->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(14)
            ->getColor()->setRGB('1E3C78');
        $sheetResumen->getStyle('A' . $fila)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila++;

        if (!empty($empresa->NIT)) {
            $sheetResumen->setCellValue('A' . $fila, 'NIT: ' . $empresa->NIT);
            $sheetResumen->mergeCells('A' . $fila . ':H' . $fila);
            $sheetResumen->getStyle('A' . $fila)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $fila++;
        }

        $sheetResumen->setCellValue('A' . $fila, 'RESUMEN DE MÍNIMOS POR CLIENTE');
        $sheetResumen->mergeCells('A' . $fila . ':H' . $fila);
        $sheetResumen->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(13)
            ->getColor()->setRGB('1E3C78');
        $sheetResumen->getStyle('A' . $fila)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila++;

        $sheetResumen->setCellValue('A' . $fila, 'Fecha: ' . $fechaImpresion . '   ·   Generado por: ' . ($operador->nombre ?? '-'));
        $sheetResumen->mergeCells('A' . $fila . ':H' . $fila);
        $sheetResumen->getStyle('A' . $fila)->getFont()->setItalic(true)->setSize(9)
            ->getColor()->setRGB('666666');
        $sheetResumen->getStyle('A' . $fila)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila += 2;

        // Contadores
        $sheetResumen->setCellValue('A' . $fila, 'Clientes: ' . $datos['contadores']['total_clientes']
            . '  ·  Grupos: ' . $datos['contadores']['total_grupos']
            . '  ·  Productos: ' . $datos['contadores']['total_productos']
            . '  ·  Sin mínimo: ' . $datos['contadores']['total_sin_minimo']
            . '  ·  Sin precio: ' . $datos['contadores']['total_sin_precio']);
        $sheetResumen->mergeCells('A' . $fila . ':H' . $fila);
        $sheetResumen->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(9)
            ->getColor()->setRGB('1E3C78');
        $sheetResumen->getStyle('A' . $fila)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F0F5FF');
        $sheetResumen->getStyle('A' . $fila)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $fila += 2;

        // Encabezados del resumen
        $headersResumen = ['N°', 'CI/NIT', 'CLIENTE', 'SUCURSAL', 'GRUPO', 'MÍNIMO', 'PRODUCTOS', 'ESTADO'];
        $col = 'A';
        foreach ($headersResumen as $h) {
            $sheetResumen->setCellValue($col . $fila, $h);
            $sheetResumen->getStyle($col . $fila)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheetResumen->getStyle($col . $fila)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('1E3C78');
            $sheetResumen->getStyle($col . $fila)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $fila++;

        $contador = 0;
        foreach ($datos['clientes'] as $cli) {
            $primerGrupoDelCliente = true;

            foreach ($cli['Grupos'] as $grp) {
                $contador++;
                $minimo = $grp['Configurado']
                    ? number_format($grp['CantidadMinimaGrupo'], 0, ',', '.') . ' und'
                    : '-';
                $estado = $grp['Configurado'] ? 'Configurado' : 'Sin configurar';
                $cantProd = count($grp['Productos']);

                $sheetResumen->setCellValue('A' . $fila, $contador);
                $sheetResumen->setCellValue('B' . $fila, $cli['CI_NIT'] ?? '');
                $sheetResumen->setCellValue('C' . $fila, $primerGrupoDelCliente ? ($cli['Nombre'] ?? '') : '');
                $sheetResumen->setCellValue('D' . $fila, $primerGrupoDelCliente ? ($cli['Sucursal'] ?? '') : '');
                $sheetResumen->setCellValue('E' . $fila, $grp['NombreGrupo'] ?? '');
                $sheetResumen->setCellValue('F' . $fila, $minimo);
                $sheetResumen->setCellValue('G' . $fila, $cantProd);
                $sheetResumen->setCellValue('H' . $fila, $estado);

                // Colorear estado
                if ($grp['Configurado']) {
                    $sheetResumen->getStyle('H' . $fila)->getFont()->getColor()->setRGB('1B5E20');
                    $sheetResumen->getStyle('H' . $fila)->getFont()->setBold(true);
                } else {
                    $sheetResumen->getStyle('H' . $fila)->getFont()->getColor()->setRGB('E65100');
                    $sheetResumen->getStyle('H' . $fila)->getFont()->setBold(true);
                }

                // Fondo suave para agrupar visualmente al cliente
                if ($primerGrupoDelCliente) {
                    $sheetResumen->getStyle('A' . $fila . ':D' . $fila)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F5F7FC');
                }

                $fila++;
                $primerGrupoDelCliente = false;
            }

            // Fila separadora entre clientes
            $sheetResumen->setCellValue('A' . $fila, '');
            $fila++;
        }

        // Autoajustar
        foreach (range('A', 'H') as $col) {
            $sheetResumen->getColumnDimension($col)->setAutoSize(true);
        }

        // ============================================================
        // ✅ HOJA POR CADA CLIENTE
        // ============================================================
        foreach ($datos['clientes'] as $cli) {
            // Nombre de hoja: máx 31 caracteres, sin caracteres inválidos
            $nombreHoja = $this->generarNombreHoja($cli['Nombre']);

            $sheetCliente = $spreadsheet->createSheet();
            $sheetCliente->setTitle($nombreHoja);

            $f = 1;

            // ============ HEADER DEL CLIENTE ============
            $sheetCliente->setCellValue('A' . $f, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'));
            $sheetCliente->mergeCells('A' . $f . ':H' . $f);
            $sheetCliente->getStyle('A' . $f)->getFont()->setBold(true)->setSize(12)
                ->getColor()->setRGB('1E3C78');
            $sheetCliente->getStyle('A' . $f)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $f++;

            $sheetCliente->setCellValue('A' . $f, 'MÍNIMOS Y PRECIOS - ' . mb_strtoupper($cli['Nombre'], 'UTF-8'));
            $sheetCliente->mergeCells('A' . $f . ':H' . $f);
            $sheetCliente->getStyle('A' . $f)->getFont()->setBold(true)->setSize(11)
                ->getColor()->setRGB('1E3C78');
            $sheetCliente->getStyle('A' . $f)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $f++;

            if (!empty($cli['CI_NIT'])) {
                $sheetCliente->setCellValue('A' . $f, 'CI/NIT: ' . $cli['CI_NIT'] . '   ·   Sucursal: ' . ($cli['Sucursal'] ?? '-'));
                $sheetCliente->mergeCells('A' . $f . ':H' . $f);
                $sheetCliente->getStyle('A' . $f)->getFont()->setItalic(true)->setSize(9)
                    ->getColor()->setRGB('666666');
                $sheetCliente->getStyle('A' . $f)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $f++;
            }

            $sheetCliente->setCellValue('A' . $f, 'Fecha: ' . $fechaImpresion . '   ·   Generado por: ' . ($operador->nombre ?? '-'));
            $sheetCliente->mergeCells('A' . $f . ':H' . $f);
            $sheetCliente->getStyle('A' . $f)->getFont()->setItalic(true)->setSize(9)
                ->getColor()->setRGB('666666');
            $sheetCliente->getStyle('A' . $f)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $f += 2;

            // ============ RECORRER GRUPOS ============
            foreach ($cli['Grupos'] as $grp) {
                // ✅ Título del GRUPO
                $minimoGrupo = $grp['Configurado']
                    ? 'Mínimo del grupo: ' . number_format($grp['CantidadMinimaGrupo'], 0, ',', '.') . ' und'
                    : 'Mínimo del grupo: SIN CONFIGURAR';

                $estadoGrupo = $grp['Configurado'] ? 'CONFIGURADO' : 'SIN CONFIGURAR';

                $sheetCliente->setCellValue('A' . $f, '  [' . $grp['NombreGrupo'] . ']   ·   ' . $minimoGrupo . '   ·   ' . $estadoGrupo);
                $sheetCliente->mergeCells('A' . $f . ':H' . $f);
                $sheetCliente->getStyle('A' . $f)->getFont()->setBold(true)->setSize(10)
                    ->getColor()->setRGB('1E3C78');
                $sheetCliente->getStyle('A' . $f)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E8EAF6');
                $f++;

                // ============ ENCABEZADOS PRODUCTOS ============
                $headers = ['Nº', 'CÓDIGO', 'PRODUCTO', 'SIN FACTURA', 'CON FACTURA', 'MÍN. PRODUCTO', 'ESTADO', 'OBS.'];
                $col = 'A';
                foreach ($headers as $h) {
                    $sheetCliente->setCellValue($col . $f, $h);
                    $sheetCliente->getStyle($col . $f)->getFont()->setBold(true)->setSize(9)
                        ->getColor()->setRGB('FFFFFF');
                    $sheetCliente->getStyle($col . $f)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('1E3C78');
                    $sheetCliente->getStyle($col . $f)->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $col++;
                }
                $f++;

                // ============ PRODUCTOS DEL GRUPO ============
                $i = 0;
                $sumaSF = 0;
                $sumaCF = 0;
                $sumaMin = 0;

                foreach ($grp['Productos'] as $prod) {
                    $i++;

                    $sf  = $prod['TienePrecio'] ? (float) $prod['PrecioSinFactura'] : 0;
                    $cf  = $prod['TienePrecio'] ? (float) $prod['PrecioConFactura'] : 0;
                    $min = $prod['TienePrecio'] ? (int)   $prod['PedidoMinimo']    : 0;
                    $estado = $prod['TienePrecio'] ? 'OK' : 'SIN PRECIO';

                    $sumaSF  += $sf;
                    $sumaCF  += $cf;
                    $sumaMin += $min;

                    $sheetCliente->setCellValue('A' . $f, $i);
                    $sheetCliente->setCellValue('B' . $f, $prod['Codigo'] ?? '-');
                    $sheetCliente->setCellValue('C' . $f, $prod['Descripcion'] ?? '-');
                    $sheetCliente->setCellValue('D' . $f, $sf);
                    $sheetCliente->setCellValue('E' . $f, $cf);
                    $sheetCliente->setCellValue('F' . $f, $min);
                    $sheetCliente->setCellValue('G' . $f, $estado);
                    $sheetCliente->setCellValue('H' . $f, '');

                    // Moneda
                    $sheetCliente->getStyle('D' . $f . ':E' . $f)
                        ->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');

                    // Centrar columnas numéricas
                    $sheetCliente->getStyle('A' . $f)
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheetCliente->getStyle('F' . $f . ':G' . $f)
                        ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                    // Color del estado
                    if ($prod['TienePrecio']) {
                        $sheetCliente->getStyle('G' . $f)->getFont()->setBold(true)->getColor()->setRGB('1B5E20');
                    } else {
                        $sheetCliente->getStyle('G' . $f)->getFont()->setBold(true)->getColor()->setRGB('E65100');
                        // Fondo rosado claro en toda la fila
                        $sheetCliente->getStyle('A' . $f . ':H' . $f)->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('FFF5F5');
                    }

                    $f++;
                }

                // ============ SUBTOTAL DEL GRUPO ============
                $sheetCliente->setCellValue('A' . $f, 'Subtotal ' . $grp['NombreGrupo']);
                $sheetCliente->mergeCells('A' . $f . ':C' . $f);
                $sheetCliente->getStyle('A' . $f)->getFont()->setBold(true)
                    ->getColor()->setRGB('1E3C78');
                $sheetCliente->getStyle('A' . $f)->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

                $sheetCliente->setCellValue('D' . $f, $sumaSF);
                $sheetCliente->setCellValue('E' . $f, $sumaCF);
                $sheetCliente->setCellValue('F' . $f, $sumaMin);
                $sheetCliente->setCellValue('G' . $f, $i . ' prod.');
                $sheetCliente->setCellValue('H' . $f, '');

                $sheetCliente->getStyle('D' . $f . ':E' . $f)
                    ->getNumberFormat()->setFormatCode('"Bs. "#,##0.00');
                $sheetCliente->getStyle('D' . $f . ':F' . $f)
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheetCliente->getStyle('G' . $f)
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $sheetCliente->getStyle('A' . $f . ':H' . $f)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F5F7FC');
                $sheetCliente->getStyle('A' . $f . ':H' . $f)->getFont()->setBold(true);

                $f += 2;
            }

            // Autoajustar
            foreach (range('A', 'H') as $col) {
                $sheetCliente->getColumnDimension($col)->setAutoSize(true);
            }

            // Congelar la primera fila del header
            $sheetCliente->freezePane('A2');
        }

        // ============================================================
        // DESCARGAR
        // ============================================================
        $nombreArchivo = 'Minimos_Clientes_' . Carbon::now('America/La_Paz')->format('Y-m-d_His') . '.xls';

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

    /**
     * ✅ HELPER: Generar nombre de hoja válido (máx 31 caracteres, sin caracteres inválidos)
     */
    private function generarNombreHoja($nombre, $indice = null)
    {
        // Caracteres inválidos en Excel: \ / ? * [ ] :
        $nombre = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $nombre);
        $nombre = trim($nombre);

        // Máximo 31 caracteres
        if (mb_strlen($nombre, 'UTF-8') > 31) {
            $nombre = mb_substr($nombre, 0, 28, 'UTF-8') . '...';
        }

        // Si quedó vacío, usar genérico
        if (empty($nombre)) {
            $nombre = 'Cliente_' . ($indice ?? rand(1000, 9999));
        }

        return $nombre;
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