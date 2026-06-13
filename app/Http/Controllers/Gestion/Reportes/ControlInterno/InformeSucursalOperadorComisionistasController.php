<?php

namespace App\Http\Controllers\Gestion\Reportes\ControlInterno;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Inertia\Inertia;

class InformeSucursalOperadorComisionistasController extends Controller
{
    /**
     * Muestra el formulario del reporte
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        
        // 🔥 OBTENER TODAS LAS SUCURSALES DEL CLIENTE (sin filtrar por sesión)
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // Obtener fechas disponibles
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha', 'Fecha', DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y') as fecha_formateada")]);
        
        return Inertia::render('Gestion/Reportes/ControlInterno/InformeSucursalOperadorComisionistas', [
            'sucursales' => $sucursales,
            'fechas' => $fechas,
        ]);
    }
    
    /**
     * Exporta el reporte a Excel
     */
    public function exportar(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date',
            'sucursal_id' => 'required|integer|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);
        
        $clienteId = session('cliente_id');
        $fecha = $request->fecha;
        $sucursalId = $request->sucursal_id;
        
        $fechaMostrar = date('d-m-Y', strtotime($fecha));
        
        // 🔥 OBTENER NOMBRE DE LA SUCURSAL SELECCIONADA PARA EL TÍTULO
        $nombreSucursalSeleccionada = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->value('Nombre');
        
        // Obtener ID de fecha para egresos
        $idFecha = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', $fecha)
            ->value('IdFecha');
        
        // Datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion']);
        
        // ==========================================
        // CREAR EXCEL
        // ==========================================
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        
        $columnas = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AY', 'AZ'];
        
        // ==========================================
        // CABECERA (con nombre de sucursal)
        // ==========================================
        $worksheet->setCellValue('A1', $this->toExcelString($empresa->Nombre ?? ''));
        $worksheet->setCellValue('A2', 'NIT : ' . ($empresa->NIT ?? ''));
        $worksheet->setCellValue('A3', 'INFORME LIQUIDACION VENTAS POR OPERADOR');
        $worksheet->setCellValue('A4', 'Sucursal: ' . $this->toExcelString($nombreSucursalSeleccionada) . ' - Fecha: ' . $fechaMostrar);
        $worksheet->setCellValue('A5', '(Expresado en Bolivianos)');
        
        // ==========================================
        // MATRIZ DE CONTROL: OPERADORES DE LA SUCURSAL SELECCIONADA
        // ==========================================
        $controlOperadorSucursal = [];
        $fila = 0;
        
        // Obtener datos de la sucursal seleccionada
        $sucursalData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['IdClienteSucursal', 'Nombre']);
        
        if ($sucursalData) {
            $idSucursal = $sucursalData->IdClienteSucursal;
            $nombreSucursal = $sucursalData->Nombre;
            
            // Obtener operadores que hicieron ventas en esa sucursal en la fecha seleccionada
            $operadoresVenta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $idSucursal)
                ->whereDate('FechaVenta', $fecha)
                ->where('ActivoInactivo', 1)
                ->groupBy('IdOperadorIngresa')
                ->orderBy('IdOperadorIngresa')
                ->pluck('IdOperadorIngresa');
            
