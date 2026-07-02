<?php

namespace App\Http\Controllers\Gestion\Reportes\Operacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Inertia\Inertia;

class InventarioTipoOperacionController extends Controller
{
    /**
     * Muestra el formulario del reporte (sucursal automática)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // Obtener datos de la sucursal logueada
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // Obtener estados de producto
        $estados = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_producto_estado')
            ->where('IdCliente', $clienteId)
            ->orderBy('Estado')
            ->get(['IdEstado', 'Estado']);
        
        // Obtener fechas disponibles
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', '>', '2020-01-01')
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha', 'Fecha', DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y') as fecha_formateada")]);
        
        // Obtener la fecha más reciente como predeterminada
        $fechaDefault = $fechas->first();
        
        return Inertia::render('Gestion/Reportes/Operacion/InventarioTipoOperacion', [
            'sucursal' => $sucursal,
            'estados' => $estados,
            'fechas' => $fechas,
            'fechaDefault' => $fechaDefault ? $fechaDefault->IdFecha : null,
        ]);
    }

    /**
     * Exporta el reporte a Excel (con sucursal de sesión)
     */
    public function exportar(Request $request)
    {
        set_time_limit(0);
        
        $request->validate([
            'fecha_inicial' => 'required|integer|exists:todos_fecha,IdFecha',
            'fecha_final' => 'required|integer|exists:todos_fecha,IdFecha',
            'estado_producto' => 'required|integer',
        ]);
        
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id'); // ← Sucursal logueada
        $estadoProducto = $request->estado_producto;
        $fechaInicialId = $request->fecha_inicial;
        $fechaFinalId = $request->fecha_final;
        
        // =============================================
        // OBTENER DATOS DE CABECERA
        // =============================================
        
        // Datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion']);
        
        $nombreEmpresa = $empresa->Nombre ?? '';
        $nitEmpresa = $empresa->NIT ?? '';
        $direccionEmpresa = $empresa->Direccion ?? '';
        
        // Nombre de la sucursal logueada
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);
        
        $nombreSucursal = $sucursal->Nombre ?? '';
        
