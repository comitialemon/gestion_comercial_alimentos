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
use PhpOffice\PhpSpreadsheet\Style\Color;
use Inertia\Inertia;

class InventarioTipoOperacionController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        $estados = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_producto_estado')
            ->where('IdCliente', $clienteId)
            ->orderBy('Estado')
            ->get(['IdEstado', 'Estado']);
        
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', '>', '2020-01-01')
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha', 'Fecha', DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y') as fecha_formateada")]);
        
        $fechaDefault = $fechas->first();
        
        return Inertia::render('Gestion/Reportes/Operacion/InventarioTipoOperacion', [
            'sucursal' => $sucursal,
            'estados' => $estados,
            'fechas' => $fechas,
            'fechaDefault' => $fechaDefault ? $fechaDefault->IdFecha : null,
        ]);
    }

    public function exportar(Request $request)
    {
        set_time_limit(0);
        
        // =============================================
        // 🔥 VALIDACIÓN
        // =============================================
        try {
            $request->validate([
                'fecha_inicial' => 'required|integer',
                'fecha_final' => 'required|integer',
                'estado_producto' => 'required|integer',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación: ' . $e->getMessage()
            ], 422);
        }
        
        // =============================================
        // 📊 PARÁMETROS
        // =============================================
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $estadoProducto = $request->estado_producto;
        $fechaInicialId = (int)$request->fecha_inicial;
        $fechaFinalId = (int)$request->fecha_final;
        
        // =============================================
        // 📅 FECHAS PARA CABECERA
        // =============================================
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->whereIn('IdFecha', [$fechaInicialId, $fechaFinalId])
            ->get()
            ->keyBy('IdFecha');
        
        if (!isset($fechas[$fechaInicialId]) || !isset($fechas[$fechaFinalId])) {
            return response()->json([
                'success' => false,
                'message' => 'Fechas no encontradas'
            ], 404);
        }
        
        $fechaInicialReal = $fechas[$fechaInicialId]->Fecha;
        $fechaFinalReal = $fechas[$fechaFinalId]->Fecha;
        $fechaInicial = date('d-m-Y', strtotime($fechaInicialReal));
        $fechaFinal = date('d-m-Y', strtotime($fechaFinalReal));
        
        // =============================================
        // 🏢 DATOS DE CABECERA
        // =============================================
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion']);
        
        $nombreEmpresa = $empresa->Nombre ?? '';
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);
        $nombreSucursal = $sucursal->Nombre ?? '';
        
        // =============================================
        // 📋 1. TODOS LOS TIPOS DE OPERACIÓN ACTIVOS
        // =============================================
        $tiposOperacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle', 'ASC')
            ->get(['IdTipoOperacion', 'Detalle', 'Concepto']);
        
        $tiposOperacionIds = $tiposOperacion->pluck('IdTipoOperacion')->toArray();
        $tiposOperacionDetalle = [];
        foreach ($tiposOperacion as $tipo) {
            $tiposOperacionDetalle[$tipo->IdTipoOperacion] = $tipo->Detalle;
        }
        
        if (empty($tiposOperacionIds)) {
            $spreadsheet = new Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setCellValue('A1', 'No hay tipos de operación activos');
            $writer = new Xls($spreadsheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="SinTipos.xls"');
            $writer->save('php://output');
            exit;
        }
        
        // =============================================
        // 📦 2. PRODUCTOS POR ESTADO
        // =============================================
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle')
            ->where('IdCliente', $clienteId)
            ->where('IdEstadoProducto', $estadoProducto)
            ->orderBy('Descripcion', 'ASC')
            ->get(['IdProducto', 'Codigo', 'Descripcion']);
        
        if ($productos->isEmpty()) {
            $spreadsheet = new Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setCellValue('A1', 'No hay productos con este estado');
            $writer = new Xls($spreadsheet);
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="SinProductos.xls"');
            $writer->save('php://output');
            exit;
        }
        
        $idProductos = $productos->pluck('IdProducto')->toArray();
        $idsProductosStr = implode(',', $idProductos);
        $tiposOperacionIdsStr = implode(',', $tiposOperacionIds);
        
        // =============================================
        // 📊 3. CONSULTA PRINCIPAL
        // =============================================
        $fechaInicialMovimiento = $fechaInicialId + 1;
        
        $queryMovimientos = "
            SELECT 
                IdProducto,
                IdTipoDeOperacion,
                D_H,
                SUM(CASE WHEN IdFecha <= {$fechaInicialId} THEN Unidades ELSE 0 END) as SaldoInicial,
                SUM(CASE WHEN IdFecha BETWEEN {$fechaInicialMovimiento} AND {$fechaFinalId} THEN Unidades ELSE 0 END) as MovimientoPeriodo
            FROM inventario_propiamente 
            WHERE IdProducto IN ({$idsProductosStr}) 
                AND IdCliente = {$clienteId}
                AND IdSucursal = {$sucursalId}
                AND IdFecha <= {$fechaFinalId}
                AND IdTipoDeOperacion IN ({$tiposOperacionIdsStr})
            GROUP BY IdProducto, IdTipoDeOperacion, D_H
        ";
        
        $movimientosData = DB::connection('mysql_gestion_comercial_alimentos')
            ->select($queryMovimientos);
        
        // =============================================
        // 🔄 4. PROCESAR DATOS
        // =============================================
        $datosProductos = [];
        
        foreach ($productos as $producto) {
            $idProducto = $producto->IdProducto;
            
            $datosProductos[$idProducto] = [
                'descripcion' => $producto->Descripcion,
                'saldo_inicial_D' => 0,
                'saldo_inicial_H' => 0,
                'movimientos' => []
            ];
            
            foreach ($tiposOperacionIds as $idTipo) {
                $datosProductos[$idProducto]['movimientos'][$idTipo]['D'] = 0;
                $datosProductos[$idProducto]['movimientos'][$idTipo]['H'] = 0;
            }
        }
        
        foreach ($movimientosData as $mov) {
            $idProducto = $mov->IdProducto;
            $idTipoOperacion = $mov->IdTipoDeOperacion;
            $d_h = $mov->D_H;
            $saldoInicial = (float) ($mov->SaldoInicial ?? 0);
            $movPeriodo = (float) ($mov->MovimientoPeriodo ?? 0);
            
            if (!isset($datosProductos[$idProducto])) {
                continue;
            }
            
            if ($d_h == 'D') {
                $datosProductos[$idProducto]['saldo_inicial_D'] += $saldoInicial;
            } else {
                $datosProductos[$idProducto]['saldo_inicial_H'] += $saldoInicial;
            }
            
            if (isset($datosProductos[$idProducto]['movimientos'][$idTipoOperacion][$d_h])) {
                $datosProductos[$idProducto]['movimientos'][$idTipoOperacion][$d_h] += $movPeriodo;
            }
        }
        
        // =============================================
        // 🎯 5. IDENTIFICAR TIPOS CON MOVIMIENTOS
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
        // 📊 6. CREAR EXCEL
        // =============================================
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        
        // CABECERA
        $worksheet->setCellValue('A1', 'Empresa : ' . $nombreEmpresa);
        $worksheet->setCellValue('A2', 'Sucursal : ' . $nombreSucursal);
        $worksheet->setCellValue('A3', 'ANALISIS DE INVENTARIO POR TIPO DE OPERACION');
        $worksheet->setCellValue('A4', 'Entre ' . $fechaInicial . ' Y ' . $fechaFinal);
        $worksheet->setCellValue('A5', '(Expresado en Unidades)');
        
        $worksheet->getStyle('A1:A5')->getFont()->setBold(true);
        $worksheet->getStyle('A3')->getFont()->setSize(14);
        
        $columna = $this->getColumnLetters();
        $filaImpresion = 9;
        $filaImpresionNombreColumna = $filaImpresion - 1;
        
        // =============================================
        // 📝 7. ENCABEZADOS
        // =============================================
        
        // PRODUCTO
        $worksheet->setCellValue('A' . ($filaImpresionNombreColumna - 1), 'PRODUCTO');
        $worksheet->mergeCells('A7:A8');
        $worksheet->getColumnDimension('A')->setWidth(40);
        $worksheet->getStyle('A7:A8')->getAlignment()->setHorizontal('center');
        $worksheet->getStyle('A7:A8')->getAlignment()->setVertical('center');
        
        // SALDO INICIAL
        $worksheet->setCellValue('B' . ($filaImpresionNombreColumna - 1), 'SALDO INICIAL');
        $worksheet->mergeCells('B7:B8');
        $worksheet->getColumnDimension('B')->setWidth(15);
        $worksheet->getStyle('B7:B8')->getAlignment()->setHorizontal('center');
        $worksheet->getStyle('B7:B8')->getAlignment()->setVertical('center');
        
        // AUMENTOS
        $colActual = 2;
        $colInicioAumentos = 2;
        
        if (count($tiposConD) > 0) {
            $worksheet->setCellValue($columna[$colInicioAumentos] . '7', 'AUMENTOS');
            $worksheet->mergeCells($columna[$colInicioAumentos] . '7:' . $columna[$colInicioAumentos + count($tiposConD) - 1] . '7');
            $worksheet->getStyle($columna[$colInicioAumentos] . '7:' . $columna[$colInicioAumentos + count($tiposConD) - 1] . '7')
                ->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
            $worksheet->getStyle($columna[$colInicioAumentos] . '7')->getFont()->setBold(true);
            
            $colDetalle = $colInicioAumentos;
            foreach ($tiposConD as $idTipo) {
                $worksheet->setCellValue($columna[$colDetalle] . '8', $tiposOperacionDetalle[$idTipo]);
                $worksheet->getColumnDimension($columna[$colDetalle])->setWidth(15);
                $worksheet->getStyle($columna[$colDetalle] . '8')
                    ->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
                $worksheet->getStyle($columna[$colDetalle] . '8')->getFont()->setBold(true);
                $colDetalle++;
            }
            $colActual = $colDetalle - 1;
        }
        
        // DISMINUCIONES
        $colInicioDisminuciones = $colActual + 1;
        
        if (count($tiposConH) > 0) {
            $worksheet->setCellValue($columna[$colInicioDisminuciones] . '7', 'DISMINUCIONES');
            $worksheet->mergeCells($columna[$colInicioDisminuciones] . '7:' . $columna[$colInicioDisminuciones + count($tiposConH) - 1] . '7');
            $worksheet->getStyle($columna[$colInicioDisminuciones] . '7:' . $columna[$colInicioDisminuciones + count($tiposConH) - 1] . '7')
                ->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
            $worksheet->getStyle($columna[$colInicioDisminuciones] . '7')->getFont()->setBold(true);
            
            $colDetalle = $colInicioDisminuciones;
            foreach ($tiposConH as $idTipo) {
                $worksheet->setCellValue($columna[$colDetalle] . '8', $tiposOperacionDetalle[$idTipo]);
                $worksheet->getColumnDimension($columna[$colDetalle])->setWidth(15);
                $worksheet->getStyle($columna[$colDetalle] . '8')
                    ->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
                $worksheet->getStyle($columna[$colDetalle] . '8')->getFont()->setBold(true);
                $colDetalle++;
            }
            $colActual = $colDetalle - 1;
        }
        
        // SALDO FINAL
        $colSaldoFinal = $colActual + 1;
        $worksheet->setCellValue($columna[$colSaldoFinal] . ($filaImpresionNombreColumna - 1), 'SALDO FINAL');
        $worksheet->mergeCells($columna[$colSaldoFinal] . '7:' . $columna[$colSaldoFinal] . '8');
        $worksheet->getColumnDimension($columna[$colSaldoFinal])->setWidth(15);
        $worksheet->getStyle($columna[$colSaldoFinal] . '7:' . $columna[$colSaldoFinal] . '8')
            ->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
        $worksheet->getStyle($columna[$colSaldoFinal] . '7')->getFont()->setBold(true);
        
        // =============================================
        // 📊 8. IMPRIMIR DATOS
        // =============================================
        $filaActual = $filaImpresion;
        $productosConDatos = 0;
        
        foreach ($productos as $producto) {
            $idProducto = $producto->IdProducto;
            $data = $datosProductos[$idProducto];
            
            $saldoInicial = $data['saldo_inicial_D'] - $data['saldo_inicial_H'];
            
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
                $worksheet->setCellValue('A' . $filaActual, $data['descripcion']);
                $worksheet->setCellValue('B' . $filaActual, $saldoInicial);
                
                // AUMENTOS
                $colDetalle = $colInicioAumentos;
                foreach ($tiposConD as $idTipo) {
                    $worksheet->setCellValue($columna[$colDetalle] . $filaActual, $data['movimientos'][$idTipo]['D']);
                    $colDetalle++;
                }
                
                // DISMINUCIONES
                $colDetalle = $colInicioDisminuciones;
                foreach ($tiposConH as $idTipo) {
                    $worksheet->setCellValue($columna[$colDetalle] . $filaActual, $data['movimientos'][$idTipo]['H']);
                    $colDetalle++;
                }
                
                // SALDO FINAL
                $saldoFinal = $saldoInicial;
                foreach ($tiposConD as $idTipo) {
                    $saldoFinal += $data['movimientos'][$idTipo]['D'];
                }
                foreach ($tiposConH as $idTipo) {
                    $saldoFinal -= $data['movimientos'][$idTipo]['H'];
                }
                $worksheet->setCellValue($columna[$colSaldoFinal] . $filaActual, $saldoFinal);
                
                $filaActual++;
                $productosConDatos++;
            }
        }
        
        if ($productosConDatos == 0) {
            $worksheet->setCellValue('A' . $filaImpresion, 'No hay productos con movimiento en el período seleccionado');
            $filaActual++;
        }
        
        // =============================================
        // 🎨 9. FORMATOS
        // =============================================
        $ultimaFila = $filaActual - 1;
        
        if ($ultimaFila >= 9) {
            // BORDES
            $worksheet->getStyle('A7:' . $columna[$colSaldoFinal] . $ultimaFila)
                ->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                    ]
                ]);
            
            // FORMATO NÚMEROS
            $worksheet->getStyle('B9:' . $columna[$colSaldoFinal] . $ultimaFila)
                ->getNumberFormat()->setFormatCode(' #,##0.00 ;(#,##0.00)');
            $worksheet->getStyle('B9:' . $columna[$colSaldoFinal] . $ultimaFila)
                ->getAlignment()->setHorizontal('right');
            
            // NEGATIVOS EN ROJO
            for ($fila = 9; $fila <= $ultimaFila; $fila++) {
                for ($col = 1; $col <= $colSaldoFinal; $col++) {
                    $celda = $columna[$col] . $fila;
                    $valor = $worksheet->getCell($celda)->getValue();
                    if (is_numeric($valor) && $valor < 0) {
                        $worksheet->getStyle($celda)
                            ->getFont()
                            ->setColor(new Color(Color::COLOR_RED));
                    }
                }
            }
            
            // ESTILOS ENCABEZADOS
            $styleHeader = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE0E0E0']
                ],
                'font' => ['bold' => true]
            ];
            
            $worksheet->getStyle('A7:B8')->applyFromArray($styleHeader);
            
            if (count($tiposConD) > 0) {
                $worksheet->getStyle($columna[$colInicioAumentos] . '7:' . $columna[$colInicioAumentos + count($tiposConD) - 1] . '8')
                    ->applyFromArray($styleHeader);
            }
            
            if (count($tiposConH) > 0) {
                $worksheet->getStyle($columna[$colInicioDisminuciones] . '7:' . $columna[$colInicioDisminuciones + count($tiposConH) - 1] . '8')
                    ->applyFromArray($styleHeader);
            }
            
            $worksheet->getStyle($columna[$colSaldoFinal] . '7:' . $columna[$colSaldoFinal] . '8')
                ->applyFromArray($styleHeader);
            
            $worksheet->freezePane('A9');
        }
        
        // =============================================
        // 💾 10. SALIDA
        // =============================================
        $nombreArchivo = 'Inventario_TipoOperacion_' . date('Ymd') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }
    
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