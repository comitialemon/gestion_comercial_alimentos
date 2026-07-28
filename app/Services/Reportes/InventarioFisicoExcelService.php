<?php

namespace App\Services\Reportes;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;  // ✅ Agregar este import

class InventarioFisicoExcelService
{
    /**
     * Genera el archivo Excel de lista de productos para inventario físico
     */
    public function generar($clienteId, $sucursalId)
    {
        // =============================================
        // 1. OBTENER DATOS
        // =============================================
        
        // Nombre de la sucursal
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);
        
        $nombreSucursal = $sucursal->Nombre ?? $sucursalId;

        // Obtener productos activos e inactivos con saldo
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle as p')
            ->leftJoin('inventario_propiamente as ip', function($join) use ($clienteId, $sucursalId) {
                $join->on('p.IdProducto', '=', 'ip.IdProducto')
                     ->where('ip.IdCliente', '=', $clienteId)
                     ->where('ip.IdSucursal', '=', $sucursalId);
            })
            ->where('p.IdCliente', $clienteId)
            ->select(
                'p.IdProducto',
                'p.Descripcion',
                'p.ActivoInactivo',
                DB::raw('COALESCE(SUM(CASE WHEN ip.D_H = "D" THEN ip.Unidades ELSE -ip.Unidades END), 0) as saldo')
            )
            ->groupBy('p.IdProducto', 'p.Descripcion', 'p.ActivoInactivo')
            ->orderBy('p.Descripcion', 'ASC')
            ->get();

        // Filtrar productos según regla: Inactivos solo si saldo != 0
        $productosFiltrados = $productos->filter(function($producto) {
            // Si es inactivo (ActivoInactivo = 1) y saldo = 0, NO mostrar
            if ($producto->ActivoInactivo == 1 && $producto->saldo == 0) {
                return false;
            }
            // Mostrar activos (ActivoInactivo = 0) siempre
            return true;
        });

        // =============================================
        // 2. CREAR EXCEL CON PHPSPREADSHEET
        // =============================================
        
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // ---------- CABECERA ----------
        $worksheet
            ->setCellValue('A1', 'INVENTARIO FÍSICO')
            ->setCellValue('A2', $nombreSucursal)
            ->setCellValue('A3', date('d/m/Y H:i:s'))
            ->mergeCells('A1:F1')
            ->mergeCells('A2:F2')
            ->mergeCells('A3:F3');

        $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $worksheet->getStyle('A1')->getAlignment()->setHorizontal('center');
        $worksheet->getStyle('A2')->getFont()->setBold(true)->setSize(14);

        // ---------- ENCABEZADOS DE TABLA ----------
        $worksheet
            ->setCellValue('A5', 'N°')
            ->setCellValue('B5', 'ESTADO')
            ->setCellValue('C5', 'DESCRIPCIÓN')
            ->setCellValue('D5', 'INVENTARIO LIBROS')
            ->setCellValue('E5', 'INVENTARIO FISICO')
            ->setCellValue('F5', 'DIFERENCIA');