        // Fechas
        $fechaInicialData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $fechaInicialId)
            ->first(['Fecha']);
        
        $fechaFinalData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $fechaFinalId)
            ->first(['Fecha']);
        
        $fechaInicial = date('d-m-Y', strtotime($fechaInicialData->Fecha));
        $fechaFinal = date('d-m-Y', strtotime($fechaFinalData->Fecha));
        
        // =============================================
        // INICIA EXPORTACION EXCEL
        // =============================================
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        
        // =============================================
        // CABECERA
        // =============================================
        $worksheet->setCellValue('A1', 'Empresa : ' . $nombreEmpresa);
        $worksheet->setCellValue('A2', 'Sucursal : ' . $nombreSucursal);
        $worksheet->setCellValue('A3', 'ANALISIS DE INVENTARIO POR TIPO DE OPERACION');
        $worksheet->setCellValue('A4', 'Entre ' . $fechaInicial . ' Y ' . $fechaFinal);
        $worksheet->setCellValue('A5', '(Expresado en Unidades)');
        
        // Estilo para cabecera
        $worksheet->getStyle('A1:A5')->getFont()->setBold(true);
        $worksheet->getStyle('A3')->getFont()->setSize(14);
        
        // =============================================
        // ARRAY DE LETRAS DE COLUMNAS
        // =============================================
        $columna = $this->getColumnLetters();
        $filaImpresion = 9;
        $filaImpresionNombreColumna = $filaImpresion - 1;
        
        // =============================================
        // CONSULTAS PRINCIPALES
        // =============================================
        
        // 1. Obtener todos los tipos de operación
        $tiposOperacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdTipoOperacion', 'Detalle', 'Concepto']);
        
        // 2. Obtener todos los productos filtrados (con sucursal logueada)
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId) // ← Sucursal logueada
            ->where('IdEstadoProducto', $estadoProducto)
            ->orderBy('Descripcion')
            ->get(['IdProducto', 'Codigo', 'Descripcion']);
        
        // Si no hay productos, mostrar mensaje y terminar
        if ($productos->isEmpty()) {
            $worksheet->setCellValue('A' . $filaImpresion, 'No hay productos en el catálogo');
            $writer = new Xls($spreadsheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="Inventario_TipoOperacion_SinDatos.xls"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
            exit;
        }
        
        // IDs de productos
        $idProductos = $productos->pluck('IdProducto')->toArray();
        $idsProductosStr = implode(',', $idProductos);
        
        // IDs de tipos de operación
        $tiposOperacionIds = $tiposOperacion->pluck('IdTipoOperacion')->toArray();
        $tiposOperacionDetalle = [];
        $tiposOperacionConcepto = [];
        foreach ($tiposOperacion as $tipo) {
            $tiposOperacionDetalle[$tipo->IdTipoOperacion] = $tipo->Detalle;
            $tiposOperacionConcepto[$tipo->IdTipoOperacion] = $tipo->Concepto;
        }
        $tiposOperacionIdsStr = implode(',', $tiposOperacionIds);
        
        // =============================================
        // CONSULTA ÚNICA: TODOS LOS MOVIMIENTOS
        // =============================================
        $movimientosData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_propiamente')
            ->select(
                'IdProducto',
                'IdTipoDeOperacion',
                'D_H',
                DB::raw("SUM(CASE WHEN IdFecha <= {$fechaInicialId} THEN Unidades ELSE 0 END) as SaldoInicial"),
                DB::raw("SUM(CASE WHEN IdFecha BETWEEN ({$fechaInicialId} + 1) AND {$fechaFinalId} THEN Unidades ELSE 0 END) as MovimientoPeriodo")
            )
            ->whereIn('IdProducto', $idProductos)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId) // ← Sucursal logueada
            ->where('IdFecha', '<=', $fechaFinalId)
            ->whereIn('IdTipoDeOperacion', $tiposOperacionIds)
            ->groupBy('IdProducto', 'IdTipoDeOperacion', 'D_H')
            ->get();
        
        // =============================================
        // ESTRUCTURAS DE DATOS
        // =============================================
        $datosProductos = [];
        $productosMap = [];
        
        foreach ($productos as $producto) {
            $idProducto = $producto->IdProducto;
            $productosMap[$idProducto] = $producto;
            
            $datosProductos[$idProducto] = [
                'descripcion' => $producto->Descripcion,
                'saldo_inicial_D' => 0,
                'saldo_inicial_H' => 0,
                'movimientos' => []
            ];
            
            // Inicializar movimientos por tipo y D_H
            foreach ($tiposOperacionIds as $idTipo) {
                $datosProductos[$idProducto]['movimientos'][$idTipo]['D'] = 0;
                $datosProductos[$idProducto]['movimientos'][$idTipo]['H'] = 0;
            }
        }
        
        // Procesar los movimientos
        foreach ($movimientosData as $mov) {
            $idProducto = $mov->IdProducto;
            $idTipoOperacion = $mov->IdTipoDeOperacion;
            $d_h = $mov->D_H;
            $saldoInicial = (float) $mov->SaldoInicial;
            $movPeriodo = (float) $mov->MovimientoPeriodo;
            
            if (isset($datosProductos[$idProducto])) {
                // Acumular saldos iniciales por D_H
                if ($d_h == 'D') {
                    $datosProductos[$idProducto]['saldo_inicial_D'] += $saldoInicial;
                } else {
                    $datosProductos[$idProducto]['saldo_inicial_H'] += $saldoInicial;
                }
                
                // Acumular movimientos del período por tipo y D_H
                if (isset($datosProductos[$idProducto]['movimientos'][$idTipoOperacion][$d_h])) {
                    $datosProductos[$idProducto]['movimientos'][$idTipoOperacion][$d_h] += $movPeriodo;
                }
            }
        }
        
        // =============================================
        // IDENTIFICAR TIPOS CON MOVIMIENTOS
        // =============================================
        $tiposConD = [];
        $tiposConH = [];
        
        foreach ($tiposOperacion as $tipo) {
            $idTipo = $tipo->IdTipoOperacion;
            $tieneD = false;
            $tieneH = false;
            
            foreach ($datosProductos as $data) {
                if ($data['movimientos'][$idTipo]['D'] != 0) {
                    $tieneD = true;
                }
                if ($data['movimientos'][$idTipo]['H'] != 0) {
                    $tieneH = true;
                }
                if ($tieneD && $tieneH) break;
            }
            
            if ($tieneD) {
                $tiposConD[] = $idTipo;
            }
            if ($tieneH) {
                $tiposConH[] = $idTipo;
            }
        }
        
        // =============================================
        // ESCRITURA DE ENCABEZADOS
        // =============================================
        
        // Columna A: Producto
        $worksheet->setCellValue('A' . ($filaImpresionNombreColumna - 1), 'PRODUCTO');
        $worksheet->mergeCells('A7:A8');
        $worksheet->getColumnDimension('A')->setWidth(40);
        $worksheet->getStyle('A7:A8')->getAlignment()->setHorizontal('center');
        $worksheet->getStyle('A7:A8')->getAlignment()->setVertical('center');
        
        // Columna B: Saldo Inicial
        $worksheet->setCellValue('B' . ($filaImpresionNombreColumna - 1), 'SALDO INICIAL');
        $worksheet->mergeCells('B7:B8');
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getStyle('B7:B8')->getAlignment()->setHorizontal('center');
        $worksheet->getStyle('B7:B8')->getAlignment()->setVertical('center');
        
        // SECCIÓN AUMENTOS - Empieza en C
        $colActual = 2; // C
        $colInicioAumentos = 2; // C
        
        if (count($tiposConD) > 0) {
            $worksheet->setCellValue($columna[$colInicioAumentos] . '7', 'AUMENTOS');
            $worksheet->mergeCells($columna[$colInicioAumentos] . '7:' . $columna[$colInicioAumentos + count($tiposConD) - 1] . '7');
            
            $worksheet->getStyle($columna[$colInicioAumentos] . '7:' . $columna[$colInicioAumentos + count($tiposConD) - 1] . '7')
                ->getAlignment()->setHorizontal('center');
            $worksheet->getStyle($columna[$colInicioAumentos] . '7:' . $columna[$colInicioAumentos + count($tiposConD) - 1] . '7')
                ->getAlignment()->setVertical('center');
            $worksheet->getStyle($columna[$colInicioAumentos] . '7')->getFont()->setBold(true);
            
            $colDetalle = $colInicioAumentos;
            foreach ($tiposConD as $idTipo) {
                $worksheet->setCellValue($columna[$colDetalle] . '8', $tiposOperacionDetalle[$idTipo]);
                $worksheet->getColumnDimension($columna[$colDetalle])->setWidth(15);
                $worksheet->getStyle($columna[$colDetalle] . '8')->getAlignment()->setHorizontal('center');
                $worksheet->getStyle($columna[$colDetalle] . '8')->getAlignment()->setVertical('center');
                $worksheet->getStyle($columna[$colDetalle] . '8')->getFont()->setBold(true);
                $colDetalle++;
            }
            $colActual = $colDetalle - 1;
        }
        
        // SECCIÓN DISMINUCIONES
        $colInicioDisminuciones = $colActual + 1;
        
        if (count($tiposConH) > 0) {
            $worksheet->setCellValue($columna[$colInicioDisminuciones] . '7', 'DISMINUCIONES');
            $worksheet->mergeCells($columna[$colInicioDisminuciones] . '7:' . $columna[$colInicioDisminuciones + count($tiposConH) - 1] . '7');
            
            $worksheet->getStyle($columna[$colInicioDisminuciones] . '7:' . $columna[$colInicioDisminuciones + count($tiposConH) - 1] . '7')
                ->getAlignment()->setHorizontal('center');
            $worksheet->getStyle($columna[$colInicioDisminuciones] . '7:' . $columna[$colInicioDisminuciones + count($tiposConH) - 1] . '7')
                ->getAlignment()->setVertical('center');
            $worksheet->getStyle($columna[$colInicioDisminuciones] . '7')->getFont()->setBold(true);
            
            $colDetalle = $colInicioDisminuciones;
            foreach ($tiposConH as $idTipo) {
                $worksheet->setCellValue($columna[$colDetalle] . '8', $tiposOperacionDetalle[$idTipo]);
                $worksheet->getColumnDimension($columna[$colDetalle])->setWidth(15);
                $worksheet->getStyle($columna[$colDetalle] . '8')->getAlignment()->setHorizontal('center');
                $worksheet->getStyle($columna[$colDetalle] . '8')->getAlignment()->setVertical('center');
                $worksheet->getStyle($columna[$colDetalle] . '8')->getFont()->setBold(true);
                $colDetalle++;
            }
            $colActual = $colDetalle - 1;
        }
        
        // COLUMNA SALDO FINAL
        $colSaldoFinal = $colActual + 1;
        $worksheet->setCellValue($columna[$colSaldoFinal] . ($filaImpresionNombreColumna - 1), 'SALDO FINAL');
        $worksheet->mergeCells($columna[$colSaldoFinal] . '7:' . $columna[$colSaldoFinal] . '8');
        $worksheet->getColumnDimension($columna[$colSaldoFinal])->setWidth(15);
        $worksheet->getStyle($columna[$colSaldoFinal] . '7:' . $columna[$colSaldoFinal] . '8')
            ->getAlignment()->setHorizontal('center');
        $worksheet->getStyle($columna[$colSaldoFinal] . '7:' . $columna[$colSaldoFinal] . '8')
            ->getAlignment()->setVertical('center');
        $worksheet->getStyle($columna[$colSaldoFinal] . '7')->getFont()->setBold(true);
        
        // =============================================
        // IMPRESIÓN DE DATOS
        // =============================================
        $filaActual = $filaImpresion;
        $productosConDatos = 0;
        
        foreach ($productos as $producto) {
            $idProducto = $producto->IdProducto;
            $data = $datosProductos[$idProducto];
            
            // Calcular saldo inicial (D - H)
            $saldoInicial = $data['saldo_inicial_D'] - $data['saldo_inicial_H'];
            
            // Verificar si tiene movimiento
            $tieneMovimiento = ($saldoInicial != 0);
            if (!$tieneMovimiento) {
                foreach ($tiposOperacionIds as $idTipo) {
                    if ($data['movimientos'][$idTipo]['D'] != 0 || $data['movimientos'][$idTipo]['H'] != 0) {
                        $tieneMovimiento = true;
                        break;
                    }
                }
            }
            
            if ($tieneMovimiento) {
                // Columna A: Producto
                $worksheet->setCellValue('A' . $filaActual, $data['descripcion']);
                
                // Columna B: Saldo Inicial
                $worksheet->setCellValue('B' . $filaActual, $saldoInicial);
                
                // Aumentos (D) - empieza en C
                $colDetalle = $colInicioAumentos;
                $totalAumentos = 0;
                foreach ($tiposConD as $idTipo) {
                    $valor = $data['movimientos'][$idTipo]['D'];
                    $worksheet->setCellValue($columna[$colDetalle] . $filaActual, $valor);
                    $totalAumentos += $valor;
                    $colDetalle++;
                }
                
                // Disminuciones (H)
                $colDetalle = $colInicioDisminuciones;
                $totalDisminuciones = 0;
                foreach ($tiposConH as $idTipo) {
                    $valor = $data['movimientos'][$idTipo]['H'];
                    $worksheet->setCellValue($columna[$colDetalle] . $filaActual, $valor);
                    $totalDisminuciones += $valor;
                    $colDetalle++;
                }
                
                // Saldo Final
                $saldoFinal = $saldoInicial + $totalAumentos - $totalDisminuciones;
                $worksheet->setCellValue($columna[$colSaldoFinal] . $filaActual, $saldoFinal);
                
                $filaActual++;
                $productosConDatos++;
            }
        }
        
        // Si no hay productos con datos
        if ($productosConDatos == 0) {
            $worksheet->setCellValue('A' . $filaImpresion, 'No hay productos con movimiento en el período seleccionado');
            $filaActual++;
        }
        
        // =============================================
        // FORMATOS
        // =============================================
        $ultimaFila = $filaActual - 1;
        
        if ($ultimaFila >= 9) {
            // Bordes para toda la tabla
            $styleBorde = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ];
            
            $rangoCompleto = 'A7:' . $columna[$colSaldoFinal] . $ultimaFila;
            $worksheet->getStyle($rangoCompleto)->applyFromArray($styleBorde);
            
            // Formato de números para todas las columnas numéricas
            $rangoNumeros = 'B9:' . $columna[$colSaldoFinal] . $ultimaFila;
            $worksheet->getStyle($rangoNumeros)->getNumberFormat()->setFormatCode(' #,##0.00 ;(#,##0.00)');
            
            // Alinear números a la derecha
            $worksheet->getStyle($rangoNumeros)->getAlignment()->setHorizontal('right');
            
            // NEGATIVOS EN COLOR ROJO
            $styleNegativo = [
                'font' => [
                    'color' => ['argb' => 'FFFF0000'],
                ],
            ];
            
            for ($fila = 9; $fila <= $ultimaFila; $fila++) {
                for ($col = 1; $col <= $colSaldoFinal; $col++) {
                    $celda = $columna[$col] . $fila;
                    $valor = $worksheet->getCell($celda)->getValue();
                    if (is_numeric($valor) && $valor < 0) {
                        $worksheet->getStyle($celda)->applyFromArray($styleNegativo);
                    }
                }
            }
            
            // Alinear texto de productos a la izquierda
            $worksheet->getStyle('A9:A' . $ultimaFila)->getAlignment()->setHorizontal('left');
            
            // =============================================
            // ESTILOS DE ENCABEZADOS POR SECCIÓN
            // =============================================
            $styleHeaderProducto = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0'],
                ],
                'font' => ['bold' => true],
            ];
            
            $styleHeaderSaldoInicial = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0'],
                ],
                'font' => ['bold' => true],
            ];
            
            $styleHeaderAumentos = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD9D9D9'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF006600'],
                ],
            ];
            
            $styleHeaderDisminuciones = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFBFBFBF'],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FF8B0000'],
                ],
            ];
            
            $styleHeaderSaldoFinal = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0'],
                ],
                'font' => ['bold' => true],
            ];
            
            // Aplicar estilos específicos por sección
            $worksheet->getStyle('A7:A8')->applyFromArray($styleHeaderProducto);
            $worksheet->getStyle('B7:B8')->applyFromArray($styleHeaderSaldoInicial);
            
            if (count($tiposConD) > 0) {
                $worksheet->getStyle($columna[$colInicioAumentos] . '7:' . $columna[$colInicioAumentos + count($tiposConD) - 1] . '8')
                    ->applyFromArray($styleHeaderAumentos);
            }
            
            if (count($tiposConH) > 0) {
                $worksheet->getStyle($columna[$colInicioDisminuciones] . '7:' . $columna[$colInicioDisminuciones + count($tiposConH) - 1] . '8')
                    ->applyFromArray($styleHeaderDisminuciones);
            }
            
            $worksheet->getStyle($columna[$colSaldoFinal] . '7:' . $columna[$colSaldoFinal] . '8')
                ->applyFromArray($styleHeaderSaldoFinal);
            
            // Inmovilizar paneles en A9
            $worksheet->freezePane('A9');
        }
        
        // =============================================
        // SALIDA DEL ARCHIVO
        // =============================================
        $nombreArchivo = 'Inventario_TipoOperacion_' . date('Ymd') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Genera array de letras de columnas (A, B, C, ..., ZZ)
     */
    private function getColumnLetters()
    {
        $letters = ['', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $result = [];
        
        for ($i = 1; $i <= 100; $i++) {
            if ($i <= 26) {
                $result[] = $letters[$i];
            } else {
                $first = floor(($i - 1) / 26);
                $second = ($i - 1) % 26;
                $result[] = $letters[$first] . $letters[$second + 1];
            }
        }
        
        return $result;
    }
}