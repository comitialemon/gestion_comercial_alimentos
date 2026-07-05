<?php

namespace App\Http\Controllers\Gestion\Reportes\Operacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use TCPDF;
use Inertia\Inertia;

class ArqueoCajaChicaAutomaticoController extends Controller
{
    /**
     * Muestra el formulario del reporte con datos precargados
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        // Obtener datos de la sucursal actual
        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // Obtener datos del operador logueado (incluyendo IdIdentificador)
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first([
                'todos_operador.IdOperador as id',
                DB::raw("CONCAT(todos_identificador.CI_NIT, '-', todos_identificador.Nombre) as nombre_completo"),
                'todos_identificador.IdIdentificador'
            ]);
        
        // Obtener fechas disponibles
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', '>', '2020-01-01')
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha', 'Fecha']);
        
        // Formatear fechas en PHP
        $fechasFormateadas = $fechas->map(function ($item) {
            $item->fecha_formateada = date('d/m/Y', strtotime($item->Fecha));
            return $item;
        });
        
        // Obtener la fecha más reciente como predeterminada
        $fechaDefault = $fechasFormateadas->first();
        
        return Inertia::render('Gestion/Reportes/Operacion/ArqueoCajaChicaAutomatico', [
            'sucursal' => $sucursal,
            'operador' => $operador,
            'fechas' => $fechasFormateadas,
            'fechaDefault' => $fechaDefault ? $fechaDefault->IdFecha : null,
        ]);
    }
    
    /**
     * Genera el PDF de Arqueo de Caja Chica del operador logueado
     */
    public function generarPdf(Request $request)
    {
        $request->validate([
            'fecha_id' => 'required|integer|exists:todos_fecha,IdFecha',
        ]);
        
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $fechaId = $request->fecha_id;
        
        // =============================================
        // OBTENER DATOS DE CONFIGURACIÓN
        // =============================================
        
        // Obtener cuenta de Caja Chica desde parámetros
        $idCuentaCajaChica = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_parametros_cuentas')
            ->where('IdCliente', $clienteId)
            ->value('CajaChica');
        
        // Si no existe, buscar en conta_cuenta
        if (!$idCuentaCajaChica) {
            $idCuentaCajaChica = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_cuenta')
                ->where('Descripcion', 'like', '%Caja Chica%')
                ->where('IdCliente', $clienteId)
                ->value('IdCuenta');
        }
        
        if (!$idCuentaCajaChica) {
            throw new \Exception('No se encontró la cuenta de Caja Chica en los parámetros. Verifique la configuración.');
        }
        
        // 🔥 OBTENER EL IDENTIFICADOR DEL OPERADOR LOGUEADO
        $operadorData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first([
                'todos_operador.IdOperador',
                'todos_identificador.IdIdentificador',  // ← Este es el campo clave
                'todos_identificador.Nombre',
                'todos_identificador.CI_NIT'
            ]);
        
        $identificadorOperador = $operadorData->IdIdentificador;
        $nombreOperador = $operadorData->Nombre ?? 'Sin operador';
        $ciNitOperador = $operadorData->CI_NIT ?? '';
        
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
        // 🔥 USANDO IdIdentificador EN LUGAR DE IdOperadorIngreso
        // =============================================
        
        // Saldo Debe Anterior
        $totalAnteriorDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente')
            ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('todos_fecha.Fecha', '<', $fecha)
            ->where('conta_diario_propiamente.D_H', 'D')
            ->where('conta_diario_propiamente.IdCuenta', $idCuentaCajaChica)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorOperador)  // ← CAMBIADO
            ->sum('conta_diario_propiamente.MontoBolivianos');
        