            $idControlOperador = 0;
            foreach ($operadoresVenta as $idOperador) {
                if ($idControlOperador != $idOperador) {
                    $nombreOperador = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('todos_operador')
                        ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                        ->where('todos_operador.IdOperador', $idOperador)
                        ->value('todos_identificador.Nombre');
                    
                    $controlOperadorSucursal[$fila] = [
                        'id_sucursal' => $idSucursal,
                        'nombre_sucursal' => $nombreSucursal,
                        'id_operador' => $idOperador,
                        'nombre_operador' => $nombreOperador ?? 'Sin nombre'
                    ];
                    $idControlOperador = $idOperador;
                    $fila++;
                }
            }
        }
        
        $totalOperadores = count($controlOperadorSucursal);
        
        // Si no hay operadores, mostrar un mensaje
        if ($totalOperadores == 0) {
            $worksheet->setCellValue('A10', 'No hay ventas registradas para esta fecha y sucursal');
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment;filename="SinVentas.xls"');
            header('Cache-Control: max-age=0');
            $writer = new Xls($spreadsheet);
            $writer->save('php://output');
            exit();
        }
        
        $columnaArrayOperador = 2;
        
        // ==========================================
        // TÍTULOS DE COLUMNAS
        // ==========================================
        $worksheet->setCellValue('B8', 'DETALLE');
        $worksheet->mergeCells('B8:B9');
        
        $posicionInicialX = 'C';
        $auxiliarColumnaNombreSucursal = '';
        $control = 0;
        
        for ($fi = 0; $fi < $totalOperadores; $fi++) {
            $worksheet->setCellValue($columnas[$columnaArrayOperador] . '8', $this->toExcelString($controlOperadorSucursal[$fi]['nombre_sucursal']));
            $worksheet->setCellValue($columnas[$columnaArrayOperador] . '9', $this->toExcelString($controlOperadorSucursal[$fi]['nombre_operador']));
            $letraFinal = $columnas[$columnaArrayOperador];
            
            if (strcasecmp($controlOperadorSucursal[$fi]['nombre_sucursal'], $auxiliarColumnaNombreSucursal) !== 0) {
                $auxiliarColumnaNombreSucursal = $controlOperadorSucursal[$fi]['nombre_sucursal'];
                if ($control == 1) {
                    $letraFinal = $columnas[$columnaArrayOperador - 1];
                    $worksheet->mergeCells($posicionInicialX . '8:' . $letraFinal . '8');
                    $posicionInicialX = $columnas[$columnaArrayOperador];
                } else {
                    $control = 1;
                }
            }
            $columnaArrayOperador++;
        }
        
        $worksheet->setCellValue($columnas[$columnaArrayOperador] . '8', 'TOTAL');
        $worksheet->mergeCells($posicionInicialX . '8:' . $letraFinal . '8');
        $worksheet->mergeCells($columnas[$columnaArrayOperador] . '8:' . $columnas[$columnaArrayOperador] . '9');
        
        // ==========================================
        // FORMATO DE TÍTULOS
        // ==========================================
        $alignCenter = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        
        $titleStyle = [
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'font' => ['bold' => true, 'size' => 15]
        ];
        $subTitleStyle = [
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'font' => ['bold' => true, 'size' => 8]
        ];
        
        // Combinar títulos principales
        $worksheet->mergeCells('A1:' . $columnas[$columnaArrayOperador] . '1');
        $worksheet->mergeCells('A2:' . $columnas[$columnaArrayOperador] . '2');
        $worksheet->mergeCells('A3:' . $columnas[$columnaArrayOperador] . '3');
        $worksheet->mergeCells('A4:' . $columnas[$columnaArrayOperador] . '4');
        $worksheet->mergeCells('A5:' . $columnas[$columnaArrayOperador] . '5');
        
        $worksheet->getStyle('A1')->applyFromArray($titleStyle);
        $worksheet->getStyle('A2')->applyFromArray($subTitleStyle);
        $worksheet->getStyle('A3')->applyFromArray($alignCenter);
        $worksheet->getStyle('A4')->applyFromArray($alignCenter);
        $worksheet->getStyle('A5')->applyFromArray($alignCenter);
        
        // Alineación de títulos de columnas
        $worksheet->getStyle('B8')->applyFromArray($alignCenter);
        $worksheet->getStyle('C8:' . $columnas[$columnaArrayOperador] . '9')->applyFromArray($alignCenter);
        
        $columnaArrayAux = $columnaArrayOperador - 1;
        $letraAuxiliar = $columnas[$columnaArrayAux];
        $worksheet->getStyle('C9:' . $letraAuxiliar . '9')->getAlignment()->setWrapText(true);
        
        // ==========================================
        // VENTAS
        // ==========================================
        $filaVentas = 10;
        $columnaArrayVentas = 2;
        
        $worksheet->setCellValue('B' . $filaVentas, 'Ventas sin Descuentos');
        $worksheet->setCellValue('B' . ($filaVentas + 2), 'Ventas Brutas');
        
        for ($fi = 0; $fi < $totalOperadores; $fi++) {
            $idOperador = $controlOperadorSucursal[$fi]['id_operador'];
            $idSucursalVentas = $controlOperadorSucursal[$fi]['id_sucursal'];
            
            $totalVentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->join('impuestos_ventas_detalle', 'impuestos_ventas.IdVentas', '=', 'impuestos_ventas_detalle.idventas')
                ->where('impuestos_ventas.IdCliente', $clienteId)
                ->where('impuestos_ventas.IdClienteSucursal', $idSucursalVentas)
                ->whereDate('impuestos_ventas.FechaVenta', $fecha)
                ->where('impuestos_ventas.IdOperadorIngresa', $idOperador)
                ->where('impuestos_ventas.ActivoInactivo', 1)
                ->where('impuestos_ventas.IdEstado', 1)
                ->sum('impuestos_ventas_detalle.totalbolivianos');
            
            $worksheet->setCellValue($columnas[$columnaArrayVentas] . $filaVentas, (float) $totalVentas);
            $worksheet->setCellValue($columnas[$columnaArrayVentas] . ($filaVentas + 2), '=+' . $columnas[$columnaArrayVentas] . $filaVentas);
            
            $columnaArrayVentas++;
        }
        
        $worksheet->setCellValue($columnas[$columnaArrayVentas] . $filaVentas, '=SUM(C' . $filaVentas . ':' . $columnas[$columnaArrayVentas - 1] . $filaVentas . ')');
        $worksheet->setCellValue($columnas[$columnaArrayVentas] . ($filaVentas + 2), '=SUM(C' . ($filaVentas + 2) . ':' . $columnas[$columnaArrayVentas - 1] . ($filaVentas + 2) . ')');
        
        // ==========================================
        // CONCEPTOS DE LIQUIDACIÓN
        // ==========================================
        $filaConcepto = $filaVentas + 3;
        
        $conceptos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_liquidacion_concepto')
            ->where('IdCliente', $clienteId)
            ->orderBy('Concepto', 'desc')
            ->get(['Concepto', 'IdCuenta']);
        
        foreach ($conceptos as $concepto) {
            $worksheet->setCellValue('B' . $filaConcepto, $this->toExcelString($concepto->Concepto));
            
            $columnaArrayLiquidacion = 2;
            for ($fi = 0; $fi < $totalOperadores; $fi++) {
                $idOperador = $controlOperadorSucursal[$fi]['id_operador'];
                $idSucursalLiquidacion = $controlOperadorSucursal[$fi]['id_sucursal'];
                
                $totalBolivianos = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion')
                    ->join('impuestos_ventas', 'impuestos_ventas_liquidacion.IdVentas', '=', 'impuestos_ventas.IdVentas')
                    ->where('impuestos_ventas.IdCliente', $clienteId)
                    ->where('impuestos_ventas.IdClienteSucursal', $idSucursalLiquidacion)
                    ->whereDate('impuestos_ventas.FechaVenta', $fecha)
                    ->where('impuestos_ventas.IdOperadorIngresa', $idOperador)
                    ->where('impuestos_ventas.ActivoInactivo', 1)
                    ->where('impuestos_ventas.IdEstado', 1)
                    ->where('impuestos_ventas_liquidacion.IdCuenta', $concepto->IdCuenta)
                    ->sum('impuestos_ventas_liquidacion.Bolivianos');
                
                $worksheet->setCellValue($columnas[$columnaArrayLiquidacion] . $filaConcepto, (float) $totalBolivianos);
                $columnaArrayLiquidacion++;
            }
            $worksheet->setCellValue($columnas[$columnaArrayVentas] . $filaConcepto, '=SUM(C' . $filaConcepto . ':' . $columnas[$columnaArrayVentas - 1] . $filaConcepto . ')');
            $filaConcepto++;
        }
        
        // ==========================================
        // DIFERENCIA EN LIQUIDACIÓN
        // ==========================================
        $filaDiferencia = $filaConcepto;
        $worksheet->setCellValue('B' . $filaDiferencia, 'Diferencia Liquidacion');
        
        $columnaArrayDiferencia = 2;
        for ($fi = 0; $fi < $totalOperadores; $fi++) {
            $worksheet->setCellValue($columnas[$columnaArrayDiferencia] . $filaDiferencia, '=SUM(' . $columnas[$columnaArrayDiferencia] . '13:' . $columnas[$columnaArrayDiferencia] . ($filaDiferencia - 1) . ')-' . $columnas[$columnaArrayDiferencia] . '12');
            $columnaArrayDiferencia++;
        }
        $worksheet->setCellValue($columnas[$columnaArrayVentas] . $filaDiferencia, '=SUM(C' . $filaDiferencia . ':' . $columnas[$columnaArrayVentas - 1] . $filaDiferencia . ')');
        
        // ==========================================
        // APLICAR BORDES
        // ==========================================
        $worksheet->getStyle('B8:' . $columnas[$columnaArrayDiferencia] . '9')->applyFromArray($borderStyle);
        $worksheet->getStyle('B12:' . $columnas[$columnaArrayDiferencia] . '12')->applyFromArray($borderStyle);
        $worksheet->getStyle('B' . $filaDiferencia . ':' . $columnas[$columnaArrayDiferencia] . $filaDiferencia)->applyFromArray($borderStyle);
        
        // ==========================================
        // EGRESOS DEL DÍA (comentado porque da error)
        // ==========================================
        
        // ==========================================
        // PRODUCTOS VENDIDOS (CON CATEGORÍA)
        // ==========================================
        $filaProductoVendido = $filaDiferencia + 3;
        $worksheet->setCellValue('B' . $filaProductoVendido, 'Producto Vendido');
        $worksheet->getStyle('B' . $filaProductoVendido)->applyFromArray($borderStyle);
        $filaProductoVendido++;
        $filaParaSumaTotal = $filaProductoVendido;
        
        $productosVendidos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->join('impuestos_ventas_detalle', 'impuestos_ventas.IdVentas', '=', 'impuestos_ventas_detalle.idventas')
            ->join('inventario_relacion_ventainventario', 'impuestos_ventas_detalle.idrelacionventainventario', '=', 'inventario_relacion_ventainventario.IdDetalleProducto')
            ->leftJoin('inventario_menu_categoria', 'inventario_relacion_ventainventario.id_categoria', '=', 'inventario_menu_categoria.id_categoria')
            ->where('impuestos_ventas.IdCliente', $clienteId)
            ->where('impuestos_ventas.IdClienteSucursal', $sucursalId)
            ->whereDate('impuestos_ventas.FechaVenta', $fecha)
            ->where('impuestos_ventas.ActivoInactivo', 1)
            ->where('impuestos_ventas.IdEstado', 1)
            ->groupBy(
                'inventario_relacion_ventainventario.IdDetalleProducto',
                'inventario_relacion_ventainventario.Detalle',
                'inventario_menu_categoria.nombre'
            )
            ->orderBy('inventario_relacion_ventainventario.Detalle')
            ->select(
                'inventario_relacion_ventainventario.IdDetalleProducto',
                'inventario_relacion_ventainventario.Detalle',
                'inventario_menu_categoria.nombre as categoria_nombre'
            )
            ->get();
        
        foreach ($productosVendidos as $producto) {
            $tipoVenta = 'N';
            if (!empty($producto->categoria_nombre)) {
                $tipoVenta = substr($producto->categoria_nombre, 0, 1);
            }
            $worksheet->setCellValue('B' . $filaProductoVendido, $tipoVenta . ' - ' . $this->toExcelString($producto->Detalle));
            
            $columnaArrayProductoAux = 2;
            for ($fi = 0; $fi < $totalOperadores; $fi++) {
                $idOperador = $controlOperadorSucursal[$fi]['id_operador'];
                $idSucursalAux = $controlOperadorSucursal[$fi]['id_sucursal'];
                
                $unidadesVendidas = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas')
                    ->join('impuestos_ventas_detalle', 'impuestos_ventas.IdVentas', '=', 'impuestos_ventas_detalle.idventas')
                    ->where('impuestos_ventas.IdCliente', $clienteId)
                    ->where('impuestos_ventas.IdClienteSucursal', $idSucursalAux)
                    ->where('impuestos_ventas.IdOperadorIngresa', $idOperador)
                    ->where('impuestos_ventas.IdEstado', 1)
                    ->whereDate('impuestos_ventas.FechaVenta', $fecha)
                    ->where('impuestos_ventas_detalle.idrelacionventainventario', $producto->IdDetalleProducto)
                    ->sum('impuestos_ventas_detalle.unidades');
                
                $worksheet->setCellValue($columnas[$columnaArrayProductoAux] . $filaProductoVendido, (float) $unidadesVendidas);
                $columnaArrayProductoAux++;
            }
            $worksheet->setCellValue($columnas[$columnaArrayProductoAux] . $filaProductoVendido, '=SUM(C' . $filaProductoVendido . ':' . $columnas[$columnaArrayProductoAux - 1] . $filaProductoVendido . ')');
            $filaProductoVendido++;
        }
        
        // Totales de productos vendidos
        if ($productosVendidos->count() > 0) {
            $worksheet->setCellValue('B' . $filaProductoVendido, 'TOTAL');
            $columnaArrayProducto = 2;
            for ($fi = 0; $fi <= $totalOperadores; $fi++) {
                $worksheet->setCellValue($columnas[$columnaArrayProducto] . $filaProductoVendido, '=SUM(' . $columnas[$columnaArrayProducto] . $filaParaSumaTotal . ':' . $columnas[$columnaArrayProducto] . ($filaProductoVendido - 1) . ')');
                $worksheet->getStyle($columnas[$columnaArrayProducto] . $filaProductoVendido)->applyFromArray($borderStyle);
                $columnaArrayProducto++;
            }
        }
        
        // ==========================================
        // FORMATOS GENERALES
        // ==========================================
        $worksheet->getStyle('C10:' . $columnas[$columnaArrayDiferencia] . $filaProductoVendido)->getNumberFormat()->setFormatCode('##0.00 ;(##0.00)');
        
        $worksheet->getColumnDimension('A')->setWidth(2);
        $worksheet->getColumnDimension('B')->setWidth(23);
        
        $letraUltima = $columnas[$columnaArrayDiferencia];
        for ($c = ord('C'); $c <= ord($letraUltima); $c++) {
            $worksheet->getColumnDimension(chr($c))->setWidth(15);
        }
        
        // ==========================================
        // DESCARGAR
        // ==========================================
        $nombreArchivo = 'AnalisisLiquidacionOperador_' . $fechaMostrar . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit();
    }
    
    /**
     * Convierte texto a ISO-8859-1 para Excel
     */
    private function toExcelString($texto)
    {
        if (empty($texto)) {
            return '';
        }
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }
}