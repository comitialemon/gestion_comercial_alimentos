<?php

namespace App\Http\Controllers\Gestion\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Inertia\Inertia;

class ListaPreciosController extends Controller
{
    /**
     * Muestra la vista previa del reporte
     */
    public function index()
    {
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first(['Nombre', 'NIT']);

        return Inertia::render('Gestion/Reportes/ListaPrecios', [
            'empresa' => $empresa
        ]);
    }
    
    /**
     * Muestra la vista del reporte por sucursal actual
     */
    public function indexSucursal()
    {
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first(['Nombre', 'NIT']);

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', session('cliente_id'))
            ->where('IdClienteSucursal', session('cliente_sucursal_id'))
            ->first(['Nombre as nombre']);

        return Inertia::render('Gestion/Reportes/ListaPreciosSucursal', [
            'empresa' => $empresa,
            'sucursal' => $sucursal
        ]);
    }

    /**
     * Exporta el reporte SOLO de la sucursal logueada (VERSIÓN SIMPLIFICADA)
     */
    public function exportarPorSucursal()
    {
        set_time_limit(0);
        
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // Obtener datos de la sucursal logueada
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre as nombre']);
        
        if (!$sucursal) {
            return redirect()->back()->with('error', 'No se encontró la sucursal seleccionada');
        }
        
        // ==========================================
        // INICIA EXPORTACION EXCEL
        // ==========================================
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        
        // ==========================================
        // CABECERA DEL REPORTE
        // ==========================================
        $datosCabecera = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);
        
        $nombreEmpresa = $datosCabecera->Nombre ?? '';
        $nitEmpresa = $datosCabecera->NIT ?? '';
        
        $worksheet->setCellValue('A1', $nombreEmpresa);
        $worksheet->setCellValue('A2', 'NIT: ' . $nitEmpresa);
        $worksheet->setCellValue('A3', 'LISTA DE PRECIOS - BOLIVIANOS');
        $worksheet->setCellValue('A4', 'SUCURSAL: ' . ($sucursal->nombre ?? 'No especificada'));
        $worksheet->setCellValue('A5', 'Fecha: ' . date('d/m/Y H:i:s'));
        
        // ==========================================
        // ENCABEZADOS DE TABLA (fila 7)
        // ==========================================
        $worksheet->setCellValue('A7', 'PRODUCTO');
        $worksheet->setCellValue('B7', 'PRECIO GENERAL');
        $worksheet->setCellValue('C7', 'PRECIO SUCURSAL');
        