        // Saldo Haber Anterior
        $totalAnteriorHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente')
            ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('todos_fecha.Fecha', '<', $fecha)
            ->where('conta_diario_propiamente.D_H', 'H')
            ->where('conta_diario_propiamente.IdCuenta', $idCuentaCajaChica)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorOperador)  // ← CAMBIADO
            ->sum('conta_diario_propiamente.MontoBolivianos');
        
        $totalAnteriorSaldo = (float) $totalAnteriorDebe - (float) $totalAnteriorHaber;
        
        // =============================================
        // OBTENER INGRESOS DEL DÍA (DEBE)
        // 🔥 USANDO IdIdentificador EN LUGAR DE IdOperadorIngreso
        // =============================================
        
        $ingresos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente')
            ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('todos_fecha.Fecha', $fecha)
            ->where('conta_diario_propiamente.IdCuenta', $idCuentaCajaChica)
            ->where('conta_diario_propiamente.D_H', 'D')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('conta_diario.Contabilizado', 1)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorOperador)  // ← CAMBIADO
            ->select(
                'conta_diario_propiamente.Glosa',
                'conta_diario_propiamente.MontoBolivianos',
                'conta_diario_propiamente.IdIdentificador',
                'conta_diario.NumeroDiario'
            )
            ->orderBy('conta_diario.NumeroDiario')
            ->get();
        
        // =============================================
        // OBTENER EGRESOS DEL DÍA (HABER)
        // 🔥 USANDO IdIdentificador EN LUGAR DE IdOperadorIngreso
        // =============================================
        
        $egresos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario_propiamente')
            ->join('conta_diario', 'conta_diario_propiamente.IdDiario', '=', 'conta_diario.IdDiario')
            ->join('todos_fecha', 'conta_diario.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('todos_fecha.Fecha', $fecha)
            ->where('conta_diario_propiamente.IdCuenta', $idCuentaCajaChica)
            ->where('conta_diario_propiamente.D_H', 'H')
            ->where('conta_diario.IdCliente', $clienteId)
            ->where('conta_diario.Contabilizado', 1)
            ->where('conta_diario.IdSucursal', $sucursalId)
            ->where('conta_diario_propiamente.IdIdentificador', $identificadorOperador)  // ← CAMBIADO
            ->select(
                'conta_diario_propiamente.Glosa',
                'conta_diario_propiamente.MontoBolivianos',
                'conta_diario_propiamente.IdIdentificador'
            )
            ->orderBy('conta_diario.NumeroDiario')
            ->get();
        
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
        $y = 10;
        
        // Empresa
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 5, $nombreCliente, 0, 1, 'C');
        $y += 5;
        
        // Sucursal
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 4, $nombreSucursal, 0, 1, 'C');
        $y += 6;
        
        // Título
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 5, 'Arqueo - (Caja Chica Bs.)', 0, 1, 'C');
        $y += 5;
        
        // Operador
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(5, $y);
        $pdf->Cell(70, 4, $nombreOperador . ' - ' . $ciNitOperador, 0, 1, 'C');
        $y += 5;
        
        // Fecha
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
        
        if ($ingresos->isEmpty()) {
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 4, '  Sin ingresos registrados', 0, 1, 'L');
            $y += 5;
        } else {
            foreach ($ingresos as $ingreso) {
                $monto = (float) $ingreso->MontoBolivianos;
                $totalIngresos += $monto;
                
                // Obtener nombre del identificador
                $nombreIdentificador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_identificador')
                    ->where('IdIdentificador', $ingreso->IdIdentificador)
                    ->value('Nombre');
                
                $glosaCompleta = $ingreso->Glosa . ', No ' . $ingreso->NumeroDiario . ' - ' . ($nombreIdentificador ?? '');
                
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
        
        if ($egresos->isEmpty()) {
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 4, '  Sin egresos registrados', 0, 1, 'L');
            $y += 5;
        } else {
            foreach ($egresos as $egreso) {
                $monto = (float) $egreso->MontoBolivianos;
                $totalEgresos += $monto;
                
                // Obtener nombre del identificador
                $nombreIdentificador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_identificador')
                    ->where('IdIdentificador', $egreso->IdIdentificador)
                    ->value('Nombre');
                
                $glosaCompleta = $egreso->Glosa . ' - ' . ($nombreIdentificador ?? '');
                
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
        $nombreArchivo = 'ArqueoCajaChica_' . $nombreOperador . '_' . $fechaCabecera . '.pdf';
        
        $pdf->Output($nombreArchivo, 'I');
        exit();
    }
}