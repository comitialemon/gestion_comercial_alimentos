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

class InformeSucursalController extends Controller
{
    /**
     * Muestra el formulario del reporte
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha', DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y') as fecha_formateada")]);
        
        return Inertia::render('Gestion/Reportes/ControlInterno/InformeSucursal', [
            'fechas' => $fechas,
        ]);
    }
    
    /**
     * Exporta el reporte a Excel (IDÉNTICO al de Scriptcase)
     */
    public function exportar(Request $request)
    {
        $request->validate([
            'IdFecha' => 'required|integer|exists:todos_fecha,IdFecha',
        ]);
        
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $idFecha = $request->IdFecha;
        
        // Obtener fecha literal
        $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $idFecha)
            ->first(['Fecha']);
        
        $fechaLiteral = date('d-m-Y', strtotime($fechaData->Fecha));
        
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
        // CABECERA (EXACTAMENTE COMO SCRIPTCASE)
        // ==========================================
        $worksheet->setCellValue('A1', $this->toExcelString($empresa->Nombre ?? ''));
        $worksheet->setCellValue('A2', 'NIT : ' . ($empresa->NIT ?? ''));
        $worksheet->setCellValue('A3', 'INFORMACION SOBRE EL MOVIMIENTO DIARIO DE SUCURSALES');
        $worksheet->setCellValue('A4', $fechaLiteral);
        $worksheet->setCellValue('A5', '(Expresado en Bolivianos)');
        
        // ==========================================
        // OBTENER SUCURSALES (igual que Scriptcase)
        // ==========================================
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->groupBy('IdCliente', 'IdClienteSucursal')
            ->select('IdClienteSucursal')
            ->get();
        
        if ($sucursales->isEmpty()) {
            $sucursales = collect([(object)['IdClienteSucursal' => $sucursalId]]);
        }
        
        // ==========================================
        // TÍTULOS DE COLUMNAS (fila 8)
        // ==========================================
        $i = 8;
        $columnaArray = 2;
        
        foreach ($sucursales as $suc) {
            $idSucursal = $suc->IdClienteSucursal;
            $letra = $columnas[$columnaArray];
            
            $nombreSucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $idSucursal)
                ->value('Nombre');
            
            $worksheet->setCellValue('B' . $i, 'DETALLE');
            $worksheet->setCellValue($letra . $i, $this->toExcelString($nombreSucursal));
            
