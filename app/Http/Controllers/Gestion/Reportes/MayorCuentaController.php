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

class MayorCuentaController extends Controller
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

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre', 'NumeroSucursal']);

        // Lista de cuentas
        $cuentas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCliente', $clienteId)
            ->where('AbiertoCerrado', 0)
            ->orderBy('Cuenta')
            ->get(['IdCuenta as id', 'Cuenta', 'Descripcion']);

        // Lista de identificadores
        $identificadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador')
            ->orderBy('CI_NIT')
            ->get(['IdIdentificador as id', 'CI_NIT', 'Nombre']);

        return Inertia::render('Gestion/Reportes/MayorCuenta', [
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'cuentas' => $cuentas,
            'identificadores' => $identificadores,
        ]);
    }

    /**
     * Exporta según los parámetros seleccionados
     */
    public function exportar(Request $request)
    {
        $request->validate([
            'cuenta_id' => 'required|exists:conta_cuenta,IdCuenta',
            'fecha_inicial' => 'required|date',
            'identificador_id' => 'nullable|exists:todos_identificador,IdIdentificador',
            'moneda' => 'required|in:1,2',
        ]);

        $cuentaId = $request->cuenta_id;
        $fechaMayor = $request->fecha_inicial;
        $identificadorId = $request->identificador_id;
        $moneda = $request->moneda;

        if (empty($identificadorId)) {
            if ($moneda == 1) {
                return $this->exportarBolivianos($cuentaId, $fechaMayor);
            } else {
                return $this->exportarOtraMoneda($cuentaId, $fechaMayor);
            }
        } else {
            if ($moneda == 1) {
                return $this->exportarBolivianosConIdentificador($cuentaId, $fechaMayor, $identificadorId);
            } else {
                return $this->exportarOtraMonedaConIdentificador($cuentaId, $fechaMayor, $identificadorId);
            }
        }
    }

    /**
     * Reporte 1: Mayor de cuenta en Bolivianos (sin identificador)
     * IDÉNTICO a ScriptCase
     */
    private function exportarBolivianos($cuentaId, $fechaMayor)
    {
        set_time_limit(0);

        // Datos de la cuenta
        $cuenta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCuenta', $cuentaId)
            ->first(['Cuenta', 'Descripcion', 'TipoDeCuenta']);

        if (!$cuenta) {
            die("Cuenta no encontrada: {$cuentaId}");
        }

        $numeroCuenta = $cuenta->Cuenta;
        $descripcionCuenta = $cuenta->Descripcion;
        $tipoDeCuenta = $cuenta->TipoDeCuenta;

        // Fecha inicial para saldo anterior
        if ($tipoDeCuenta == 'B') {
            $fechaInicialSaldoAnterior = '1900/01/01';
            $auxiliarGestion = null;
        } else {
            $primerDia = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->selectRaw("MIN(DATE_FORMAT(Fecha, '%Y/%m/%d')) as fecha")
                ->whereYear('Fecha', date('Y', strtotime($fechaMayor)))
                ->first();
            
            $fechaInicialSaldoAnterior = $primerDia->fecha ?? '1900/01/01';
            $auxiliarGestion = date('Y', strtotime($fechaMayor));
        }

        // Datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', session('cliente_sucursal_id'))
            ->first(['Nombre']);

        // Saldo anterior - Debe
        $saldoAnteriorDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario.IdSucursal', session('cliente_sucursal_id'))
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario_propiamente.D_H', 'D')
            ->whereBetween('todos_fecha.Fecha', [$fechaInicialSaldoAnterior, $fechaMayor])
            ->where('conta_diario.Contabilizado', 1)
            ->sum('conta_diario_propiamente.MontoBolivianos');

        $saldoAnteriorHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario.IdSucursal', session('cliente_sucursal_id'))
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario_propiamente.D_H', 'H')
            ->whereBetween('todos_fecha.Fecha', [$fechaInicialSaldoAnterior, $fechaMayor])
            ->where('conta_diario.Contabilizado', 1)
            ->sum('conta_diario_propiamente.MontoBolivianos');

        $saldoInicial = $saldoAnteriorDebe - $saldoAnteriorHaber;
        $auxiliarSaldo = $saldoInicial;

        if ($saldoInicial > 0) {
            $dHInicial = 'D';
        } elseif ($saldoInicial < 0) {
            $dHInicial = 'H';
        } else {
            $dHInicial = '';
        }

        // Detalle de movimientos
        $detalle = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->join('conta_tipodiario', 'conta_diario.IdTipoDiario', '=', 'conta_tipodiario.IdTipoDiario')
            ->join('todos_identificador', 'conta_diario_propiamente.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->join('todos_operador', 'conta_diario.IdOperadorIngreso', '=', 'todos_operador.IdOperador')
            ->where('todos_fecha.Fecha', '>', $fechaMayor)
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario.IdSucursal', session('cliente_sucursal_id'))
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->orderBy('todos_fecha.Fecha')
            ->orderBy('conta_diario.NumeroDiario')
            ->get([
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d/%m/%Y') as fecha"),
                'conta_diario.NumeroDiario',
                'conta_diario_propiamente.Glosa',
                'todos_identificador.CI_NIT',
                'todos_identificador.Nombre as identificador_nombre',
                'conta_diario_propiamente.D_H',
                'conta_diario_propiamente.MontoBolivianos',
                'todos_operador.Iniciales',
                'conta_tipodiario.Abreviacion as tipo_diario',
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%Y') as gestion_detalle"),
            ]);

        // ==========================================
        // CREAR EXCEL
        // ==========================================
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->setCellValue('A1', $this->toExcelString('Mayor de Cuenta'));
        $worksheet->setCellValue('A2', $this->toExcelString('(Expresado en Bolivianos)'));
        $worksheet->setCellValue('A3', $this->toExcelString('Nombre o Razon Social : ' . ($empresa->Nombre ?? '')));
        $worksheet->setCellValue('A4', $this->toExcelString('Sucursal : ' . ($sucursal->Nombre ?? '')));
        $worksheet->setCellValue('A5', $this->toExcelString('Direccion : ' . ($empresa->Direccion ?? '')));
        $worksheet->setCellValue('A6', $this->toExcelString('Cuenta : ' . $numeroCuenta . '  ' . $descripcionCuenta));

        $worksheet->mergeCells('A1:J1');
        $worksheet->mergeCells('A2:J2');
        $worksheet->mergeCells('A3:C3');
        $worksheet->mergeCells('A4:C4');
        $worksheet->mergeCells('A5:C5');

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 15],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $worksheet->getStyle('A1')->applyFromArray($titleStyle);
        $worksheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 8], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        $worksheet->setCellValue('A7', $this->toExcelString('Fecha'));
        $worksheet->setCellValue('B7', $this->toExcelString('Numero Diario'));
        $worksheet->setCellValue('C7', $this->toExcelString('Detalle'));
        $worksheet->setCellValue('D7', $this->toExcelString('Identificador'));
        $worksheet->setCellValue('E7', $this->toExcelString('Debe'));
        $worksheet->setCellValue('F7', $this->toExcelString('Haber'));
        $worksheet->setCellValue('G7', $this->toExcelString('Saldo'));
        $worksheet->setCellValue('H7', $this->toExcelString('D_H'));
        $worksheet->setCellValue('I7', $this->toExcelString('Op.'));
        $worksheet->setCellValue('J7', $this->toExcelString('Tipo'));

        $fechaFormat = date('d/m/Y', strtotime($fechaMayor));
        $saldoInicialA = round(abs($saldoInicial), 2);
        $worksheet->setCellValue('C8', $this->toExcelString('Saldo de Cuenta al  ( ' . $fechaFormat . ' )'));
        $worksheet->setCellValue('G8', $saldoInicialA);
        $worksheet->setCellValue('H8', $dHInicial);

        $worksheet->getStyle('C8')->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $worksheet->getStyle('G8')->getNumberFormat()->setFormatCode('#,##0.00');

        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $worksheet->getStyle('A7:J7')->applyFromArray($headerStyle);

        $worksheet->getColumnDimension('A')->setWidth(11);
        $worksheet->getColumnDimension('B')->setWidth(11);
        $worksheet->getColumnDimension('C')->setWidth(40);
        $worksheet->getColumnDimension('D')->setWidth(40);
        $worksheet->getColumnDimension('E')->setWidth(14);
        $worksheet->getColumnDimension('F')->setWidth(14);
        $worksheet->getColumnDimension('G')->setWidth(14);
        $worksheet->getColumnDimension('H')->setWidth(4);
        $worksheet->getColumnDimension('I')->setWidth(5);
        $worksheet->getColumnDimension('J')->setWidth(5);

        $fila = 9;
        $auxiliarSaldoActual = $auxiliarSaldo;
        $auxiliarGestionActual = $auxiliarGestion;

        foreach ($detalle as $row) {
            $montoDebe = ($row->D_H == 'D') ? $row->MontoBolivianos : '';
            $montoHaber = ($row->D_H == 'H') ? $row->MontoBolivianos : '';

            if ($tipoDeCuenta == 'P' && $auxiliarGestionActual != $row->gestion_detalle) {
                $auxiliarSaldoActual = 0;
                $auxiliarGestionActual = $row->gestion_detalle;
            }

            $auxiliarDebe = (float)($montoDebe ?: 0);
            $auxiliarHaber = (float)($montoHaber ?: 0);
            
            $total = round($auxiliarSaldoActual, 2) + round($auxiliarDebe, 2) - round($auxiliarHaber, 2);
            $auxiliarSaldoActual = $total;
            
            if ($total > 0) {
                $dHMuestra = 'D';
            } elseif ($total < 0) {
                $dHMuestra = 'H';
            } else {
                $dHMuestra = '';
            }

            $identificadorCompleto = $row->identificador_nombre ?? '';
            $glosa = is_string($row->Glosa) ? $row->Glosa : '';

            $worksheet->setCellValue('A' . $fila, $row->fecha);
            $worksheet->setCellValue('B' . $fila, $row->NumeroDiario);
            $worksheet->setCellValue('C' . $fila, $this->toExcelString($glosa));
            $worksheet->setCellValue('D' . $fila, $this->toExcelString($identificadorCompleto));
            $worksheet->setCellValue('E' . $fila, $montoDebe);
            $worksheet->setCellValue('F' . $fila, $montoHaber);
            $worksheet->setCellValue('G' . $fila, abs($auxiliarSaldoActual));
            $worksheet->setCellValue('H' . $fila, $dHMuestra);
            $worksheet->setCellValue('I' . $fila, $row->Iniciales);
            $worksheet->setCellValue('J' . $fila, $row->tipo_diario);

            // Alineación
            $worksheet->getStyle('A' . $fila)->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
            $worksheet->getStyle('B' . $fila . ':J' . $fila)->applyFromArray(['alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
            $worksheet->getStyle('C' . $fila)->getAlignment()->setWrapText(true);
            $worksheet->getStyle('D' . $fila)->getAlignment()->setWrapText(true);

            $worksheet->getStyle('E' . $fila . ':G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');

            $fila++;
        }

        // NOTA: ScriptCase NO aplica bordes a las filas de datos en Reporte 1
        // Solo aplica borde a la fila 7 (títulos) - ya aplicado arriba

        // NOTA: ScriptCase NO agrega fila de totales - NO agregar

        // Configuración de impresión
        $worksheet->getPageSetup()->setPrintArea('A1:J' . $fila);
        $worksheet->getPageMargins()->setTop(0.3);
        $worksheet->getPageMargins()->setRight(0.3);
        $worksheet->getPageMargins()->setLeft(0.3);
        $worksheet->getPageMargins()->setBottom(0.3);
        $worksheet->getPageSetup()->setFitToPage(false);
        $worksheet->getPageSetup()->setScale(60);
        $worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 7);

        // ==========================================
        // DESCARGA DEL ARCHIVO
        // ==========================================
        $nombreArchivo = 'MayorBolivianosSucursal.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit();
    }

    /**
     * Reporte 2: Mayor de cuenta en Otra Moneda (sin identificador)
     * IDÉNTICO a ScriptCase
     * NOTA: En ScriptCase, el saldo anterior NO filtra por IdSucursal
     */
    private function exportarOtraMoneda($cuentaId, $fechaMayor)
    {
        set_time_limit(0);

        $cuenta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCuenta', $cuentaId)
            ->first(['Cuenta', 'Descripcion', 'TipoDeCuenta']);

        if (!$cuenta) {
            die("Cuenta no encontrada: {$cuentaId}");
        }

        $numeroCuenta = $cuenta->Cuenta;
        $descripcionCuenta = $cuenta->Descripcion;
        $tipoDeCuenta = $cuenta->TipoDeCuenta;

        if ($tipoDeCuenta == 'B') {
            $fechaInicialSaldoAnterior = '1900/01/01';
            $auxiliarGestion = null;
        } else {
            $primerDia = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->selectRaw("MIN(DATE_FORMAT(Fecha, '%Y/%m/%d')) as fecha")
                ->whereYear('Fecha', date('Y', strtotime($fechaMayor)))
                ->first();
            
            $fechaInicialSaldoAnterior = $primerDia->fecha ?? '1900/01/01';
            $auxiliarGestion = date('Y', strtotime($fechaMayor));
        }

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', session('cliente_sucursal_id'))
            ->first(['Nombre']);

        // IMPORTANTE: ScriptCase NO filtra por IdSucursal en el saldo anterior del Reporte 2
        $saldoAnteriorDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario_propiamente.D_H', 'D')
            ->whereBetween('todos_fecha.Fecha', [$fechaInicialSaldoAnterior, $fechaMayor])
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->sum('conta_diario_propiamente.MontoOtraMoneda');

        $saldoAnteriorHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario_propiamente.D_H', 'H')
            ->whereBetween('todos_fecha.Fecha', [$fechaInicialSaldoAnterior, $fechaMayor])
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->sum('conta_diario_propiamente.MontoOtraMoneda');

        $saldoInicial = $saldoAnteriorDebe - $saldoAnteriorHaber;
        $auxiliarSaldo = $saldoInicial;

        if ($saldoInicial > 0) {
            $dHInicial = 'D';
        } elseif ($saldoInicial < 0) {
            $dHInicial = 'H';
        } else {
            $dHInicial = '';
        }

        // El detalle SÍ filtra por IdSucursal (igual que ScriptCase)
        $detalle = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->join('conta_tipodiario', 'conta_diario.IdTipoDiario', '=', 'conta_tipodiario.IdTipoDiario')
            ->join('todos_identificador', 'conta_diario_propiamente.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->join('todos_operador', 'conta_diario.IdOperadorIngreso', '=', 'todos_operador.IdOperador')
            ->where('todos_fecha.Fecha', '>', $fechaMayor)
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario.IdSucursal', session('cliente_sucursal_id'))
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->orderBy('todos_fecha.Fecha')
            ->orderBy('conta_diario.NumeroDiario')
            ->get([
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d/%m/%Y') as fecha"),
                'conta_diario.NumeroDiario',
                'conta_diario_propiamente.Glosa',
                'todos_identificador.CI_NIT',
                'todos_identificador.Nombre as identificador_nombre',
                'conta_diario_propiamente.D_H',
                'conta_diario_propiamente.MontoOtraMoneda',
                'todos_operador.Iniciales',
                'conta_tipodiario.Abreviacion as tipo_diario',
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%Y') as gestion_detalle"),
            ]);

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->setCellValue('A1', $this->toExcelString('Mayor de Cuenta'));
        $worksheet->setCellValue('A2', $this->toExcelString('(Expresado en Otra Moneda)'));
        $worksheet->setCellValue('A3', $this->toExcelString('Nombre o Razon Social : ' . ($empresa->Nombre ?? '')));
        $worksheet->setCellValue('A4', $this->toExcelString('Sucursal : ' . ($sucursal->Nombre ?? '')));
        $worksheet->setCellValue('A5', $this->toExcelString('Direccion : ' . ($empresa->Direccion ?? '')));
        $worksheet->setCellValue('A6', $this->toExcelString('Cuenta : ' . $numeroCuenta . '  ' . $descripcionCuenta));

        $worksheet->mergeCells('A1:J1');
        $worksheet->mergeCells('A2:J2');
        $worksheet->mergeCells('A3:C3');
        $worksheet->mergeCells('A4:C4');
        $worksheet->mergeCells('A5:C5');

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 15],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $worksheet->getStyle('A1')->applyFromArray($titleStyle);
        $worksheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 8], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        $worksheet->setCellValue('A7', $this->toExcelString('Fecha'));
        $worksheet->setCellValue('B7', $this->toExcelString('Numero Diario'));
        $worksheet->setCellValue('C7', $this->toExcelString('Detalle'));
        $worksheet->setCellValue('D7', $this->toExcelString('Identificador'));
        $worksheet->setCellValue('E7', $this->toExcelString('Debe'));
        $worksheet->setCellValue('F7', $this->toExcelString('Haber'));
        $worksheet->setCellValue('G7', $this->toExcelString('Saldo'));
        $worksheet->setCellValue('H7', $this->toExcelString('D_H'));
        $worksheet->setCellValue('I7', $this->toExcelString('Op.'));
        $worksheet->setCellValue('J7', $this->toExcelString('Tipo'));

        $fechaFormat = date('d/m/Y', strtotime($fechaMayor));
        $saldoInicialA = round(abs($saldoInicial), 2);
        $worksheet->setCellValue('C8', $this->toExcelString('Saldo de Cuenta al  ( ' . $fechaFormat . ' )'));
        $worksheet->setCellValue('G8', $saldoInicialA);
        $worksheet->setCellValue('H8', $dHInicial);

        $worksheet->getStyle('C8')->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $worksheet->getStyle('G8')->getNumberFormat()->setFormatCode('#,##0.00');

        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $worksheet->getStyle('A7:J7')->applyFromArray($headerStyle);

        $worksheet->getColumnDimension('A')->setWidth(11);
        $worksheet->getColumnDimension('B')->setWidth(11);
        $worksheet->getColumnDimension('C')->setWidth(40);
        $worksheet->getColumnDimension('D')->setWidth(40);
        $worksheet->getColumnDimension('E')->setWidth(14);
        $worksheet->getColumnDimension('F')->setWidth(14);
        $worksheet->getColumnDimension('G')->setWidth(14);
        $worksheet->getColumnDimension('H')->setWidth(4);
        $worksheet->getColumnDimension('I')->setWidth(5);
        $worksheet->getColumnDimension('J')->setWidth(5);

        $fila = 9;
        $auxiliarSaldoActual = $auxiliarSaldo;
        $auxiliarGestionActual = $auxiliarGestion;

        foreach ($detalle as $row) {
            $montoDebe = ($row->D_H == 'D') ? $row->MontoOtraMoneda : '';
            $montoHaber = ($row->D_H == 'H') ? $row->MontoOtraMoneda : '';

            if ($tipoDeCuenta == 'P' && $auxiliarGestionActual != $row->gestion_detalle) {
                $auxiliarSaldoActual = 0;
                $auxiliarGestionActual = $row->gestion_detalle;
            }

            $auxiliarDebe = (float)($montoDebe ?: 0);
            $auxiliarHaber = (float)($montoHaber ?: 0);
            
            $total = round($auxiliarSaldoActual, 2) + round($auxiliarDebe, 2) - round($auxiliarHaber, 2);
            $auxiliarSaldoActual = $total;
            
            if ($total > 0) {
                $dHMuestra = 'D';
            } elseif ($total < 0) {
                $dHMuestra = 'H';
            } else {
                $dHMuestra = '';
            }

            $identificadorCompleto = $row->identificador_nombre ?? '';
            $glosa = is_string($row->Glosa) ? $row->Glosa : '';

            $worksheet->setCellValue('A' . $fila, $row->fecha);
            $worksheet->setCellValue('B' . $fila, $row->NumeroDiario);
            $worksheet->setCellValue('C' . $fila, $this->toExcelString($glosa));
            $worksheet->setCellValue('D' . $fila, $this->toExcelString($identificadorCompleto));
            $worksheet->setCellValue('E' . $fila, $montoDebe);
            $worksheet->setCellValue('F' . $fila, $montoHaber);
            $worksheet->setCellValue('G' . $fila, abs($auxiliarSaldoActual));
            $worksheet->setCellValue('H' . $fila, $dHMuestra);
            $worksheet->setCellValue('I' . $fila, $row->Iniciales);
            $worksheet->setCellValue('J' . $fila, $row->tipo_diario);

            $worksheet->getStyle('A' . $fila)->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
            $worksheet->getStyle('B' . $fila . ':J' . $fila)->applyFromArray(['alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
            $worksheet->getStyle('C' . $fila)->getAlignment()->setWrapText(true);
            $worksheet->getStyle('D' . $fila)->getAlignment()->setWrapText(true);

            $worksheet->getStyle('E' . $fila . ':G' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');

            $fila++;
        }

        $worksheet->getPageSetup()->setPrintArea('A1:J' . $fila);
        $worksheet->getPageMargins()->setTop(0.3);
        $worksheet->getPageMargins()->setRight(0.3);
        $worksheet->getPageMargins()->setLeft(0.3);
        $worksheet->getPageMargins()->setBottom(0.3);
        $worksheet->getPageSetup()->setFitToPage(false);
        $worksheet->getPageSetup()->setScale(60);
        $worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 7);

        $nombreArchivo = 'MayorOtraMoneda.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit();
    }

    /**
     * Reporte 3: Mayor de cuenta en Bolivianos CON identificador
     * IDÉNTICO a ScriptCase
     */
    private function exportarBolivianosConIdentificador($cuentaId, $fechaMayor, $identificadorId)
    {
        set_time_limit(0);

        $cuenta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCuenta', $cuentaId)
            ->first(['Cuenta', 'Descripcion', 'TipoDeCuenta']);

        if (!$cuenta) {
            die("Cuenta no encontrada: {$cuentaId}");
        }

        $numeroCuenta = $cuenta->Cuenta;
        $descripcionCuenta = $cuenta->Descripcion;
        $tipoDeCuenta = $cuenta->TipoDeCuenta;

        if ($tipoDeCuenta == 'B') {
            $fechaInicialSaldoAnterior = '1900/01/01';
            $auxiliarGestion = null;
        } else {
            $primerDia = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->selectRaw("MIN(DATE_FORMAT(Fecha, '%Y/%m/%d')) as fecha")
                ->whereYear('Fecha', date('Y', strtotime($fechaMayor)))
                ->first();
            
            $fechaInicialSaldoAnterior = $primerDia->fecha ?? '1900/01/01';
            $auxiliarGestion = date('Y', strtotime($fechaMayor));
        }

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', session('cliente_sucursal_id'))
            ->first(['Nombre']);

        $identificador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador')
            ->where('IdIdentificador', $identificadorId)
            ->first(['CI_NIT', 'Nombre']);

        // IMPORTANTE: ScriptCase usa Contabilizado != 0 en este reporte
        $saldoAnteriorDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario_propiamente.D_H', 'D')
            ->whereBetween('todos_fecha.Fecha', [$fechaInicialSaldoAnterior, $fechaMayor])
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorId)
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->sum('conta_diario_propiamente.MontoBolivianos');

        $saldoAnteriorHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario_propiamente.D_H', 'H')
            ->whereBetween('todos_fecha.Fecha', [$fechaInicialSaldoAnterior, $fechaMayor])
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorId)
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->sum('conta_diario_propiamente.MontoBolivianos');

        $saldoInicial = $saldoAnteriorDebe - $saldoAnteriorHaber;
        $auxiliarSaldo = $saldoInicial;

        if ($saldoInicial > 0) {
            $dHInicial = 'D';
        } elseif ($saldoInicial < 0) {
            $dHInicial = 'H';
        } else {
            $dHInicial = '';
        }

        $detalle = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->join('conta_tipodiario', 'conta_diario.IdTipoDiario', '=', 'conta_tipodiario.IdTipoDiario')
            ->join('todos_identificador', 'conta_diario_propiamente.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->join('todos_operador', 'conta_diario.IdOperadorIngreso', '=', 'todos_operador.IdOperador')
            ->where('todos_fecha.Fecha', '>', $fechaMayor)
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorId)
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->orderBy('todos_fecha.Fecha')
            ->orderBy('conta_diario.NumeroDiario')
            ->get([
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d/%m/%Y') as fecha"),
                'conta_diario.NumeroDiario',
                'conta_diario_propiamente.Glosa',
                'conta_diario_propiamente.D_H',
                'conta_diario_propiamente.MontoBolivianos',
                'todos_operador.Iniciales',
                'conta_tipodiario.Abreviacion as tipo_diario',
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%Y') as gestion_detalle"),
            ]);

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->setCellValue('A1', $this->toExcelString('Mayor de Cuenta - Identificador'));
        $worksheet->setCellValue('A2', $this->toExcelString('(Expresado en Bolivianos)'));
        $worksheet->setCellValue('A3', $this->toExcelString('Nombre o Razon Social : ' . ($empresa->Nombre ?? '')));
        $worksheet->setCellValue('A4', $this->toExcelString('Sucursal : ' . ($sucursal->Nombre ?? '')));
        $worksheet->setCellValue('A5', $this->toExcelString('Cuenta : ' . $numeroCuenta . '  ' . $descripcionCuenta));
        $worksheet->setCellValue('A6', $this->toExcelString('Nombre : ' . ($identificador->Nombre ?? '') . ' CI - NIT  : ' . ($identificador->CI_NIT ?? '')));

        $worksheet->mergeCells('A1:J1');
        $worksheet->mergeCells('A2:J2');
        $worksheet->mergeCells('A3:C3');
        $worksheet->mergeCells('A4:C4');
        $worksheet->mergeCells('A5:C5');

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 15],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $worksheet->getStyle('A1')->applyFromArray($titleStyle);
        $worksheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 8], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        // OJO: Este reporte solo tiene 9 columnas (A-I)
        $worksheet->setCellValue('A7', $this->toExcelString('Fecha'));
        $worksheet->setCellValue('B7', $this->toExcelString('Numero Diario'));
        $worksheet->setCellValue('C7', $this->toExcelString('Detalle'));
        $worksheet->setCellValue('D7', $this->toExcelString('Debe'));
        $worksheet->setCellValue('E7', $this->toExcelString('Haber'));
        $worksheet->setCellValue('F7', $this->toExcelString('Saldo'));
        $worksheet->setCellValue('G7', $this->toExcelString('D_H'));
        $worksheet->setCellValue('H7', $this->toExcelString('Op.'));
        $worksheet->setCellValue('I7', $this->toExcelString('Tipo'));

        $fechaFormat = date('d/m/Y', strtotime($fechaMayor));
        $saldoInicialA = round(abs($saldoInicial), 2);
        $worksheet->setCellValue('C8', $this->toExcelString('Saldo de Cuenta al  ( ' . $fechaFormat . ' )'));
        $worksheet->setCellValue('F8', $saldoInicialA);
        $worksheet->setCellValue('G8', $dHInicial);

        $worksheet->getStyle('C8')->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $worksheet->getStyle('F8')->getNumberFormat()->setFormatCode('#,##0.00');

        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $worksheet->getStyle('A7:I7')->applyFromArray($headerStyle);

        $worksheet->getColumnDimension('A')->setWidth(11);
        $worksheet->getColumnDimension('B')->setWidth(11);
        $worksheet->getColumnDimension('C')->setWidth(40);
        $worksheet->getColumnDimension('D')->setWidth(40);
        $worksheet->getColumnDimension('E')->setWidth(14);
        $worksheet->getColumnDimension('F')->setWidth(14);
        $worksheet->getColumnDimension('G')->setWidth(5);
        $worksheet->getColumnDimension('H')->setWidth(4);
        $worksheet->getColumnDimension('I')->setWidth(5);

        $fila = 9;
        $auxiliarSaldoActual = $auxiliarSaldo;
        $auxiliarGestionActual = $auxiliarGestion;

        foreach ($detalle as $row) {
            $montoDebe = ($row->D_H == 'D') ? $row->MontoBolivianos : '';
            $montoHaber = ($row->D_H == 'H') ? $row->MontoBolivianos : '';

            if ($tipoDeCuenta == 'P' && $auxiliarGestionActual != $row->gestion_detalle) {
                $auxiliarSaldoActual = 0;
                $auxiliarGestionActual = $row->gestion_detalle;
            }

            $auxiliarDebe = (float)($montoDebe ?: 0);
            $auxiliarHaber = (float)($montoHaber ?: 0);
            
            $total = round($auxiliarSaldoActual, 2) + round($auxiliarDebe, 2) - round($auxiliarHaber, 2);
            $auxiliarSaldoActual = $total;
            
            if ($total > 0) {
                $dHMuestra = 'D';
            } elseif ($total < 0) {
                $dHMuestra = 'H';
            } else {
                $dHMuestra = '';
            }

            $glosa = is_string($row->Glosa) ? $row->Glosa : '';

            $worksheet->setCellValue('A' . $fila, $row->fecha);
            $worksheet->setCellValue('B' . $fila, $row->NumeroDiario);
            $worksheet->setCellValue('C' . $fila, $this->toExcelString($glosa));
            $worksheet->setCellValue('D' . $fila, $montoDebe);
            $worksheet->setCellValue('E' . $fila, $montoHaber);
            $worksheet->setCellValue('F' . $fila, abs($auxiliarSaldoActual));
            $worksheet->setCellValue('G' . $fila, $dHMuestra);
            $worksheet->setCellValue('H' . $fila, $row->Iniciales);
            $worksheet->setCellValue('I' . $fila, $row->tipo_diario);

            $worksheet->getStyle('A' . $fila)->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
            $worksheet->getStyle('B' . $fila . ':I' . $fila)->applyFromArray(['alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
            $worksheet->getStyle('C' . $fila)->getAlignment()->setWrapText(true);

            $worksheet->getStyle('D' . $fila . ':F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');

            $fila++;
        }

        // NOTA: ScriptCase NO aplica bordes a las filas de datos en este reporte
        // Solo se aplica borde a la fila 7 (títulos)

        $worksheet->getPageSetup()->setPrintArea('A1:I' . $fila);
        $worksheet->getPageMargins()->setTop(0.3);
        $worksheet->getPageMargins()->setRight(0.3);
        $worksheet->getPageMargins()->setLeft(0.3);
        $worksheet->getPageMargins()->setBottom(0.3);
        $worksheet->getPageSetup()->setFitToPage(false);
        $worksheet->getPageSetup()->setScale(60);
        $worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 7);

        $nombreArchivo = 'MayorBolivianosIdentificador.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit();
    }

    /**
     * Reporte 4: Mayor de cuenta en Otra Moneda CON identificador
     * IDÉNTICO a ScriptCase
     */
    private function exportarOtraMonedaConIdentificador($cuentaId, $fechaMayor, $identificadorId)
    {
        set_time_limit(0);

        $cuenta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCuenta', $cuentaId)
            ->first(['Cuenta', 'Descripcion', 'TipoDeCuenta']);

        if (!$cuenta) {
            die("Cuenta no encontrada: {$cuentaId}");
        }

        $numeroCuenta = $cuenta->Cuenta;
        $descripcionCuenta = $cuenta->Descripcion;
        $tipoDeCuenta = $cuenta->TipoDeCuenta;

        if ($tipoDeCuenta == 'B') {
            $fechaInicialSaldoAnterior = '1900/01/01';
            $auxiliarGestion = null;
        } else {
            $primerDia = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->selectRaw("MIN(DATE_FORMAT(Fecha, '%Y/%m/%d')) as fecha")
                ->whereYear('Fecha', date('Y', strtotime($fechaMayor)))
                ->first();
            
            $fechaInicialSaldoAnterior = $primerDia->fecha ?? '1900/01/01';
            $auxiliarGestion = date('Y', strtotime($fechaMayor));
        }

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', session('cliente_sucursal_id'))
            ->first(['Nombre']);

        $identificador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador')
            ->where('IdIdentificador', $identificadorId)
            ->first(['CI_NIT', 'Nombre']);

        // IMPORTANTE: ScriptCase usa Contabilizado != 0 en este reporte
        $saldoAnteriorDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario_propiamente.D_H', 'D')
            ->whereBetween('todos_fecha.Fecha', [$fechaInicialSaldoAnterior, $fechaMayor])
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorId)
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->sum('conta_diario_propiamente.MontoOtraMoneda');

        $saldoAnteriorHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario_propiamente.D_H', 'H')
            ->whereBetween('todos_fecha.Fecha', [$fechaInicialSaldoAnterior, $fechaMayor])
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorId)
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->sum('conta_diario_propiamente.MontoOtraMoneda');

        $saldoInicial = $saldoAnteriorDebe - $saldoAnteriorHaber;
        $auxiliarSaldo = $saldoInicial;

        if ($saldoInicial > 0) {
            $dHInicial = 'D';
        } elseif ($saldoInicial < 0) {
            $dHInicial = 'H';
        } else {
            $dHInicial = '';
        }

        $detalle = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->join('conta_diario_propiamente', 'conta_diario.IdDiario', '=', 'conta_diario_propiamente.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->join('conta_tipodiario', 'conta_diario.IdTipoDiario', '=', 'conta_tipodiario.IdTipoDiario')
            ->join('todos_identificador', 'conta_diario_propiamente.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->join('todos_operador', 'conta_diario.IdOperadorIngreso', '=', 'todos_operador.IdOperador')
            ->where('todos_fecha.Fecha', '>', $fechaMayor)
            ->where('conta_diario_propiamente.IdCuenta', $cuentaId)
            ->where('conta_diario.IdCliente', session('cliente_id'))
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorId)
            ->where('conta_diario.Contabilizado', '!=', 0)
            ->orderBy('todos_fecha.Fecha')
            ->orderBy('conta_diario.NumeroDiario')
            ->get([
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d/%m/%Y') as fecha"),
                'conta_diario.NumeroDiario',
                'conta_diario_propiamente.Glosa',
                'conta_diario_propiamente.D_H',
                'conta_diario_propiamente.MontoOtraMoneda',
                'todos_operador.Iniciales',
                'conta_tipodiario.Abreviacion as tipo_diario',
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%Y') as gestion_detalle"),
            ]);

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->setCellValue('A1', $this->toExcelString('Mayor de Cuenta - Identificador'));
        $worksheet->setCellValue('A2', $this->toExcelString('(Expresado en Otra Moneda)'));
        $worksheet->setCellValue('A3', $this->toExcelString('Nombre o Razon Social : ' . ($empresa->Nombre ?? '')));
        $worksheet->setCellValue('A4', $this->toExcelString('Sucursal : ' . ($sucursal->Nombre ?? '')));
        $worksheet->setCellValue('A5', $this->toExcelString('Cuenta : ' . $numeroCuenta . '  ' . $descripcionCuenta));
        $worksheet->setCellValue('A6', $this->toExcelString('Nombre : ' . ($identificador->Nombre ?? '') . ' CI - NIT  : ' . ($identificador->CI_NIT ?? '')));

        $worksheet->mergeCells('A1:J1');
        $worksheet->mergeCells('A2:J2');
        $worksheet->mergeCells('A3:C3');
        $worksheet->mergeCells('A4:C4');
        $worksheet->mergeCells('A5:C5');

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 15],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        $worksheet->getStyle('A1')->applyFromArray($titleStyle);
        $worksheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 8], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]);

        $worksheet->setCellValue('A7', $this->toExcelString('Fecha'));
        $worksheet->setCellValue('B7', $this->toExcelString('Numero Diario'));
        $worksheet->setCellValue('C7', $this->toExcelString('Detalle'));
        $worksheet->setCellValue('D7', $this->toExcelString('Debe'));
        $worksheet->setCellValue('E7', $this->toExcelString('Haber'));
        $worksheet->setCellValue('F7', $this->toExcelString('Saldo'));
        $worksheet->setCellValue('G7', $this->toExcelString('D_H'));
        $worksheet->setCellValue('H7', $this->toExcelString('Op.'));
        $worksheet->setCellValue('I7', $this->toExcelString('Tipo'));

        $fechaFormat = date('d/m/Y', strtotime($fechaMayor));
        $saldoInicialA = round(abs($saldoInicial), 2);
        $worksheet->setCellValue('C8', $this->toExcelString('Saldo de Cuenta al  ( ' . $fechaFormat . ' )'));
        $worksheet->setCellValue('F8', $saldoInicialA);
        $worksheet->setCellValue('G8', $dHInicial);

        $worksheet->getStyle('C8')->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
        $worksheet->getStyle('F8')->getNumberFormat()->setFormatCode('#,##0.00');

        $headerStyle = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $worksheet->getStyle('A7:I7')->applyFromArray($headerStyle);

        $worksheet->getColumnDimension('A')->setWidth(11);
        $worksheet->getColumnDimension('B')->setWidth(11);
        $worksheet->getColumnDimension('C')->setWidth(40);
        $worksheet->getColumnDimension('D')->setWidth(40);
        $worksheet->getColumnDimension('E')->setWidth(14);
        $worksheet->getColumnDimension('F')->setWidth(14);
        $worksheet->getColumnDimension('G')->setWidth(5);
        $worksheet->getColumnDimension('H')->setWidth(4);
        $worksheet->getColumnDimension('I')->setWidth(5);

        $fila = 9;
        $auxiliarSaldoActual = $auxiliarSaldo;
        $auxiliarGestionActual = $auxiliarGestion;

        foreach ($detalle as $row) {
            $montoDebe = ($row->D_H == 'D') ? $row->MontoOtraMoneda : '';
            $montoHaber = ($row->D_H == 'H') ? $row->MontoOtraMoneda : '';

            if ($tipoDeCuenta == 'P' && $auxiliarGestionActual != $row->gestion_detalle) {
                $auxiliarSaldoActual = 0;
                $auxiliarGestionActual = $row->gestion_detalle;
            }

            $auxiliarDebe = (float)($montoDebe ?: 0);
            $auxiliarHaber = (float)($montoHaber ?: 0);
            
            $total = round($auxiliarSaldoActual, 2) + round($auxiliarDebe, 2) - round($auxiliarHaber, 2);
            $auxiliarSaldoActual = $total;
            
            if ($total > 0) {
                $dHMuestra = 'D';
            } elseif ($total < 0) {
                $dHMuestra = 'H';
            } else {
                $dHMuestra = '';
            }

            $glosa = is_string($row->Glosa) ? $row->Glosa : '';

            $worksheet->setCellValue('A' . $fila, $row->fecha);
            $worksheet->setCellValue('B' . $fila, $row->NumeroDiario);
            $worksheet->setCellValue('C' . $fila, $this->toExcelString($glosa));
            $worksheet->setCellValue('D' . $fila, $montoDebe);
            $worksheet->setCellValue('E' . $fila, $montoHaber);
            $worksheet->setCellValue('F' . $fila, abs($auxiliarSaldoActual));
            $worksheet->setCellValue('G' . $fila, $dHMuestra);
            $worksheet->setCellValue('H' . $fila, $row->Iniciales);
            $worksheet->setCellValue('I' . $fila, $row->tipo_diario);

            $worksheet->getStyle('A' . $fila)->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
            $worksheet->getStyle('B' . $fila . ':I' . $fila)->applyFromArray(['alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
            $worksheet->getStyle('C' . $fila)->getAlignment()->setWrapText(true);

            $worksheet->getStyle('D' . $fila . ':F' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');

            $fila++;
        }

        $worksheet->getPageSetup()->setPrintArea('A1:I' . $fila);
        $worksheet->getPageMargins()->setTop(0.3);
        $worksheet->getPageMargins()->setRight(0.3);
        $worksheet->getPageMargins()->setLeft(0.3);
        $worksheet->getPageMargins()->setBottom(0.3);
        $worksheet->getPageSetup()->setFitToPage(false);
        $worksheet->getPageSetup()->setScale(60);
        $worksheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 7);

        $nombreArchivo = 'MayorOtraMonedaIdentificador.xls';
        
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

    /**
     * Muestra el formulario del reporte con selector de sucursal
     */
    public function porSucursal()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        // Obtener todas las sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        // Lista de cuentas
        $cuentas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCliente', $clienteId)
            ->where('AbiertoCerrado', 0)
            ->orderBy('Cuenta')
            ->get(['IdCuenta as id', 'Cuenta', 'Descripcion']);

        // Lista de identificadores
        $identificadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador')
            ->orderBy('CI_NIT')
            ->get(['IdIdentificador as id', 'CI_NIT', 'Nombre']);

        return Inertia::render('Gestion/Reportes/MayorCuentaPorSucursal', [
            'empresa' => $empresa,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalId,
            'cuentas' => $cuentas,
            'identificadores' => $identificadores,
        ]);
    }

    /**
     * Exporta según los parámetros GET (para la vista por sucursal)
     */
    public function exportarPorSucursal(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'cuenta_id' => 'required|exists:conta_cuenta,IdCuenta',
            'fecha_inicial' => 'required|date',
            'identificador_id' => 'nullable|exists:todos_identificador,IdIdentificador',
            'moneda' => 'required|in:1,2',
        ]);

        $sucursalId = $request->sucursal_id;
        $cuentaId = $request->cuenta_id;
        $fechaMayor = $request->fecha_inicial;
        $identificadorId = $request->identificador_id;
        $moneda = $request->moneda;

        // Guardar temporalmente la sucursal original de la sesión
        $sucursalOriginal = session('cliente_sucursal_id');
        
        // Cambiar a la sucursal seleccionada
        session(['cliente_sucursal_id' => $sucursalId]);
        
        // Exportar según combinación
        if (empty($identificadorId)) {
            if ($moneda == 1) {
                $resultado = $this->exportarBolivianos($cuentaId, $fechaMayor);
            } else {
                $resultado = $this->exportarOtraMoneda($cuentaId, $fechaMayor);
            }
        } else {
            if ($moneda == 1) {
                $resultado = $this->exportarBolivianosConIdentificador($cuentaId, $fechaMayor, $identificadorId);
            } else {
                $resultado = $this->exportarOtraMonedaConIdentificador($cuentaId, $fechaMayor, $identificadorId);
            }
        }
        
        // Restaurar la sucursal original
        session(['cliente_sucursal_id' => $sucursalOriginal]);
        
        return $resultado;
    }
}