        // Estilo encabezados
        $worksheet->getStyle('A5:F5')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF0070C0']
            ],
            'alignment' => [
                'horizontal' => 'center',
                'vertical' => 'center'
            ]
        ]);

        $worksheet->getStyle('A5:F5')->getBorders()->getAllBorders()->setBorderStyle('thin');

        // Congelar encabezados (freeze pane)
        $worksheet->freezePane('A6');

        // ---------- DATOS ----------
        $fila = 6;
        $item = 1;

        foreach ($productosFiltrados as $producto) {
            $esActivo = ($producto->ActivoInactivo == 0);
            $esInactivo = ($producto->ActivoInactivo == 1);
            $saldo = (float) $producto->saldo;
            $estado = $esActivo ? 'ACTIVO' : 'INACTIVO';

            // Escribir datos
            $worksheet
                ->setCellValue('A' . $fila, $item)
                ->setCellValue('B' . $fila, $estado)
                ->setCellValue('C' . $fila, $producto->Descripcion)
                ->setCellValue('D' . $fila, $saldo)
                ->setCellValue('E' . $fila, 0)  // Inventario físico por defecto 0
                ->setCellValue('F' . $fila, '=E' . $fila . '-D' . $fila);

            // Configurar fuente
            $worksheet->getStyle('A' . $fila . ':F' . $fila)->getFont()->setSize(10);
            $worksheet->getStyle('A' . $fila)->getFont()->setBold(true);
            $worksheet->getStyle('A' . $fila)->getAlignment()->setHorizontal('center');

            // ---------- COLORES SEGÚN ESTADO ----------
            if ($esInactivo) {
                // PRODUCTO INACTIVO: Columnas A, B, C en ROJO
                $colorRojo = 'FFFF0000';
                $worksheet->getStyle('A' . $fila)->getFont()->getColor()->setARGB($colorRojo);
                $worksheet->getStyle('B' . $fila)->getFont()->getColor()->setARGB($colorRojo);
                $worksheet->getStyle('C' . $fila)->getFont()->getColor()->setARGB($colorRojo);
                // Columna E (INVENTARIO FISICO) siempre NEGRO
                $worksheet->getStyle('E' . $fila)->getFont()->getColor()->setARGB('FF000000');
            } else {
                // PRODUCTO ACTIVO: Estado en VERDE
                $worksheet->getStyle('B' . $fila)->getFont()->getColor()->setARGB('FF007F00');
            }

            // Columna D (INVENTARIO LIBROS): ROJO si saldo negativo
            if ($saldo < 0) {
                $worksheet->getStyle('D' . $fila)->getFont()->getColor()->setARGB('FFFF0000');
            } else {
                $worksheet->getStyle('D' . $fila)->getFont()->getColor()->setARGB('FF000000');
            }

            // Columna E (INVENTARIO FISICO) siempre NEGRO (para activos ya está, pero lo aseguramos)
            if (!$esInactivo) {
                $worksheet->getStyle('E' . $fila)->getFont()->getColor()->setARGB('FF000000');
            }

            // ---------- FORMATOS DE ALINEACIÓN Y NUMÉRICOS ----------
            $worksheet->getStyle('B' . $fila)->getAlignment()->setHorizontal('center');
            $worksheet->getStyle('D' . $fila)->getAlignment()->setHorizontal('right');
            $worksheet->getStyle('D' . $fila)->getNumberFormat()->setFormatCode('#,##0.0000');

            $worksheet->getStyle('E' . $fila)->getAlignment()->setHorizontal('right');
            $worksheet->getStyle('E' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');

            $worksheet->getStyle('F' . $fila)->getAlignment()->setHorizontal('right');
            $worksheet->getStyle('F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');

            // Fondo para columna DIFERENCIA
            $worksheet->getStyle('F' . $fila)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF2F2F2');

            // Bordes
            $worksheet->getStyle('A' . $fila . ':F' . $fila)->getBorders()->getBottom()->setBorderStyle('dotted');
            $worksheet->getStyle('A' . $fila . ':F' . $fila)->getBorders()->getLeft()->setBorderStyle('thin');
            $worksheet->getStyle('A' . $fila . ':F' . $fila)->getBorders()->getRight()->setBorderStyle('thin');

            $worksheet->getRowDimension($fila)->setRowHeight(20);
            $worksheet->getStyle('C' . $fila)->getAlignment()->setWrapText(true);

            $item++;
            $fila++;
        }

        // ---------- MENSAJE SI NO HAY PRODUCTOS ----------
        if ($item == 1) {
            $worksheet
                ->setCellValue('A' . $fila, 'No hay productos que cumplan los criterios de visualización')
                ->mergeCells('A' . $fila . ':F' . $fila)
                ->getStyle('A' . $fila)
                ->getFont()
                ->setBold(true)
                ->setSize(11);
            $worksheet->getStyle('A' . $fila)->getAlignment()->setHorizontal('center');
            $fila++;
        }

        // ---------- ANCHO DE COLUMNAS ----------
        $worksheet->getColumnDimension('A')->setWidth(8);
        $worksheet->getColumnDimension('B')->setWidth(10);
        $worksheet->getColumnDimension('C')->setWidth(50);
        $worksheet->getColumnDimension('D')->setWidth(18);
        $worksheet->getColumnDimension('E')->setWidth(18);
        $worksheet->getColumnDimension('F')->setWidth(15);

        // ---------- FORMATO CONDICIONAL PARA DIFERENCIA ----------
        if ($item > 1) {
            $condicionales = [];

            // Negativo: ROJO
            $negativo = new Conditional();
            $negativo->setConditionType(Conditional::CONDITION_CELLIS);
            $negativo->setOperatorType(Conditional::OPERATOR_LESSTHAN);
            $negativo->addCondition('0');
            $negativo->getStyle()->getFont()->getColor()->setARGB('FFFF0000');
            $negativo->getStyle()->getFont()->setBold(true);
            $condicionales[] = $negativo;

            // Positivo: NEGRO
            $positivo = new Conditional();
            $positivo->setConditionType(Conditional::CONDITION_CELLIS);
            $positivo->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
            $positivo->addCondition('0');
            $positivo->getStyle()->getFont()->getColor()->setARGB('FF000000');
            $positivo->getStyle()->getFont()->setBold(true);
            $condicionales[] = $positivo;

            // Cero: NEGRO
            $cero = new Conditional();
            $cero->setConditionType(Conditional::CONDITION_CELLIS);
            $cero->setOperatorType(Conditional::OPERATOR_EQUAL);
            $cero->addCondition('0');
            $cero->getStyle()->getFont()->getColor()->setARGB('FF000000');
            $cero->getStyle()->getFont()->setBold(true);
            $condicionales[] = $cero;

            $worksheet->getStyle('F6:F' . ($fila - 1))->setConditionalStyles($condicionales);
        }

        // ---------- INSTRUCCIONES ----------
        $instruccionesFila = $fila;
        $worksheet->setCellValue('A' . $fila, 'INSTRUCCIONES:');
        $worksheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(11);
        $fila++;

        $instrucciones = [
            '1. La columna "INVENTARIO FISICO" viene con valor 0.00 por defecto (SIEMPRE en NEGRO)',
            '2. Modifique los valores según su conteo físico (2 decimales)',
            '3. La "DIFERENCIA" se calcula automáticamente: Físico - Libros',
            '4. Diferencias NEGATIVAS: ROJO (faltantes) | Diferencias POSITIVAS: NEGRO (sobrantes)'
        ];

        foreach ($instrucciones as $texto) {
            $worksheet->setCellValue('A' . $fila, $texto)
                ->mergeCells('A' . $fila . ':F' . $fila);
            $fila++;
        }

        // Estilo para instrucciones
        $worksheet->getStyle('A' . $instruccionesFila . ':F' . ($fila - 1))->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF2F2F2');

        $worksheet->getStyle('A' . $instruccionesFila . ':F' . ($fila - 1))->getBorders()
            ->getOutline()->setBorderStyle('thin');

        // ---------- NOTA SOBRE PRODUCTOS Y COLORES ----------
        $worksheet->setCellValue('A' . $fila, 'NOTA SOBRE PRODUCTOS Y COLORES:');
        $worksheet->getStyle('A' . $fila)->getFont()->setBold(true)->setSize(11);
        $fila++;

        $notas = [
            ['- Productos ACTIVOS: Se muestran todos (incluso con saldo 0) - Estado en VERDE', 'FF007F00'],
            ['- Productos INACTIVOS: Solo si tienen saldo ≠ 0 - Columnas A, B, C en ROJO', 'FFFF0000'],
            ['- INVENTARIO FISICO (E): SIEMPRE en NEGRO para facilitar la escritura', 'FF000000'],
            ['- DIFERENCIA (F): ROJO si negativa | NEGRO si positiva o cero', 'FF000000'],
        ];

        foreach ($notas as [$texto, $color]) {
            $worksheet->setCellValue('A' . $fila, $texto)
                ->mergeCells('A' . $fila . ':F' . $fila);
            $worksheet->getStyle('A' . $fila)->getFont()->getColor()->setARGB($color);
            $fila++;
        }

        // =============================================
        // CONFIGURACIÓN PARA IMPRESIÓN
        // =============================================
        $worksheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)  // ✅ Constante INT
            ->setFitToWidth(1);

        $worksheet->getPageMargins()
            ->setTop(0.75)
            ->setRight(0.5)
            ->setLeft(0.5)
            ->setBottom(0.75);

        $worksheet->getPageSetup()->setPrintArea('A1:F' . ($fila - 1));

        // =============================================
        // EXPORTAR
        // =============================================
        $nombreArchivo = 'Inventario_' . str_replace(' ', '_', $nombreSucursal) . '_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        header('Expires: 0');
        header('Pragma: public');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    }
}