<?php

namespace App\Http\Controllers\Gestion\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Inertia\Inertia;

class ResultadosComparativoController extends Controller
{
    /**
     * Muestra el formulario del reporte
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        // Obtener sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        // Obtener gestiones disponibles
        $gestiones = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->selectRaw("DATE_FORMAT(Fecha, '%Y') as gestion")
            ->groupBy('gestion')
            ->orderBy('gestion', 'desc')
            ->get()
            ->pluck('gestion');

        return Inertia::render('Gestion/Reportes/ResultadosComparativo', [
            'empresa' => $empresa,
            'sucursales' => $sucursales,
            'sucursalId' => $sucursalId,
            'gestiones' => $gestiones,
        ]);
    }

    /**
     * Exporta el reporte a Excel (GET)
     */
    public function exportar(Request $request)
    {
        $sucursalId = $request->get('sucursal_id');
        $gestion = $request->get('gestion');
        $clienteId = session('cliente_id');

        // Obtener datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion']);

        // Obtener datos de la sucursal
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);

        // 🔥 COMO EN SCRIPTCASE: Esta línea está comentada, NO se ejecuta
        // sc_lookup(idtipocliente,"SELECT IdTipoCliente FROM todos_cliente WHERE IdCliente = '[global_cliente]'");
        
        // 🔥 En Scriptcase, $IdTipoCliente toma el valor comentado, que es 4
        $IdTipoCliente = 4;
        
        // COMO EN SCRIPTCASE: if ($IdTipoCliente == 4)
        if ($IdTipoCliente == 4) {
            $esIndustrial = true;
        } else {
            $esIndustrial = false;
        }

        // ==========================================
        // CREAR EXCEL
        // ==========================================
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Estado Resultado');

        // Hoja para Gastos No Deducibles
        $worksheetND = $spreadsheet->createSheet();
        $worksheetND->setTitle('Gasto ND');

        // ==========================================
        // CABECERA - HOJA PRINCIPAL
        // ==========================================
        $worksheet->setCellValue('A1', $this->toExcelString($empresa->Nombre ?? ''));
        $worksheet->setCellValue('A2', $this->toExcelString($sucursal->Nombre ?? ''));
        $worksheet->setCellValue('A3', 'NIT: ' . ($empresa->NIT ?? ''));
        $worksheet->setCellValue('A4', 'Estado de Resultados Comparativo');
        $worksheet->setCellValue('A5', 'Gestión ' . $gestion);
        $worksheet->setCellValue('A6', '(Expresado en Bolivianos)');

        // ==========================================
        // CABECERA - HOJA NO DEDUCIBLE
        // ==========================================
        $worksheetND->setCellValue('A1', $this->toExcelString($empresa->Nombre ?? ''));
        $worksheetND->setCellValue('A2', $this->toExcelString($sucursal->Nombre ?? ''));
        $worksheetND->setCellValue('A3', 'NIT: ' . ($empresa->NIT ?? ''));
        $worksheetND->setCellValue('A4', 'Estado de Resultados Comparativo (Gasto No Deducible)');
        $worksheetND->setCellValue('A5', 'Gestión ' . $gestion);
        $worksheetND->setCellValue('A6', '(Expresado en Bolivianos)');

        // Combinar celdas de cabecera
        $worksheet->mergeCells('A1:O1');
        $worksheet->mergeCells('A2:O2');
        $worksheet->mergeCells('A3:O3');
        $worksheet->mergeCells('A4:O4');
        $worksheet->mergeCells('A5:O5');
        $worksheet->mergeCells('A6:O6');

        $worksheetND->mergeCells('A1:O1');
        $worksheetND->mergeCells('A2:O2');
        $worksheetND->mergeCells('A3:O3');
        $worksheetND->mergeCells('A4:O4');
        $worksheetND->mergeCells('A5:O5');
        $worksheetND->mergeCells('A6:O6');

