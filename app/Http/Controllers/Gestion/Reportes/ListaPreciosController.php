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