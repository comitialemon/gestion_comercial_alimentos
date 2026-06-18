<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EstadoResultadosA3Controller extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $tipoOperador = session('operador_tipo_id');

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        $fecha = date('Y-m-d');

        return Inertia::render('Gestion/Contabilidad/EstadoResultadosA3/Index', [
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalId,
            'fecha' => $fecha,
            'tipoOperador' => $tipoOperador,
        ]);
    }

    public function generar(Request $request)
    {
        $request->validate([
            'sucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'fecha' => 'required|date',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = $request->sucursal;
        $fecha = $request->fecha;

        // Datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first();

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first();

        // Fecha inicial (1 de enero del año)
        $fechaObj = new \DateTime($fecha);
        $anio = (int)$fechaObj->format('Y');
        $fechaInicio = $anio . '-01-01';

        // Obtener IDs de fechas
        $idFechaInicio = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', $fechaInicio)
            ->value('IdFecha');

        $idFechaFin = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', $fecha)
            ->value('IdFecha');

        if (!$idFechaInicio || !$idFechaFin) {
            return response()->json(['error' => 'Fechas no encontradas en la tabla todos_fecha'], 404);
        }

        // =============================================
        // 1. OBTENER CUENTAS PRINCIPALES (___._)
        // =============================================
        $cuentasPrincipales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCliente', $clienteId)
            ->where('TipoDeCuenta', 'P')
            ->whereRaw("Cuenta LIKE '___._'")
            ->orderBy('Cuenta')
            ->get();

        $totalResultadosA3Deducible = 0;
        $totalResultadosA3NoDeducible = 0;
        $resultados = [];

        foreach ($cuentasPrincipales as $principal) {
            $totalGrupoCuentaDeducible = 0;
            $totalGrupoCuentaNoDeducible = 0;
            $detalles = [];

            // Obtener subcuentas (____.__)
            $subcuentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_cuenta')
                ->where('IdCliente', $clienteId)
                ->where('Cuenta', 'like', substr($principal->Cuenta, 0, 5) . '__')
                ->orderBy('Cuenta')
                ->get();

            foreach ($subcuentas as $subcuenta) {
                $cuentaAux = $subcuenta->Cuenta;
                
                // =============================================
                // CALCULAR TOTAL DEDUCIBLE (D_H = 'D' - 'H')
                // =============================================
                $totalDebeCuentaDeducible = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario as d')
                    ->join('conta_diario_propiamente as dp', 'd.IdDiario', '=', 'dp.IdDiario')
                    ->join('conta_cuenta as c', 'dp.IdCuenta', '=', 'c.IdCuenta')
                    ->where('d.IdCliente', $clienteId)
                    ->where('d.IdSucursal', $sucursalId)
                    ->whereBetween('d.IdFecha', [$idFechaInicio, $idFechaFin])
                    ->where('c.Cuenta', 'like', $cuentaAux)
                    ->where('dp.D_H', 'D')
                    ->sum('dp.MontoBolivianos') ?? 0;

                $totalHaberCuentaDeducible = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario as d')
                    ->join('conta_diario_propiamente as dp', 'd.IdDiario', '=', 'dp.IdDiario')
                    ->join('conta_cuenta as c', 'dp.IdCuenta', '=', 'c.IdCuenta')
                    ->where('d.IdCliente', $clienteId)
                    ->where('d.IdSucursal', $sucursalId)
                    ->whereBetween('d.IdFecha', [$idFechaInicio, $idFechaFin])
                    ->where('c.Cuenta', 'like', $cuentaAux)
                    ->where('dp.D_H', 'H')
                    ->sum('dp.MontoBolivianos') ?? 0;

                $totalCuentaDeducible = ($totalDebeCuentaDeducible - $totalHaberCuentaDeducible) * -1;

                // =============================================
                // CALCULAR NO DEDUCIBLE (SOLO PARA GASTOS)
                // =============================================
                $totalCuentaNoDeducible = 0;

                // Verificar si es cuenta de gasto (> 4__.___)
                if (floatval($cuentaAux) > 40000) {
                    $totalDebeCuentaNoDeducible = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('conta_diario as d')
                        ->join('conta_diario_propiamente as dp', 'd.IdDiario', '=', 'dp.IdDiario')
                        ->join('conta_cuenta as c', 'dp.IdCuenta', '=', 'c.IdCuenta')
                        ->where('d.IdCliente', $clienteId)
                        ->where('d.IdSucursal', $sucursalId)
                        ->whereBetween('d.IdFecha', [$idFechaInicio, $idFechaFin])
                        ->where('c.Cuenta', 'like', $cuentaAux)
                        ->where('dp.D_H', 'D')
                        ->where('dp.Deducible', 'N')
                        ->sum('dp.MontoBolivianos') ?? 0;

                    $totalHaberCuentaNoDeducible = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('conta_diario as d')
                        ->join('conta_diario_propiamente as dp', 'd.IdDiario', '=', 'dp.IdDiario')
                        ->join('conta_cuenta as c', 'dp.IdCuenta', '=', 'c.IdCuenta')
                        ->where('d.IdCliente', $clienteId)
                        ->where('d.IdSucursal', $sucursalId)
                        ->whereBetween('d.IdFecha', [$idFechaInicio, $idFechaFin])
                        ->where('c.Cuenta', 'like', $cuentaAux)
                        ->where('dp.D_H', 'H')
                        ->where('dp.Deducible', 'N')
                        ->sum('dp.MontoBolivianos') ?? 0;

                    $totalCuentaNoDeducible = ($totalDebeCuentaNoDeducible - $totalHaberCuentaNoDeducible) * -1;
                }

                // Acumular totales del grupo
                $totalGrupoCuentaDeducible += $totalCuentaDeducible;
                $totalGrupoCuentaNoDeducible += $totalCuentaNoDeducible;

                // Guardar detalle si tiene movimiento
                if ($totalCuentaDeducible != 0 || $totalCuentaNoDeducible != 0) {
                    $detalles[] = [
                        'cuenta' => $cuentaAux,
                        'descripcion' => $subcuenta->Descripcion,
                        'saldo_deducible' => $totalCuentaDeducible,
                        'saldo_no_deducible' => $totalCuentaNoDeducible,
                    ];
                }
            }

            // Totales por grupo
            $totalResultadosA3Deducible += $totalGrupoCuentaDeducible;
            $totalResultadosA3NoDeducible += $totalGrupoCuentaNoDeducible;

            if ($totalGrupoCuentaDeducible != 0 || $totalGrupoCuentaNoDeducible != 0 || count($detalles) > 0) {
                $resultados[] = [
                    'cuenta_principal' => $principal->Cuenta,
                    'descripcion' => $principal->Descripcion,
                    'detalles' => $detalles,
                    'total_deducible' => $totalGrupoCuentaDeducible,
                    'total_no_deducible' => $totalGrupoCuentaNoDeducible,
                ];
            }
        }

        // Generar PDF
        $this->generarPDF($empresa, $sucursal, $fechaInicio, $fecha, $resultados, $totalResultadosA3Deducible, $totalResultadosA3NoDeducible);
    }

    private function generarPDF($empresa, $sucursal, $fechaInicio, $fechaFin, $resultados, $totalDeducible, $totalNoDeducible)
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 60);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 9);

        // =============================================
        // ENCABEZADO
        // =============================================
        $pdf->SetXY(20, 7);
        $pdf->SetFont('courier', 'B', 10);
        $pdf->Cell(10, 3, $empresa->Nombre ?? 'EMPRESA', 0, 1, 'L');

        $pdf->SetXY(20, 11);
        $pdf->SetFont('courier', '', 9);
        $pdf->Cell(10, 3, $sucursal->Nombre ?? 'SUCURSAL', 0, 1, 'L');

        $pdf->SetXY(90, 18);
        $pdf->SetFont('courier', 'U', 14);
        $pdf->Cell(15, 3, 'ESTADO DE RESULTADOS A3', 0, 1, 'C');

        $pdf->SetXY(85, 24);
        $pdf->SetFont('courier', '', 9);
        $pdf->Cell(32, 3, "Del " . date('d/m/Y', strtotime($fechaInicio)) . " al " . date('d/m/Y', strtotime($fechaFin)), 0, 1, 'C');

        // =============================================
        // CABECERA DE TABLA
        // =============================================
        $y = 32;
        $pdf->SetXY(15, $y);
        $pdf->SetFont('courier', 'U', 10);
        $pdf->Cell(20, 5, 'Cuenta', 0, 0, 'L');
        $pdf->Cell(70, 5, 'Descripcion', 0, 0, 'L');
        $pdf->Cell(35, 5, 'Resultados', 0, 0, 'R');
        $pdf->Cell(10, 5, 'D/H', 0, 0, 'C');
        $pdf->Cell(35, 5, 'No Deducible', 0, 0, 'R');
        $pdf->Cell(10, 5, 'D/H', 0, 0, 'C');

        $y = 38;
        $pdf->SetXY(15, $y);
        $pdf->Cell(180, 2, '------------------------------------------------------------------------------------------------------------------------------------', 0, 1, 'L');

        $y = 42;
        $pdf->SetFont('courier', '', 9);

        // =============================================
        // CUERPO DEL REPORTE
        // =============================================
        foreach ($resultados as $grupo) {
            foreach ($grupo['detalles'] as $detalle) {
                if ($y > 250) {
                    $pdf->AddPage();
                    $y = 20;
                    // Reimprimir cabecera
                    $pdf->SetXY(15, $y);
                    $pdf->SetFont('courier', 'U', 10);
                    $pdf->Cell(20, 5, 'Cuenta', 0, 0, 'L');
                    $pdf->Cell(70, 5, 'Descripcion', 0, 0, 'L');
                    $pdf->Cell(35, 5, 'Resultados', 0, 0, 'R');
                    $pdf->Cell(10, 5, 'D/H', 0, 0, 'C');
                    $pdf->Cell(35, 5, 'No Deducible', 0, 0, 'R');
                    $pdf->Cell(10, 5, 'D/H', 0, 0, 'C');
                    $y += 6;
                }

                // Fila de detalle
                $pdf->SetXY(16, $y);
                $pdf->SetFont('courier', '', 9);
                $pdf->Cell(16, 4, $detalle['cuenta'], 0, 0, 'L');
                $pdf->Cell(50, 4, $detalle['descripcion'], 0, 0, 'L');
                
                // Deducible
                $dHded = $detalle['saldo_deducible'] < 0 ? 'D' : ($detalle['saldo_deducible'] > 0 ? 'H' : '');
                $pdf->Cell(35, 4, number_format(abs($detalle['saldo_deducible']), 2, '.', ''), 0, 0, 'R');
                $pdf->Cell(10, 4, $dHded, 0, 0, 'C');
                
                // No Deducible
                $dHnod = $detalle['saldo_no_deducible'] < 0 ? 'D' : ($detalle['saldo_no_deducible'] > 0 ? 'H' : '');
                $pdf->Cell(35, 4, number_format(abs($detalle['saldo_no_deducible']), 2, '.', ''), 0, 0, 'R');
                $pdf->Cell(10, 4, $dHnod, 0, 0, 'C');
                $y += 4;
            }

            // Total del grupo
            if ($grupo['total_deducible'] != 0 || $grupo['total_no_deducible'] != 0) {
                $y += 2;
                
                // Línea separadora
                $pdf->SetXY(30, $y);
                $pdf->Cell(100, 2, '----------------------------------------------------------------------', 0, 1, 'L');
                $y += 2;
                
                // Total
                $pdf->SetXY(40, $y);
                $pdf->SetFont('courier', 'B', 9);
                $pdf->Cell(10, 4, 'Total', 0, 0, 'L');
                $pdf->Cell(40, 4, $grupo['descripcion'], 0, 0, 'L');
                
                $dHded = $grupo['total_deducible'] < 0 ? 'D' : ($grupo['total_deducible'] > 0 ? 'H' : '');
                $pdf->Cell(35, 4, number_format(abs($grupo['total_deducible']), 2, '.', ''), 0, 0, 'R');
                $pdf->Cell(10, 4, $dHded, 0, 0, 'C');
                
                $dHnod = $grupo['total_no_deducible'] < 0 ? 'D' : ($grupo['total_no_deducible'] > 0 ? 'H' : '');
                $pdf->Cell(35, 4, number_format(abs($grupo['total_no_deducible']), 2, '.', ''), 0, 0, 'R');
                $pdf->Cell(10, 4, $dHnod, 0, 0, 'C');
                $y += 4;
                
                // Línea separadora
                $pdf->SetXY(30, $y);
                $pdf->Cell(100, 2, '----------------------------------------------------------------------', 0, 1, 'L');
                $y += 3;
            }
        }

        // =============================================
        // TOTAL GENERAL
        // =============================================
        $y += 4;
        $pdf->SetXY(60, $y);
        $pdf->SetFont('courier', 'B', 10);
        $pdf->Cell(20, 5, 'TOTAL RESULTADO', 0, 0, 'L');
        
        $dHtotal = $totalDeducible < 0 ? 'D' : ($totalDeducible > 0 ? 'H' : '');
        $pdf->Cell(50, 5, number_format(abs($totalDeducible), 2, '.', ''), 0, 0, 'R');
        $pdf->Cell(10, 5, $dHtotal, 0, 0, 'C');
        $pdf->Cell(35, 5, number_format(abs($totalNoDeducible), 2, '.', ''), 0, 0, 'R');
        $pdf->Cell(10, 5, '', 0, 0, 'C');

        // Líneas separadoras
        $y += 4;
        $pdf->SetXY(30, $y);
        $pdf->Cell(100, 2, '----------------------------------------------------------------------', 0, 1, 'L');
        $y += 2;
        $pdf->SetXY(30, $y);
        $pdf->Cell(100, 2, '----------------------------------------------------------------------', 0, 1, 'L');

        // =============================================
        // RESUMEN PARA IUE
        // =============================================
        $y += 10;
        $totalResultadoResumen = $totalDeducible + abs($totalNoDeducible);

        if ($totalResultadoResumen > 0) {
            $pdf->SetXY(55, $y);
            $pdf->SetFont('courier', 'B', 10);
            $pdf->Cell(20, 5, 'RESUMEN PARA EL I.U.E.', 0, 1, 'L');
            
            $y += 4;
            $pdf->SetXY(55, $y);
            $pdf->Cell(50, 2, '-------------------', 0, 1, 'L');
            
            $y += 6;
            $pdf->SetXY(30, $y);
            $pdf->SetFont('courier', '', 9);
            $pdf->Cell(35, 4, 'Total : Resultados', 0, 0, 'L');
            $pdf->Cell(90, 4, number_format($totalDeducible, 2, '.', ''), 0, 0, 'R');
            
            $y += 5;
            $pdf->SetXY(30, $y);
            $pdf->Cell(35, 4, 'Mas  : Gasto no deducible', 0, 0, 'L');
            $pdf->Cell(90, 4, number_format(($totalNoDeducible * -1), 2, '.', ''), 0, 0, 'R');
            
            $y += 3;
            $pdf->SetXY(115, $y);
            $pdf->Cell(30, 2, '------------', 0, 1, 'L');
            
            $y += 5;
            $pdf->SetXY(30, $y);
            $pdf->Cell(35, 4, 'Igual : Resultado Impuestos', 0, 0, 'L');
            $montoParaIUE = ($totalNoDeducible * -1) + $totalDeducible;
            $pdf->Cell(90, 4, number_format($montoParaIUE, 2, '.', ''), 0, 0, 'R');
            
            $y += 3;
            $pdf->SetXY(115, $y);
            $pdf->Cell(30, 2, '------------', 0, 1, 'L');
            
            $y += 5;
            $pdf->SetXY(30, $y);
            $pdf->Cell(35, 4, 'I.U.E. 25%', 0, 0, 'L');
            $iueGestion = $montoParaIUE * 0.25;
            $pdf->Cell(90, 4, number_format($iueGestion, 2, '.', ''), 0, 0, 'R');
            
            $y += 5;
            $pdf->SetXY(115, $y);
            $pdf->Cell(30, 2, '============', 0, 1, 'L');
        } else {
            $pdf->SetXY(55, $y);
            $pdf->SetFont('courier', 'B', 12);
            $pdf->Cell(30, 5, 'PERDIDA - NO PAGA I.U.E.', 0, 1, 'C');
        }

        // =============================================
        // PIE DE PÁGINA
        // =============================================
        $pdf->SetXY(15, 270);
        $pdf->SetFont('courier', '', 7);
        $pdf->Cell(170, 3, 'Documento generado el ' . date('d/m/Y H:i:s'), 0, 0, 'C');

        $pdf->Output("estado_resultados_a3.pdf", 'I');
        exit;
    }
}