        // Estilos de cabecera
        $alignCenter = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]];
        $worksheet->getStyle('A4:A6')->applyFromArray($alignCenter);
        $worksheetND->getStyle('A4:A6')->applyFromArray($alignCenter);
        
        $worksheet->getStyle('A4')->getFont()->setSize(20);
        $worksheet->getStyle('A5')->getFont()->setSize(20);
        $worksheet->getStyle('A6')->getFont()->setSize(8);
        
        $worksheetND->getStyle('A4')->getFont()->setSize(20);
        $worksheetND->getStyle('A5')->getFont()->setSize(20);
        $worksheetND->getStyle('A6')->getFont()->setSize(8);

        // ==========================================
        // CONFIGURAR ANCHO DE COLUMNAS
        // ==========================================
        $columnas = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O'];
        foreach ($columnas as $col) {
            $worksheet->getColumnDimension($col)->setWidth(14);
            $worksheetND->getColumnDimension($col)->setWidth(14);
        }
        $worksheet->getColumnDimension('B')->setAutoSize(true);
        $worksheetND->getColumnDimension('B')->setAutoSize(true);

        // ==========================================
        // TÍTULOS DE COLUMNAS
        // ==========================================
        $borderStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

        if ($esIndustrial) {
            $periodos = [
                'Abr - ' . $gestion,
                'May - ' . $gestion,
                'Jun - ' . $gestion,
                'Jul - ' . $gestion,
                'Ago - ' . $gestion,
                'Sep - ' . $gestion,
                'Oct - ' . $gestion,
                'Nov - ' . $gestion,
                'Dic - ' . $gestion,
                'Ene - ' . ($gestion + 1),
                'Feb - ' . ($gestion + 1),
                'Mar - ' . ($gestion + 1),
            ];
        } else {
            $periodos = [
                'Ene - ' . $gestion,
                'Feb - ' . $gestion,
                'Mar - ' . $gestion,
                'Abr - ' . $gestion,
                'May - ' . $gestion,
                'Jun - ' . $gestion,
                'Jul - ' . $gestion,
                'Ago - ' . $gestion,
                'Sep - ' . $gestion,
                'Oct - ' . $gestion,
                'Nov - ' . $gestion,
                'Dic - ' . $gestion,
            ];
        }

        // Títulos principales
        $worksheet->setCellValue('A8', 'Cuenta');
        $worksheet->setCellValue('B8', 'Descripcion');
        $worksheet->setCellValue('O8', 'Totales');
        
        $worksheetND->setCellValue('A8', 'Cuenta');
        $worksheetND->setCellValue('B8', 'Descripcion');
        $worksheetND->setCellValue('O8', 'Totales');

        for ($i = 0; $i < 12; $i++) {
            $columna = chr(67 + $i); // C, D, E, F, G, H, I, J, K, L, M, N
            $worksheet->setCellValue($columna . '8', $periodos[$i]);
            $worksheetND->setCellValue($columna . '8', $periodos[$i]);
        }

        // Estilos de títulos
        $worksheet->getStyle('A8:O8')->applyFromArray($alignCenter);
        $worksheet->getStyle('A8:O8')->applyFromArray($borderStyle);
        $worksheetND->getStyle('A8:O8')->applyFromArray($alignCenter);
        $worksheetND->getStyle('A8:O8')->applyFromArray($borderStyle);

        // ==========================================
        // OBTENER CUENTAS DE RESULTADO (Tipo P)
        // ==========================================
        $cuentas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('conta_cuenta', 'conta_diario_propiamente.IdCuenta', '=', 'conta_cuenta.IdCuenta')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->where('conta_cuenta.TipoDeCuenta', 'P')
            ->where('conta_diario.Contabilizado', 1)
            ->select('conta_cuenta.Cuenta', 'conta_cuenta.Descripcion', 'conta_cuenta.IdCuenta')
            ->groupBy('conta_cuenta.Cuenta', 'conta_cuenta.Descripcion', 'conta_cuenta.IdCuenta')
            ->orderBy('conta_cuenta.Cuenta')
            ->get();

        // Definir array de periodos para consultas
        if ($esIndustrial) {
            $meses = ['04', '05', '06', '07', '08', '09', '10', '11', '12', '01', '02', '03'];
            $aniosPeriodo = array_merge(
                array_fill(0, 9, $gestion),
                array_fill(0, 3, $gestion + 1)
            );
        } else {
            $meses = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];
            $aniosPeriodo = array_fill(0, 12, $gestion);
        }

        // ==========================================
        // GENERAR DATOS
        // ==========================================
        $i = 9; // Fila inicial
        $iParcial = 9;
        $controlCuentaSaltoLinea = 4;
        $subTotales = ['Mas Total Ingresos', 'Menos Total Costo', 'Menos Total Gasto'];
        $controlArrays = 0;
        $paraTotalGeneral = [];

        foreach ($cuentas as $cuenta) {
            $numeroCuenta = $cuenta->Cuenta;
            $descripcion = $cuenta->Descripcion;
            $idCuenta = $cuenta->IdCuenta;

            // Detectar cambio de cuenta principal (primer dígito)
            $controlCuenta = substr($numeroCuenta, 0, 1);
            
            if ($controlCuenta != $controlCuentaSaltoLinea && $controlArrays < 3) {
                if ($i > $iParcial) {
                    $controlCuentaSaltoLinea = $controlCuenta;
                    
                    // Totales parciales
                    $rango = $iParcial . ':' . ($i - 1);
                    $worksheet->setCellValue('C' . $i, '=SUM(C' . $rango . ')');
                    $worksheet->setCellValue('D' . $i, '=SUM(D' . $rango . ')');
                    $worksheet->setCellValue('E' . $i, '=SUM(E' . $rango . ')');
                    $worksheet->setCellValue('F' . $i, '=SUM(F' . $rango . ')');
                    $worksheet->setCellValue('G' . $i, '=SUM(G' . $rango . ')');
                    $worksheet->setCellValue('H' . $i, '=SUM(H' . $rango . ')');
                    $worksheet->setCellValue('I' . $i, '=SUM(I' . $rango . ')');
                    $worksheet->setCellValue('J' . $i, '=SUM(J' . $rango . ')');
                    $worksheet->setCellValue('K' . $i, '=SUM(K' . $rango . ')');
                    $worksheet->setCellValue('L' . $i, '=SUM(L' . $rango . ')');
                    $worksheet->setCellValue('M' . $i, '=SUM(M' . $rango . ')');
                    $worksheet->setCellValue('N' . $i, '=SUM(N' . $rango . ')');
                    $worksheet->setCellValue('O' . $i, '=SUM(O' . $rango . ')');
                    
                    $worksheet->getStyle('A' . $i . ':O' . $i)->applyFromArray($borderStyle);
                    $worksheet->mergeCells('A' . $i . ':B' . $i);
                    $worksheet->setCellValue('A' . $i, $subTotales[$controlArrays]);
                    
                    // Mismo para No Deducible
                    $worksheetND->setCellValue('C' . $i, '=SUM(C' . $rango . ')');
                    $worksheetND->setCellValue('D' . $i, '=SUM(D' . $rango . ')');
                    $worksheetND->setCellValue('E' . $i, '=SUM(E' . $rango . ')');
                    $worksheetND->setCellValue('F' . $i, '=SUM(F' . $rango . ')');
                    $worksheetND->setCellValue('G' . $i, '=SUM(G' . $rango . ')');
                    $worksheetND->setCellValue('H' . $i, '=SUM(H' . $rango . ')');
                    $worksheetND->setCellValue('I' . $i, '=SUM(I' . $rango . ')');
                    $worksheetND->setCellValue('J' . $i, '=SUM(J' . $rango . ')');
                    $worksheetND->setCellValue('K' . $i, '=SUM(K' . $rango . ')');
                    $worksheetND->setCellValue('L' . $i, '=SUM(L' . $rango . ')');
                    $worksheetND->setCellValue('M' . $i, '=SUM(M' . $rango . ')');
                    $worksheetND->setCellValue('N' . $i, '=SUM(N' . $rango . ')');
                    $worksheetND->setCellValue('O' . $i, '=SUM(O' . $rango . ')');
                    
                    $worksheetND->getStyle('A' . $i . ':O' . $i)->applyFromArray($borderStyle);
                    $worksheetND->mergeCells('A' . $i . ':B' . $i);
                    $worksheetND->setCellValue('A' . $i, $subTotales[$controlArrays]);
                    
                    $paraTotalGeneral[$controlArrays] = $i;
                    $controlArrays++;
                    $i++;
                    $iParcial = $i;
                }
            }

            // Calcular saldos por período
            $totalesPeriodo = [];
            
            for ($p = 0; $p < 12; $p++) {
                $fecha = $meses[$p] . '/' . $aniosPeriodo[$p];
                
                $saldoDebe = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
                    ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
                    ->where('conta_diario_propiamente.IdCuenta', $idCuenta)
                    ->where('conta_diario_propiamente.D_H', 'D')
                    ->where('conta_diario.IdCliente', $clienteId)
                    ->where('conta_diario.IdSucursal', $sucursalId)
                    ->whereRaw("DATE_FORMAT(todos_fecha.Fecha, '%m/%Y') = ?", [$fecha])
                    ->sum('conta_diario_propiamente.MontoBolivianos');

                $saldoHaber = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
                    ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
                    ->where('conta_diario_propiamente.IdCuenta', $idCuenta)
                    ->where('conta_diario_propiamente.D_H', 'H')
                    ->where('conta_diario.IdCliente', $clienteId)
                    ->where('conta_diario.IdSucursal', $sucursalId)
                    ->whereRaw("DATE_FORMAT(todos_fecha.Fecha, '%m/%Y') = ?", [$fecha])
                    ->sum('conta_diario_propiamente.MontoBolivianos');

                $totalesPeriodo[$p] = $saldoHaber - $saldoDebe;
            }

            // Escribir datos en la hoja principal
            $worksheet->setCellValue('A' . $i, $numeroCuenta);
            $worksheet->setCellValue('B' . $i, $this->toExcelString($descripcion));
            
            for ($p = 0; $p < 12; $p++) {
                $columna = chr(67 + $p);
                $worksheet->setCellValue($columna . $i, $totalesPeriodo[$p]);
            }
            $worksheet->setCellValue('O' . $i, '=SUM(C' . $i . ':N' . $i . ')');
            
            // Escribir datos en la hoja de No Deducible (mismos valores por ahora)
            $worksheetND->setCellValue('A' . $i, $numeroCuenta);
            $worksheetND->setCellValue('B' . $i, $this->toExcelString($descripcion));
            for ($p = 0; $p < 12; $p++) {
                $columna = chr(67 + $p);
                $worksheetND->setCellValue($columna . $i, $totalesPeriodo[$p]);
            }
            $worksheetND->setCellValue('O' . $i, '=SUM(C' . $i . ':N' . $i . ')');

            $i++;
        }

        // Totales finales
        if ($i > $iParcial) {
            $rangoFinal = $iParcial . ':' . ($i - 1);
            $worksheet->setCellValue('C' . $i, '=SUM(C' . $rangoFinal . ')');
            $worksheet->setCellValue('D' . $i, '=SUM(D' . $rangoFinal . ')');
            $worksheet->setCellValue('E' . $i, '=SUM(E' . $rangoFinal . ')');
            $worksheet->setCellValue('F' . $i, '=SUM(F' . $rangoFinal . ')');
            $worksheet->setCellValue('G' . $i, '=SUM(G' . $rangoFinal . ')');
            $worksheet->setCellValue('H' . $i, '=SUM(H' . $rangoFinal . ')');
            $worksheet->setCellValue('I' . $i, '=SUM(I' . $rangoFinal . ')');
            $worksheet->setCellValue('J' . $i, '=SUM(J' . $rangoFinal . ')');
            $worksheet->setCellValue('K' . $i, '=SUM(K' . $rangoFinal . ')');
            $worksheet->setCellValue('L' . $i, '=SUM(L' . $rangoFinal . ')');
            $worksheet->setCellValue('M' . $i, '=SUM(M' . $rangoFinal . ')');
            $worksheet->setCellValue('N' . $i, '=SUM(N' . $rangoFinal . ')');
            $worksheet->setCellValue('O' . $i, '=SUM(O' . $rangoFinal . ')');
            
            $worksheet->setCellValue('A' . $i, $subTotales[$controlArrays]);
            $worksheet->getStyle('A' . $i . ':O' . $i)->applyFromArray($borderStyle);
            $worksheet->mergeCells('A' . $i . ':B' . $i);
            
            $worksheetND->setCellValue('C' . $i, '=SUM(C' . $rangoFinal . ')');
            $worksheetND->setCellValue('D' . $i, '=SUM(D' . $rangoFinal . ')');
            $worksheetND->setCellValue('E' . $i, '=SUM(E' . $rangoFinal . ')');
            $worksheetND->setCellValue('F' . $i, '=SUM(F' . $rangoFinal . ')');
            $worksheetND->setCellValue('G' . $i, '=SUM(G' . $rangoFinal . ')');
            $worksheetND->setCellValue('H' . $i, '=SUM(H' . $rangoFinal . ')');
            $worksheetND->setCellValue('I' . $i, '=SUM(I' . $rangoFinal . ')');
            $worksheetND->setCellValue('J' . $i, '=SUM(J' . $rangoFinal . ')');
            $worksheetND->setCellValue('K' . $i, '=SUM(K' . $rangoFinal . ')');
            $worksheetND->setCellValue('L' . $i, '=SUM(L' . $rangoFinal . ')');
            $worksheetND->setCellValue('M' . $i, '=SUM(M' . $rangoFinal . ')');
            $worksheetND->setCellValue('N' . $i, '=SUM(N' . $rangoFinal . ')');
            $worksheetND->setCellValue('O' . $i, '=SUM(O' . $rangoFinal . ')');
            
            $worksheetND->setCellValue('A' . $i, $subTotales[$controlArrays]);
            $worksheetND->getStyle('A' . $i . ':O' . $i)->applyFromArray($borderStyle);
            $worksheetND->mergeCells('A' . $i . ':B' . $i);
            
            $paraTotalGeneral[$controlArrays] = $i;
        }

        $i = $i + 2;
        
        // Totales generales
        if (isset($paraTotalGeneral[0]) && isset($paraTotalGeneral[1]) && isset($paraTotalGeneral[2])) {
            $worksheet->setCellValue('C' . $i, '=+C' . $paraTotalGeneral[0] . '+C' . $paraTotalGeneral[1] . '+C' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('D' . $i, '=+D' . $paraTotalGeneral[0] . '+D' . $paraTotalGeneral[1] . '+D' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('E' . $i, '=+E' . $paraTotalGeneral[0] . '+E' . $paraTotalGeneral[1] . '+E' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('F' . $i, '=+F' . $paraTotalGeneral[0] . '+F' . $paraTotalGeneral[1] . '+F' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('G' . $i, '=+G' . $paraTotalGeneral[0] . '+G' . $paraTotalGeneral[1] . '+G' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('H' . $i, '=+H' . $paraTotalGeneral[0] . '+H' . $paraTotalGeneral[1] . '+H' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('I' . $i, '=+I' . $paraTotalGeneral[0] . '+I' . $paraTotalGeneral[1] . '+I' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('J' . $i, '=+J' . $paraTotalGeneral[0] . '+J' . $paraTotalGeneral[1] . '+J' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('K' . $i, '=+K' . $paraTotalGeneral[0] . '+K' . $paraTotalGeneral[1] . '+K' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('L' . $i, '=+L' . $paraTotalGeneral[0] . '+L' . $paraTotalGeneral[1] . '+L' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('M' . $i, '=+M' . $paraTotalGeneral[0] . '+M' . $paraTotalGeneral[1] . '+M' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('N' . $i, '=+N' . $paraTotalGeneral[0] . '+N' . $paraTotalGeneral[1] . '+N' . $paraTotalGeneral[2]);
            $worksheet->setCellValue('O' . $i, '=+O' . $paraTotalGeneral[0] . '+O' . $paraTotalGeneral[1] . '+O' . $paraTotalGeneral[2]);
            
            $worksheet->setCellValue('A' . $i, 'Total Resultados (Periodo)');
            $worksheet->getStyle('A' . $i . ':O' . $i)->applyFromArray($borderStyle);
            $worksheet->mergeCells('A' . $i . ':B' . $i);
            $worksheet->getStyle('A' . $i)->applyFromArray($alignCenter);
            
            // Mismo para No Deducible
            $worksheetND->setCellValue('C' . $i, '=+C' . $paraTotalGeneral[0] . '+C' . $paraTotalGeneral[1] . '+C' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('D' . $i, '=+D' . $paraTotalGeneral[0] . '+D' . $paraTotalGeneral[1] . '+D' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('E' . $i, '=+E' . $paraTotalGeneral[0] . '+E' . $paraTotalGeneral[1] . '+E' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('F' . $i, '=+F' . $paraTotalGeneral[0] . '+F' . $paraTotalGeneral[1] . '+F' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('G' . $i, '=+G' . $paraTotalGeneral[0] . '+G' . $paraTotalGeneral[1] . '+G' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('H' . $i, '=+H' . $paraTotalGeneral[0] . '+H' . $paraTotalGeneral[1] . '+H' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('I' . $i, '=+I' . $paraTotalGeneral[0] . '+I' . $paraTotalGeneral[1] . '+I' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('J' . $i, '=+J' . $paraTotalGeneral[0] . '+J' . $paraTotalGeneral[1] . '+J' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('K' . $i, '=+K' . $paraTotalGeneral[0] . '+K' . $paraTotalGeneral[1] . '+K' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('L' . $i, '=+L' . $paraTotalGeneral[0] . '+L' . $paraTotalGeneral[1] . '+L' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('M' . $i, '=+M' . $paraTotalGeneral[0] . '+M' . $paraTotalGeneral[1] . '+M' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('N' . $i, '=+N' . $paraTotalGeneral[0] . '+N' . $paraTotalGeneral[1] . '+N' . $paraTotalGeneral[2]);
            $worksheetND->setCellValue('O' . $i, '=+O' . $paraTotalGeneral[0] . '+O' . $paraTotalGeneral[1] . '+O' . $paraTotalGeneral[2]);
            
            $worksheetND->setCellValue('A' . $i, 'Total Resultados No deducibles (Periodo)');
            $worksheetND->getStyle('A' . $i . ':O' . $i)->applyFromArray($borderStyle);
            $worksheetND->mergeCells('A' . $i . ':B' . $i);
        }

        // Formato de números
        $worksheet->getStyle('A9:A' . $i)->getNumberFormat()->setFormatCode('#,##0.000');
        $worksheet->getStyle('C9:O' . $i)->getNumberFormat()->setFormatCode('#,##0.00 ; (#,##0.00)');
        $worksheetND->getStyle('A9:A' . $i)->getNumberFormat()->setFormatCode('#,##0.000');
        $worksheetND->getStyle('C9:O' . $i)->getNumberFormat()->setFormatCode('#,##0.00 ; (#,##0.00)');

        // ==========================================
        // DESCARGAR
        // ==========================================
        $nombreArchivo = 'EstadoDeResultadosComparativo.xls';
        
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