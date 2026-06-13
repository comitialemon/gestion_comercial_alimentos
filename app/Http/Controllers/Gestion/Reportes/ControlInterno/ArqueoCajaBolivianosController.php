<?php

namespace App\Http\Controllers\Gestion\Reportes\ControlInterno;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use TCPDF;
use Inertia\Inertia;

class ArqueoCajaBolivianosController extends Controller
{
    /**
     * Muestra el formulario del reporte
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        
        // Obtener sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // Obtener fechas disponibles
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', '>', '2020-01-01')
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha', 'Fecha', DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y') as fecha_formateada")]);
        
        return Inertia::render('Gestion/Reportes/ControlInterno/ArqueoCajaBolivianos', [
            'sucursales' => $sucursales,
            'fechas' => $fechas,
        ]);
    }
    
    /**
     * Genera el PDF de Arqueo de Caja Bolivianos
     */
    public function generarPdf(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:todos_cliente_sucursal,IdClienteSucursal',
            'fecha_id' => 'required|integer|exists:todos_fecha,IdFecha',
        ]);
        
        $clienteId = session('cliente_id');
        $sucursalId = $request->sucursal_id;
        $fechaId = $request->fecha_id;
        
        // =============================================
        // OBTENER DATOS DE CONFIGURACIÓN
        // =============================================
        
        // Obtener cuenta de Caja Bolivianos desde parámetros
        $idCuentaBolivianos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_parametros_cuentas')
            ->where('IdCliente', $clienteId)
            ->value('CajaBolivianos');
        
        if (!$idCuentaBolivianos) {
            throw new \Exception('No se encontró la cuenta de Caja Bolivianos en los parámetros');
        }
        
        // Obtener fecha del arqueo
        $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $fechaId)
            ->first(['Fecha']);
        
        $fecha = $fechaData->Fecha;
        $fechaCabecera = date('d-m-Y', strtotime($fecha));
        
        // Datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre']);
        
        // Nombre de la sucursal
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre']);
        
        $nombreCliente = $empresa->Nombre ?? '';
        $nombreSucursal = $sucursal->Nombre ?? '';
        
        // =============================================
        // CALCULAR SALDOS ANTERIORES
        // =============================================
        
        // Saldo Debe Anterior
        $totalAnteriorDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente')
            ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('todos_fecha.Fecha', '<', $fecha)
            ->where('conta_diario_propiamente.D_H', 'D')
            ->where('conta_diario_propiamente.IdCuenta', $idCuentaBolivianos)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->sum('conta_diario_propiamente.MontoBolivianos');
        
        // Saldo Haber Anterior
        $totalAnteriorHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente')
            ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('todos_fecha.Fecha', '<', $fecha)
            ->where('conta_diario_propiamente.D_H', 'H')
            ->where('conta_diario_propiamente.IdCuenta', $idCuentaBolivianos)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->sum('conta_diario_propiamente.MontoBolivianos');
        
        $totalAnteriorSaldo = (float) $totalAnteriorDebe - (float) $totalAnteriorHaber;
        
        // =============================================
        // OBTENER INGRESOS DEL DÍA (DEBE)
        // =============================================
        
        $ingresos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente')
            ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('todos_fecha.Fecha', $fecha)
            ->where('conta_diario_propiamente.IdCuenta', $idCuentaBolivianos)
            ->where('conta_diario_propiamente.D_H', 'D')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('conta_diario.Contabilizado', 1)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->select(
                'conta_diario_propiamente.Glosa',
                'conta_diario_propiamente.MontoBolivianos',
                'conta_diario_propiamente.IdIdentificador'
            )
            ->get();
        
        $totalIngresos = 0;
        
        // =============================================
        // OBTENER EGRESOS DEL DÍA (HABER)
        // =============================================
        
        $egresos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente')
            ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('todos_fecha.Fecha', $fecha)
            ->where('conta_diario_propiamente.IdCuenta', $idCuentaBolivianos)
            ->where('conta_diario_propiamente.D_H', 'H')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('conta_diario.Contabilizado', 1)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->select(
                'conta_diario_propiamente.Glosa',
                'conta_diario_propiamente.MontoBolivianos',
                'conta_diario_propiamente.IdIdentificador'
            )
            ->get();
        
        $totalEgresos = 0;
        
        // =============================================
        // CALCULAR SALDO ACTUAL
        // =============================================
        
        $saldoActual = $totalAnteriorSaldo + $totalIngresos - $totalEgresos;
        
        // =============================================
        // GENERAR PDF CON TCPDF
        // =============================================
        
        // Ancho de página: 80mm (ticket térmico)
        $pdf = new TCPDF('P', 'mm', array(80, 300), true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(5, 5, 5);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();
        
        // Fuentes
        $pdf->SetFont('helvetica', 'B', 10);
        
        // =============================================
        // CABECERA
        // =============================================
        $y = 5;
        
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 5, $nombreCliente, 0, 1, 'C');
        $y += 5;
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 4, $nombreSucursal, 0, 1, 'C');
        $y += 5;
        
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 5, 'Arqueo - (Caja Bolivianos)', 0, 1, 'C');
        $y += 5;
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 4, $fechaCabecera, 0, 1, 'C');
        $y += 8;
        
        // =============================================
        // LÍNEA SEPARADORA
        // =============================================
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 2, '------------------------------------------------', 0, 1, 'L');
        $y += 3;
        
        // =============================================
        // SALDO ANTERIOR
        // =============================================
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(45, 4, 'Saldo Anterior', 0, 0, 'L');
        $pdf->SetXY(55, $y);
        $pdf->Cell(20, 4, number_format($totalAnteriorSaldo, 2, '.', ','), 0, 1, 'R');
        $y += 4;
        
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 2, '------------------------------------------------', 0, 1, 'L');
        $y += 6;
        
        // =============================================
        // INGRESOS
        // =============================================
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 4, 'MAS : Ingresos', 0, 1, 'L');
        $y += 5;
        
        $pdf->SetFont('helvetica', '', 7);
        $totalIngresos = 0;
        
        foreach ($ingresos as $ingreso) {
            // Obtener nombre del identificador
            $nombreIdentificador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('IdIdentificador', $ingreso->IdIdentificador)
                ->value('Nombre');
            
            $glosaCompleta = $ingreso->Glosa . ' - ' . ($nombreIdentificador ?? '');
            $monto = (float) $ingreso->MontoBolivianos;
            $totalIngresos += $monto;
            
            // Calcular altura del MultiCell
            $alturaTexto = $pdf->getStringHeight(50, $glosaCompleta);
            $alturaFila = max(4, $alturaTexto);
            
            // GLOSA
            $pdf->SetXY(5, $y);
            $pdf->MultiCell(50, $alturaFila, $glosaCompleta, 0, 'L');
            $y = $pdf->GetY();
            
            // MONTO
            $pdf->SetXY(55, $y - $alturaFila);
            $pdf->Cell(20, $alturaFila, number_format($monto, 2, '.', ','), 0, 1, 'R');
            
            // Control de página
            if ($y > 260) {
                $pdf->AddPage();
                $y = 20;
            }
        }
        
        // Total Ingresos
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 2, '------------------------------------------------', 0, 1, 'L');
        $y += 3;
        
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(45, 4, 'Total Ingresos', 0, 0, 'R');
        $pdf->SetXY(55, $y);
        $pdf->Cell(20, 4, number_format($totalIngresos, 2, '.', ','), 0, 1, 'R');
        $y += 6;
        
        // =============================================
        // EGRESOS
        // =============================================
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 4, 'MENOS : Egresos', 0, 1, 'L');
        $y += 5;
        
        $pdf->SetFont('helvetica', '', 7);
        $totalEgresos = 0;
        
        foreach ($egresos as $egreso) {
            // Obtener nombre del identificador
            $nombreIdentificador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('IdIdentificador', $egreso->IdIdentificador)
                ->value('Nombre');
            
            $glosaCompleta = $egreso->Glosa . ' - ' . ($nombreIdentificador ?? '');
            $monto = (float) $egreso->MontoBolivianos;
            $totalEgresos += $monto;
            
            // Calcular altura del MultiCell
            $alturaTexto = $pdf->getStringHeight(50, $glosaCompleta);
            $alturaFila = max(4, $alturaTexto);
            
            // GLOSA
            $pdf->SetXY(5, $y);
            $pdf->MultiCell(50, $alturaFila, $glosaCompleta, 0, 'L');
            $y = $pdf->GetY();
            
            // MONTO
            $pdf->SetXY(55, $y - $alturaFila);
            $pdf->Cell(20, $alturaFila, number_format($monto, 2, '.', ','), 0, 1, 'R');
            
            // Control de página
            if ($y > 260) {
                $pdf->AddPage();
                $y = 20;
            }
        }
        
        // Total Egresos
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 2, '------------------------------------------------', 0, 1, 'L');
        $y += 3;
        
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(45, 4, 'Total Egresos', 0, 0, 'R');
        $pdf->SetXY(55, $y);
        $pdf->Cell(20, 4, number_format($totalEgresos, 2, '.', ','), 0, 1, 'R');
        $y += 6;
        
        // =============================================
        // SALDO ACTUAL
        // =============================================
        $saldoActual = $totalAnteriorSaldo + $totalIngresos - $totalEgresos;
        
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 2, '------------------------------------------------', 0, 1, 'L');
        $y += 3;
        
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(45, 4, 'Saldo Actual', 0, 0, 'L');
        $pdf->SetXY(55, $y);
        $pdf->Cell(20, 4, number_format($saldoActual, 2, '.', ','), 0, 1, 'R');
        $y += 4;
        
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 4, '=============================', 0, 1, 'C');
        
        // =============================================
        // SALIDA DEL PDF
        // =============================================
        $nombreArchivo = 'ArqueoCajaBolivianos_' . $fechaCabecera . '.pdf';
        
        $pdf->Output($nombreArchivo, 'I');
        exit();
    }
}