        // Estilo para encabezados
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '61131a'],
            ],
            'font' => ['color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        
        $worksheet->getStyle('A7:C7')->applyFromArray($headerStyle);
        
        // ==========================================
        // LISTA DE PRODUCTOS (solo nombre y precios)
        // ==========================================
        $fila = 8;
        
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto', 'Detalle', 'PrecioVenta']);
        
        foreach ($productos as $prod) {
            $idDetalleProducto = $prod->IdDetalleProducto;
            $detalle = $prod->Detalle;
            $precioVentaGeneral = $prod->PrecioVenta;
            
            // Precio diferenciado sucursal
            $precioSucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_preciosucursal')
                ->where('IdSucursal', $sucursalId)
                ->where('IdProducto', $idDetalleProducto)
                ->value('Precio');
            
            $worksheet->setCellValue('A' . $fila, $detalle);
            $worksheet->setCellValue('B' . $fila, $precioVentaGeneral);
            $worksheet->setCellValue('C' . $fila, $precioSucursal);
            
            $fila++;
        }
        
        // ==========================================
        // FORMATO DE PRECIOS
        // ==========================================
        $worksheet->getStyle('B8:C' . ($fila - 1))
            ->getNumberFormat()
            ->setFormatCode('"Bs. "#,##0.00');
        
        // ==========================================
        // AJUSTAR ANCHO DE COLUMNAS
        // ==========================================
        $worksheet->getColumnDimension('A')->setWidth(50);  // Producto
        $worksheet->getColumnDimension('B')->setWidth(18);  // Precio General
        $worksheet->getColumnDimension('C')->setWidth(18);  // Precio Sucursal
        
        // ==========================================
        // BORDES A TODA LA TABLA
        // ==========================================
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
        
        if ($fila > 8) {
            $worksheet->getStyle('A7:C' . ($fila - 1))->applyFromArray($borderStyle);
        }
        
        // ==========================================
        // DESCARGA DEL ARCHIVO
        // ==========================================
        $nombreArchivo = 'ListaDePrecios_' . str_replace(' ', '_', $sucursal->nombre) . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit();
    }

    /**
     * Obtener letra de columna para un número (ej: 1->A, 27->AA)
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

    /**
     * Exporta el reporte a Excel (misma lógica que Scriptcase)
     */
    public function exportar()
    {
        set_time_limit(0);
        
        // ==========================================
        // INICIA EXPORTACION EXCEL
        // ==========================================
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        
        // ==========================================
        // CABECERA
        // ==========================================
        $datosCabecera = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first(['Nombre', 'NIT', 'Direccion']);
        
        $nombreEmpresa = $datosCabecera->Nombre ?? '';
        $nitEmpresa = $datosCabecera->NIT ?? '';
        $direccionEmpresa = $datosCabecera->Direccion ?? '';
        
        $worksheet->setCellValue('A1', $nombreEmpresa);
        $worksheet->setCellValue('A2', 'NIT : ' . $nitEmpresa);
        $worksheet->setCellValue('A3', 'INFORME LISTA DE PRECIOS EN BOLIVIANOS');
        
        // ==========================================
        // ARRAY DE LETRAS DE COLUMNAS
        // ==========================================
        $columna = $this->getColumnLetters();
        
        // ==========================================
        // INICIA ENCABEZADO CONTROL DE PRECIOS POR SUCURSAL
        // ==========================================
        $fila = 0;
        $columnaLetra = 3; // Empieza en columna D (índice 3)
        $columnaLetraInicial = 3;
        
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', session('cliente_id'))
            ->where('ActivoInactivo', 0)
            ->orderBy('IdClienteSucursal')
            ->get();
        
        $controlSucursal = [];
        
        foreach ($sucursales as $suc) {
    $idSucursal = $suc->IdClienteSucursal;
    $nombreSucursal = $suc->Nombre;
    
    $controlSucursal[$fila][0] = $idSucursal;
    
    $worksheet->setCellValue($columna[$columnaLetra] . '8', $nombreSucursal);
    
    // ==========================================
    // INICIO ENCABEZADO MAYORISTA SUCURSAL (CORREGIDO)
    // ==========================================
    $mayoristas = DB::connection('mysql_gestion_comercial_alimentos')
        ->table('inventario_relacion_ventainventario_preciomayorista')
        ->select('IdIdentificador')
        ->where('IdCliente', session('cliente_id'))
        ->where('IdSucursal', $idSucursal)
        ->groupBy('IdIdentificador')
        ->get();  // ← SIN orderBy
    
    foreach ($mayoristas as $may) {
            $idIdentificador = $may->IdIdentificador;
            
            $nombreMayorista = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('IdIdentificador', $idIdentificador)
                ->value('Nombre');
            
            $columnaLetra++;
            $worksheet->setCellValue($columna[$columnaLetra] . '9', $nombreMayorista);
        }
        
        $fila++;
    }
        
        // ==========================================
        // FORMATO DE CELDAS (BORDES Y ALINEACIÓN)
        // ==========================================
        $styleBorde = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        
        $alignCenter = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        
        $columnaLetra--; // Se resta solo para borde
        
        // Alineación títulos columnas
        $worksheet->getStyle('B8:' . $columna[$columnaLetra] . '9')->applyFromArray($styleBorde);
        $worksheet->getStyle('B8:' . $columna[$columnaLetra] . '9')->getAlignment()->setWrapText(true);
        $worksheet->getStyle('B8:G9')->applyFromArray($alignCenter);
        
        // Dimensión de columnas
        $worksheet->getColumnDimension('B')->setAutoSize(true);
        $worksheet->getColumnDimension('D')->setWidth(8);
        
        // Formato numérico de las columnas numéricas
        $worksheet->getStyle('C:' . $columna[$columnaLetra])->getNumberFormat()->setFormatCode(' ##0.00  ;(##0.00)');
        
        // Inmovilizar encabezado (línea 9)
        $worksheet->freezePane('C10');
        
        // ==========================================
        // TÍTULOS
        // ==========================================
        $worksheet->setCellValue('B8', 'DETALLE');
        $worksheet->setCellValue('C8', 'PRECIO GENERAL');
        
        // ==========================================
        // INICIO LISTA DE PRECIOS DE PRODUCTOS
        // ==========================================
        $fila = 10;
        
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario')
            ->where('IdCliente', session('cliente_id'))
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto', 'Detalle', 'PrecioVenta']);
        
        foreach ($productos as $prod) {
            $idDetalleProducto = $prod->IdDetalleProducto;
            $detalle = $prod->Detalle;
            $precioVentaGeneral = $prod->PrecioVenta;
            
            $worksheet->setCellValue('B' . $fila, $detalle);
            $worksheet->setCellValue('C' . $fila, $precioVentaGeneral);
            
            // ==========================================
            // INICIO ANALIZA PRECIO DIFERENCIADO SUCURSAL
            // ==========================================
            $columnaLetra = 3;
            
            foreach ($sucursales as $suc) {
                $idSucursal = $suc->IdClienteSucursal;
                
                // Precio diferenciado sucursal
                $precioSucursal = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_preciosucursal')
                    ->where('IdSucursal', $idSucursal)
                    ->where('IdProducto', $idDetalleProducto)
                    ->value('Precio');
                
                $worksheet->setCellValue($columna[$columnaLetra] . $fila, $precioSucursal);
                
                // INICIO PRECIO MAYORISTA SUCURSAL
                $preciosMayorista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_preciomayorista')
                    ->where('IdSucursal', $idSucursal)
                    ->where('IdProducto', $idDetalleProducto)
                    ->orderBy('IdPrecioMayorista')
                    ->get();
                
                foreach ($preciosMayorista as $pm) {
                    $precioMayorista = $pm->Precio;
                    $columnaLetra++;
                    $worksheet->setCellValue($columna[$columnaLetra] . $fila, $precioMayorista);
                }
                // FINAL PRECIO MAYORISTA SUCURSAL
                
                $columnaLetra++;
            }
            // FIN ANALIZA PRECIO DIFERENCIADO SUCURSAL
            
            $fila++;
        }
        
        // ==========================================
        // DESCARGA DEL ARCHIVO
        // ==========================================
        $nombreArchivo = 'ListaDePrecios.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit();
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