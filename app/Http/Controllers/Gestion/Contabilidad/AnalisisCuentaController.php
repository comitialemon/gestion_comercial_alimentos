<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AnalisisCuentaController extends Controller
{
    /**
     * Mostrar formulario de análisis de cuenta
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $tipoOperador = session('operador_tipo_id');

        // 🔧 CORREGIDO: Agregar 'c.Cuenta' al SELECT
        $cuentas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('c.IdCliente', $clienteId)
            ->where('c.AbiertoCerrado', 0)
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' ', c.Descripcion) as nombre"), 'c.Cuenta')
            ->distinct()
            ->orderBy('c.Cuenta')
            ->get();

        // Obtener sucursales (para supervisores)
        $sucursales = [];
        $tieneMultiplesSucursales = false;

        $esSupervisor = in_array($tipoOperador, [1, 2]);

        if ($esSupervisor) {
            $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdCliente', $clienteId)
                ->orderBy('Nombre')
                ->get(['IdClienteSucursal as id', 'Nombre as nombre']);
            
            $tieneMultiplesSucursales = $sucursales->count() > 1;
        }

        return Inertia::render('Gestion/Contabilidad/AnalisisCuenta/Index', [
            'cuentas' => $cuentas,
            'sucursales' => $sucursales,
            'sucursalId' => $sucursalId,
            'tieneMultiplesSucursales' => $tieneMultiplesSucursales,
            'esSupervisor' => $esSupervisor,
        ]);
    }

    /**
     * Generar reporte Excel
     */
    public function generarExcel(Request $request)
    {
        $request->validate([
            'Cuenta' => 'required|exists:conta_cuenta,IdCuenta',
            'Fecha' => 'required|date',
            'FechaFinal' => 'required|date|after_or_equal:Fecha',
            'SucursalId' => 'nullable|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = $request->SucursalId ?? session('cliente_sucursal_id');
        $cuentaId = $request->Cuenta;

        // Obtener datos de la cuenta
        $cuenta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCuenta', $cuentaId)
            ->first();

        $IdMonedaCuenta = $cuenta->IdMoneda ?? 1;
        $CuentaNumero = $cuenta->Cuenta;
        $Descripcion = $cuenta->Descripcion;

        $monedaInforme = $IdMonedaCuenta == 1 ? 'Expresado en Dolares' : 'Expresado en Bolivianos';

        // Datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first();

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first();

        $nombreSucursal = $sucursal ? $sucursal->Nombre : '';

        $fechaInicial = date('d-m-Y', strtotime($request->Fecha));
        $fechaFinal = date('d-m-Y', strtotime($request->FechaFinal));

        // Crear Excel
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Estilos
        $styleTitulo = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 16,
                'name' => 'Arial',
            ],
        ];

        $styleSubTitulo = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'font' => [
                'bold' => true,
                'size' => 8,
                'name' => 'Arial',
            ],
        ];

        $styleCenter = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];

        $styleBorde = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];

        // Cabecera
        $worksheet->setCellValue('A1', $empresa->Nombre ?? '');
        $worksheet->setCellValue('A2', $nombreSucursal);
        $worksheet->setCellValue('A3', "Analisis de la Cuenta : {$CuentaNumero} {$Descripcion}");
        $worksheet->setCellValue('A4', "({$monedaInforme})");
        $worksheet->setCellValue('A6', 'Identificador');
        $worksheet->setCellValue('B6', 'Nombre');
        $worksheet->setCellValue('C6', 'Saldo al');
        $worksheet->setCellValue('C7', $fechaInicial);
        $worksheet->setCellValue('D6', 'Debe');
        $worksheet->setCellValue('E6', 'Haber');
        $worksheet->setCellValue('F6', 'Saldo al');
        $worksheet->setCellValue('F7', $fechaFinal);

        $worksheet->getStyle('A1:F4')->applyFromArray($styleTitulo);
        $worksheet->getStyle('A3')->applyFromArray($styleSubTitulo);
        $worksheet->getStyle('A6:F7')->applyFromArray($styleCenter);
        $worksheet->getStyle('A6:B7')->applyFromArray($styleBorde);
        $worksheet->getStyle('D6:E7')->applyFromArray($styleBorde);
        $worksheet->getStyle('C6:F6')->applyFromArray($styleBorde);
        $worksheet->getStyle('C6:F7')->applyFromArray($styleBorde);
        $worksheet->getStyle('F6:F7')->applyFromArray($styleBorde);

        // Combinar celdas
        $worksheet->mergeCells('A1:F1');
        $worksheet->mergeCells('A2:F2');
        $worksheet->mergeCells('A3:F3');
        $worksheet->mergeCells('A4:F4');
        $worksheet->mergeCells('A6:A7');
        $worksheet->mergeCells('B6:B7');
        $worksheet->mergeCells('D6:D7');
        $worksheet->mergeCells('E6:E7');

        // Obtener identificadores
        $identificadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente as dp')
            ->join('todos_identificador as i', 'dp.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('dp.IdCuenta', $cuentaId)
            ->groupBy('dp.IdIdentificador', 'i.CI_NIT', 'i.Nombre')
            ->select('dp.IdIdentificador', 'i.CI_NIT', 'i.Nombre')
            ->orderBy('i.Nombre')
            ->get();

        $fila = 8;
        $worksheet->getColumnDimension('A')->setWidth(15);
        $worksheet->getColumnDimension('B')->setWidth(50);
        $worksheet->getColumnDimension('C')->setWidth(15);
        $worksheet->getColumnDimension('D')->setWidth(15);
        $worksheet->getColumnDimension('E')->setWidth(15);
        $worksheet->getColumnDimension('F')->setWidth(15);

        foreach ($identificadores as $identificador) {
            $idIdentificador = $identificador->IdIdentificador;
            $ciNit = $identificador->CI_NIT;
            $nombre = $identificador->Nombre;

            $saldoAnterior = $this->calcularSaldoAnterior($cuentaId, $idIdentificador, $request->Fecha, $IdMonedaCuenta, $clienteId, $sucursalId);
            $movimientos = $this->calcularMovimientosRango($cuentaId, $idIdentificador, $request->Fecha, $request->FechaFinal, $IdMonedaCuenta, $clienteId, $sucursalId);
            
            $totalDebeRango = $movimientos['debe'];
            $totalHaberRango = $movimientos['haber'];

            if ($saldoAnterior == 0 && $totalDebeRango == 0 && $totalHaberRango == 0) {
                continue;
            }

            $worksheet->setCellValue('A' . $fila, $ciNit);
            $worksheet->setCellValue('B' . $fila, $nombre);
            $worksheet->setCellValue('C' . $fila, $saldoAnterior);
            $worksheet->setCellValue('D' . $fila, $totalDebeRango);
            $worksheet->setCellValue('E' . $fila, $totalHaberRango);
            $worksheet->setCellValue('F' . $fila, "=C{$fila}+D{$fila}-E{$fila}");

            $fila++;
        }

        if ($fila > 8) {
            $filaSuma = $fila - 1;
            $worksheet->setCellValue('C' . $fila, "=SUM(C8:C{$filaSuma})");
            $worksheet->setCellValue('D' . $fila, "=SUM(D8:D{$filaSuma})");
            $worksheet->setCellValue('E' . $fila, "=SUM(E8:E{$filaSuma})");
            $worksheet->setCellValue('F' . $fila, "=SUM(F8:F{$filaSuma})");
            $worksheet->setCellValue('A' . $fila, 'Totales');
            $worksheet->mergeCells('A' . $fila . ':B' . $fila);
            $worksheet->getStyle('A' . $fila)->applyFromArray($styleCenter);
            $worksheet->getStyle('A' . $fila . ':F' . $fila)->applyFromArray($styleBorde);
        }

        if ($fila > 8) {
            $worksheet->getStyle('C8:F' . $fila)->getNumberFormat()->setFormatCode('_(#,##0.00_);_(\(#,##0.00\)');
        }

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="AnalisisDeCuenta.xls"');
        header('Cache-Control: max-age=0');

        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function calcularSaldoAnterior($cuentaId, $identificadorId, $fecha, $idMoneda, $clienteId, $sucursalId)
    {
        $totalDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente as dp')
            ->join('conta_diario as d', 'dp.IdDiario', '=', 'd.IdDiario')
            ->join('todos_fecha as f', 'd.IdFecha', '=', 'f.IdFecha')
            ->where('d.IdCliente', $clienteId)
            ->where('d.IdSucursal', $sucursalId)
            ->where('dp.IdCuenta', $cuentaId)
            ->where('dp.IdIdentificador', $identificadorId)
            ->where('dp.D_H', 'D')
            ->where('f.Fecha', '<=', $fecha)
            ->select(DB::raw($idMoneda == 2 ? 'SUM(dp.MontoBolivianos) as total' : 'SUM(dp.MontoOtraMoneda) as total'))
            ->value('total') ?? 0;

        $totalHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente as dp')
            ->join('conta_diario as d', 'dp.IdDiario', '=', 'd.IdDiario')
            ->join('todos_fecha as f', 'd.IdFecha', '=', 'f.IdFecha')
            ->where('d.IdCliente', $clienteId)
            ->where('d.IdSucursal', $sucursalId)
            ->where('dp.IdCuenta', $cuentaId)
            ->where('dp.IdIdentificador', $identificadorId)
            ->where('dp.D_H', 'H')
            ->where('f.Fecha', '<=', $fecha)
            ->select(DB::raw($idMoneda == 2 ? 'SUM(dp.MontoBolivianos) as total' : 'SUM(dp.MontoOtraMoneda) as total'))
            ->value('total') ?? 0;

        return round($totalDebe - $totalHaber, 2);
    }

    private function calcularMovimientosRango($cuentaId, $identificadorId, $fechaInicio, $fechaFin, $idMoneda, $clienteId, $sucursalId)
    {
        $totalDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente as dp')
            ->join('conta_diario as d', 'dp.IdDiario', '=', 'd.IdDiario')
            ->join('todos_fecha as f', 'd.IdFecha', '=', 'f.IdFecha')
            ->where('d.IdCliente', $clienteId)
            ->where('d.IdSucursal', $sucursalId)
            ->where('dp.IdCuenta', $cuentaId)
            ->where('dp.IdIdentificador', $identificadorId)
            ->where('dp.D_H', 'D')
            ->where('f.Fecha', '>', $fechaInicio)
            ->where('f.Fecha', '<=', $fechaFin)
            ->select(DB::raw($idMoneda == 2 ? 'SUM(dp.MontoBolivianos) as total' : 'SUM(dp.MontoOtraMoneda) as total'))
            ->value('total') ?? 0;

        $totalHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente as dp')
            ->join('conta_diario as d', 'dp.IdDiario', '=', 'd.IdDiario')
            ->join('todos_fecha as f', 'd.IdFecha', '=', 'f.IdFecha')
            ->where('d.IdCliente', $clienteId)
            ->where('d.IdSucursal', $sucursalId)
            ->where('dp.IdCuenta', $cuentaId)
            ->where('dp.IdIdentificador', $identificadorId)
            ->where('dp.D_H', 'H')
            ->where('f.Fecha', '>', $fechaInicio)
            ->where('f.Fecha', '<=', $fechaFin)
            ->select(DB::raw($idMoneda == 2 ? 'SUM(dp.MontoBolivianos) as total' : 'SUM(dp.MontoOtraMoneda) as total'))
            ->value('total') ?? 0;

        return [
            'debe' => round($totalDebe, 2),
            'haber' => round($totalHaber, 2),
        ];
    }
}