            $columnaArray++;
        }
        
        $i++;
        $worksheet->setCellValue('A' . $i, 'Ventas');
        $i++;
        
        // ==========================================
        // VENTAS POR SUCURSAL
        // ==========================================
        $columnaArray = 2;
        $worksheet->setCellValue('B' . $i, 'Ventas');
        
        foreach ($sucursales as $suc) {
            $idSucursal = $suc->IdClienteSucursal;
            $letra = $columnas[$columnaArray];
            
            $totalVentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $idSucursal)
                ->whereRaw("DATE_FORMAT(FechaVenta, '%d-%m-%Y') = ?", [$fechaLiteral])
                ->sum('ImporteVenta');
            
            $worksheet->setCellValue($letra . $i, (float) $totalVentas);
            $columnaArray++;
        }
        
        $inicioSumaTotales = $i;
        $i++;
        
        // ==========================================
        // CONCEPTOS DE LIQUIDACIÓN
        // ==========================================
        $conceptos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_liquidacion_concepto')
            ->where('IdCliente', $clienteId)
            ->get(['Concepto', 'IdCuenta']);
        
        foreach ($conceptos as $concepto) {
            $worksheet->setCellValue('B' . $i, $this->toExcelString($concepto->Concepto));
            
            $columnaArray = 2;
            
            foreach ($sucursales as $suc) {
                $idSucursal = $suc->IdClienteSucursal;
                $letra = $columnas[$columnaArray];
                
                $totalBolivianos = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion')
                    ->join('impuestos_ventas', 'impuestos_ventas_liquidacion.IdVentas', '=', 'impuestos_ventas.IdVentas')
                    ->where('impuestos_ventas.IdCliente', $clienteId)
                    ->where('impuestos_ventas.IdClienteSucursal', $idSucursal)
                    ->whereRaw("DATE_FORMAT(impuestos_ventas.FechaVenta, '%d-%m-%Y') = ?", [$fechaLiteral])
                    ->where('impuestos_ventas_liquidacion.IdCuenta', $concepto->IdCuenta)
                    ->sum('impuestos_ventas_liquidacion.Bolivianos');
                
                $worksheet->setCellValue($letra . $i, (float) $totalBolivianos);
                $columnaArray++;
            }
            
            $i++;
        }
        
        // ==========================================
        // DIFERENCIA EN VENTAS (fórmula como Scriptcase)
        // ==========================================
        $columnaArray = 2;
        $worksheet->setCellValue('B' . $i, 'Diferencia Sucursal');
        
        foreach ($sucursales as $suc) {
            $letra = $columnas[$columnaArray];
            $worksheet->setCellValue($letra . $i, '=SUM(' . $letra . $inicioSumaTotales . ':' . $letra . ($i - 1) . ')-' . $letra . ($inicioSumaTotales - 1));
            $columnaArray++;
        }
        
        $i += 2;
        
        // ==========================================
        // COMISIONISTAS
        // ==========================================
        $worksheet->setCellValue('A' . $i, 'Comisionista');
        $i++;
        
        $comisionistas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_comisionitas')
            ->where('IdCliente', $clienteId)
            ->get(['IdComisionista', 'IdIdentificador']);
        
        $filaComisionistas = $i;
        
        foreach ($comisionistas as $com) {
            $nombreComisionista = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('IdIdentificador', $com->IdIdentificador)
                ->value('Nombre');
            
            $worksheet->setCellValue('B' . $i, $this->toExcelString($nombreComisionista));
            
            $columnaArray = 2;
            
            foreach ($sucursales as $suc) {
                $idSucursal = $suc->IdClienteSucursal;
                $letra = $columnas[$columnaArray];
                
                $totalComisionista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion')
                    ->join('impuestos_ventas', 'impuestos_ventas_liquidacion.IdVentas', '=', 'impuestos_ventas.IdVentas')
                    ->where('impuestos_ventas.IdCliente', $clienteId)
                    ->where('impuestos_ventas.IdClienteSucursal', $idSucursal)
                    ->whereRaw("DATE_FORMAT(impuestos_ventas.FechaVenta, '%d-%m-%Y') = ?", [$fechaLiteral])
                    ->where('impuestos_ventas.IdComisionista', $com->IdComisionista)
                    ->sum('impuestos_ventas_liquidacion.Bolivianos');
                
                $worksheet->setCellValue($letra . $i, (float) $totalComisionista);
                $columnaArray++;
            }
            
            $i++;
        }
        
        // Total comisionistas
        $worksheet->setCellValue('A' . $i, 'Total');
        
        $columnaArray = 2;
        foreach ($sucursales as $suc) {
            $letra = $columnas[$columnaArray];
            $worksheet->setCellValue($letra . $i, '=SUM(' . $letra . $filaComisionistas . ':' . $letra . ($i - 1) . ')');
            $columnaArray++;
        }
        
        $i += 2;
        
        // ==========================================
        // PRODUCTOS VENDIDOS (CON CATEGORÍA EN VEZ DE GRUPO)
        // ==========================================
        $worksheet->setCellValue('A' . $i, 'Producto Vendido');
        $i++;
        
        $productosVendidos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->join('impuestos_ventas_detalle', 'impuestos_ventas.IdVentas', '=', 'impuestos_ventas_detalle.idventas')
            ->join('inventario_relacion_ventainventario', 'impuestos_ventas_detalle.idrelacionventainventario', '=', 'inventario_relacion_ventainventario.IdDetalleProducto')
            ->leftJoin('inventario_menu_categoria', 'inventario_relacion_ventainventario.id_categoria', '=', 'inventario_menu_categoria.id_categoria')
            ->where('impuestos_ventas.IdCliente', $clienteId)
            ->where('impuestos_ventas.IdClienteSucursal', $sucursalId)
            ->where('impuestos_ventas.IdEstado', 1)
            ->whereRaw("DATE_FORMAT(impuestos_ventas.FechaVenta, '%d-%m-%Y') = ?", [$fechaLiteral])
            ->groupBy(
                'impuestos_ventas_detalle.idrelacionventainventario',
                'inventario_relacion_ventainventario.Detalle',
                'impuestos_ventas_detalle.preciounidades',
                'inventario_menu_categoria.nombre'
            )
            ->select(
                DB::raw("SUM(impuestos_ventas_detalle.unidades) as TotalUnidades"),
                'inventario_relacion_ventainventario.Detalle',
                'impuestos_ventas_detalle.preciounidades',
                'inventario_menu_categoria.nombre as categoria_nombre'
            )
            ->orderBy('inventario_relacion_ventainventario.Detalle')
            ->get();
        
        foreach ($productosVendidos as $producto) {
            $tipoVenta = 'N';
            if (!empty($producto->categoria_nombre)) {
                $tipoVenta = substr($producto->categoria_nombre, 0, 1);
            }
            $worksheet->setCellValue('A' . $i, $tipoVenta . ' - ' . $this->toExcelString($producto->Detalle));
            $worksheet->setCellValue('B' . $i, (float) $producto->TotalUnidades);
            $i++;
        }
        
        // ==========================================
        // SALIDA DE INVENTARIO
        // ==========================================
        $i++;
        $worksheet->setCellValue('A' . $i, 'Salida de Inventario');
        $i++;
        
        $inventario = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_propiamente')
            ->join('impuestos_ventas', 'inventario_propiamente.IdDocumento', '=', 'impuestos_ventas.IdVentas')
            ->join('inventario_productodetalle', 'inventario_propiamente.IdProducto', '=', 'inventario_productodetalle.IdProducto')
            ->join('todos_fecha', 'inventario_propiamente.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('impuestos_ventas.IdCliente', $clienteId)
            ->where('impuestos_ventas.IdClienteSucursal', $sucursalId)
            ->where('todos_fecha.IdFecha', $idFecha)
            ->groupBy('inventario_productodetalle.IdProducto', 'inventario_productodetalle.Descripcion')
            ->select(
                'inventario_productodetalle.Descripcion',
                DB::raw('SUM(inventario_propiamente.Unidades) as TotalUnidades')
            )
            ->get();
        
        foreach ($inventario as $item) {
            $worksheet->setCellValue('A' . $i, $this->toExcelString($item->Descripcion));
            $worksheet->setCellValue('B' . $i, (float) $item->TotalUnidades);
            $i++;
        }
        
        // ==========================================
        // FORMATOS (EXACTAMENTE COMO SCRIPTCASE)
        // ==========================================
        
        $letraUltima = $columnas[$columnaArray - 1];
        if (empty($letraUltima) || $letraUltima < 'C') {
            $letraUltima = 'B';
        }
        
        // Borde de celdas
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
        $alignCenter = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
        
        // Bordes nombres de columnas (fila 8)
        $worksheet->getStyle('B8:' . $letraUltima . '8')->applyFromArray($borderStyle);
        
        // Bordes Diferencia movimiento
        $worksheet->getStyle('B' . $i . ':' . $letraUltima . $i)->applyFromArray($borderStyle);
        
        // Combinar títulos
        $worksheet->mergeCells('A1:' . $letraUltima . '1');
        $worksheet->mergeCells('A2:' . $letraUltima . '2');
        $worksheet->mergeCells('A3:' . $letraUltima . '3');
        $worksheet->mergeCells('A4:' . $letraUltima . '4');
        $worksheet->mergeCells('A5:' . $letraUltima . '5');
        
        // Estilos títulos
        $titleStyle = [
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'font' => ['bold' => true, 'size' => 15]
        ];
        $subTitleStyle = [
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'font' => ['bold' => true, 'size' => 8]
        ];
        
        $worksheet->getStyle('A1')->applyFromArray($titleStyle);
        $worksheet->getStyle('A2')->applyFromArray($subTitleStyle);
        $worksheet->getStyle('A3')->applyFromArray($alignCenter);
        $worksheet->getStyle('A4')->applyFromArray($alignCenter);
        $worksheet->getStyle('A5')->applyFromArray($alignCenter);
        
        // Alineación títulos columnas
        $worksheet->getStyle('B8')->applyFromArray($alignCenter);
        $worksheet->getStyle('C8:' . $letraUltima . '8')->applyFromArray($alignCenter);
        
        // Wrap text
        $worksheet->getStyle('C8:' . $letraUltima . '8')->getAlignment()->setWrapText(true);
        
        // Ancho de columnas
        $worksheet->getColumnDimension('A')->setWidth(2);
        $worksheet->getColumnDimension('B')->setAutoSize(true);
        
        for ($c = ord('C'); $c <= ord($letraUltima); $c++) {
            $worksheet->getColumnDimension(chr($c))->setWidth(11);
        }
        
        // Formato números
        $worksheet->getStyle('C10:' . $letraUltima . $i)->getNumberFormat()->setFormatCode('#,##0.00');
        
        // 🔥 SIN FREEZE PANE (para que la cabecera NO se mueva)
        // Scriptcase NO tiene freeze pane, por eso no lo pongo
        
        // ==========================================
        // DESCARGAR
        // ==========================================
        $nombreArchivo = 'AnalisisSucursal.xls';
        
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