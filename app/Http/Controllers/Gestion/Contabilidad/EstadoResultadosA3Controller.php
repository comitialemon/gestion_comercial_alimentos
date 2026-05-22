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

        // Calcular período (desde 01/04 del año o año anterior)
        $fechaObj = new \DateTime($fecha);
        $mes = (int)$fechaObj->format('m');
        $anio = (int)$fechaObj->format('Y');
        
        if ($mes >= 4) {
            $anioInicio = $anio;
        } else {
            $anioInicio = $anio - 1;
        }
        
        $fechaInicio = $anioInicio . '-04-01';
        $fechaFin = $fecha;

        // Cuentas principales de resultados (TipoDeCuenta = 'P', formato XXX.X)
        $cuentasPrincipales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCliente', $clienteId)
            ->where('TipoDeCuenta', 'P')
            ->whereRaw("Cuenta LIKE '___._'")
            ->orderBy('Cuenta')
            ->get();

        $resultados = [];
        $totalDeducibleGeneral = 0;
        $totalNoDeducibleGeneral = 0;

        foreach ($cuentasPrincipales as $principal) {
            // Buscar subcuentas
            $subcuentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_cuenta')
                ->where('IdCliente', $clienteId)
                ->where('Cuenta', 'like', substr($principal->Cuenta, 0, 5) . '__')
                ->orderBy('Cuenta')
                ->get();

            $totalGrupoDeducible = 0;
            $totalGrupoNoDeducible = 0;
            $detalles = [];

            foreach ($subcuentas as $subcuenta) {
                // Calcular saldo DEDUCIBLE
                $saldoDeducible = $this->calcularSaldoCuenta($subcuenta->IdCuenta, $sucursalId, $fechaInicio, $fechaFin, $clienteId, null);
                if (floatval($subcuenta->Cuenta) >= 40000) {
                    $saldoDeducible = $saldoDeducible * -1;
                }

                // Calcular saldo NO DEDUCIBLE
                $saldoNoDeducible = $this->calcularSaldoCuenta($subcuenta->IdCuenta, $sucursalId, $fechaInicio, $fechaFin, $clienteId, 'N');
                if (floatval($subcuenta->Cuenta) >= 40000) {
                    $saldoNoDeducible = $saldoNoDeducible * -1;
                }

                if ($saldoDeducible != 0 || $saldoNoDeducible != 0) {
                    $detalles[] = [
                        'cuenta' => $subcuenta->Cuenta,
                        'descripcion' => $subcuenta->Descripcion,
                        'saldo_deducible' => abs($saldoDeducible),
                        'd_h_deducible' => $saldoDeducible < 0 ? 'D' : ($saldoDeducible > 0 ? 'H' : ''),
                        'saldo_no_deducible' => abs($saldoNoDeducible),
                        'd_h_no_deducible' => $saldoNoDeducible < 0 ? 'D' : ($saldoNoDeducible > 0 ? 'H' : ''),
                    ];
                    $totalGrupoDeducible += $saldoDeducible;
                    $totalGrupoNoDeducible += $saldoNoDeducible;
                }
            }

            if ($totalGrupoDeducible != 0 || $totalGrupoNoDeducible != 0 || count($detalles) > 0) {
                $resultados[] = [
                    'cuenta_principal' => $principal->Cuenta,
                    'descripcion' => $principal->Descripcion,
                    'total_deducible' => abs($totalGrupoDeducible),
                    'd_h_deducible' => $totalGrupoDeducible < 0 ? 'D' : ($totalGrupoDeducible > 0 ? 'H' : ''),
                    'total_no_deducible' => abs($totalGrupoNoDeducible),
                    'd_h_no_deducible' => $totalGrupoNoDeducible < 0 ? 'D' : ($totalGrupoNoDeducible > 0 ? 'H' : ''),
                    'detalles' => $detalles,
                ];
                $totalDeducibleGeneral += $totalGrupoDeducible;
                $totalNoDeducibleGeneral += $totalGrupoNoDeducible;
            }
        }

        // Totales generales
        $totalResultado = $totalDeducibleGeneral - $totalNoDeducibleGeneral;
        $totalResultadoFinal = abs($totalResultado);
        $d_h_total = $totalResultado > 0 ? 'H' : ($totalResultado < 0 ? 'D' : '');
        
        // Cálculo IUE (25% solo si hay ganancia)
        $iue = 0;
        if ($totalResultado > 0) {
            $iue = $totalResultado * 0.25;
        }

        // Generar PDF con TCPDF
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 60);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 9);

        // Encabezado
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

        // Cabecera de tabla
        $y = 32;
        $pdf->SetXY(15, $y);
        $pdf->SetFont('courier', 'U', 8);
        $pdf->Cell(20, 5, 'Cuenta', 0, 0, 'L');
        $pdf->Cell(50, 5, 'Descripcion', 0, 0, 'L');
        $pdf->Cell(30, 5, 'Deducible (Bs)', 0, 0, 'R');
        $pdf->Cell(10, 5, 'D/H', 0, 0, 'C');
        $pdf->Cell(30, 5, 'No Deducible (Bs)', 0, 0, 'R');
        $pdf->Cell(10, 5, 'D/H', 0, 0, 'C');

        $y = 38;
        $pdf->SetXY(15, $y);
        $pdf->Cell(170, 2, '---------------------------------------------------------------------------------------------------------------------------------', 0, 1, 'L');

        $y = 42;
        
        foreach ($resultados as $grupo) {
            if ($y > 250) {
                $pdf->AddPage();
                $y = 20;
            }

            // Subcuentas
            foreach ($grupo['detalles'] as $detalle) {
                $pdf->SetXY(20, $y);
                $pdf->SetFont('courier', '', 8);
                $pdf->Cell(15, 4, $detalle['cuenta'], 0, 0, 'L');
                $pdf->Cell(45, 4, $detalle['descripcion'], 0, 0, 'L');
                $pdf->Cell(30, 4, number_format($detalle['saldo_deducible'], 2, ',', '.'), 0, 0, 'R');
                $pdf->Cell(10, 4, $detalle['d_h_deducible'], 0, 0, 'C');
                $pdf->Cell(30, 4, number_format($detalle['saldo_no_deducible'], 2, ',', '.'), 0, 0, 'R');
                $pdf->Cell(10, 4, $detalle['d_h_no_deducible'], 0, 0, 'C');
                $y += 4;
            }

            // Total del grupo
            if ($grupo['total_deducible'] != 0 || $grupo['total_no_deducible'] != 0) {
                $y += 2;
                $pdf->SetXY(35, $y);
                $pdf->SetFont('courier', 'B', 8);
                $pdf->Cell(30, 4, 'Total', 0, 0, 'L');
                $pdf->Cell(30, 4, $grupo['descripcion'], 0, 0, 'L');
                $pdf->Cell(30, 4, number_format($grupo['total_deducible'], 2, ',', '.'), 0, 0, 'R');
                $pdf->Cell(10, 4, $grupo['d_h_deducible'], 0, 0, 'C');
                $pdf->Cell(30, 4, number_format($grupo['total_no_deducible'], 2, ',', '.'), 0, 0, 'R');
                $pdf->Cell(10, 4, $grupo['d_h_no_deducible'], 0, 0, 'C');
                $y += 4;
                
                $pdf->SetXY(35, $y);
                $pdf->Cell(30, 2, '------------------------------------------------------------', 0, 1, 'L');
                $y += 3;
            }
        }

        // Totales generales
        $y += 4;
        $pdf->SetXY(65, $y);
        $pdf->SetFont('courier', 'B', 9);
        $pdf->Cell(20, 5, 'TOTAL RESULTADO', 0, 0, 'L');
        $pdf->Cell(30, 5, number_format($totalDeducibleGeneral, 2, ',', '.'), 0, 0, 'R');
        $pdf->Cell(10, 5, $d_h_total, 0, 0, 'C');
        $pdf->Cell(30, 5, number_format($totalNoDeducibleGeneral, 2, ',', '.'), 0, 0, 'R');
        $pdf->Cell(10, 5, '', 0, 0, 'C');

        // Sección IUE (si hay ganancia)
        if ($totalResultado > 0) {
            $y += 8;
            $pdf->SetXY(35, $y);
            $pdf->SetFont('courier', 'B', 8);
            $pdf->Cell(30, 4, 'RESUMEN PARA EL I.U.E.', 0, 1, 'L');
            
            $y += 5;
            $pdf->SetXY(35, $y);
            $pdf->SetFont('courier', '', 8);
            $pdf->Cell(40, 4, 'Total Resultados', 0, 0, 'L');
            $pdf->Cell(80, 4, number_format($totalDeducibleGeneral, 2, ',', '.'), 0, 0, 'R');
            
            $y += 5;
            $pdf->SetXY(35, $y);
            $pdf->Cell(40, 4, 'Mas: Gastos no deducibles', 0, 0, 'L');
            $pdf->Cell(80, 4, number_format($totalNoDeducibleGeneral, 2, ',', '.'), 0, 0, 'R');
            
            $y += 5;
            $pdf->SetXY(35, $y);
            $pdf->Cell(40, 4, 'Igual: Resultado Impuestos', 0, 0, 'L');
            $pdf->Cell(80, 4, number_format($totalResultado, 2, ',', '.'), 0, 0, 'R');
            
            $y += 5;
            $pdf->SetXY(35, $y);
            $pdf->Cell(40, 4, 'I.U.E. 25%', 0, 0, 'L');
            $pdf->Cell(80, 4, number_format($iue, 2, ',', '.'), 0, 0, 'R');
        } else {
            $y += 10;
            $pdf->SetXY(45, $y);
            $pdf->SetFont('courier', 'B', 9);
            $pdf->Cell(30, 5, 'PERDIDA - NO PAGA I.U.E.', 0, 1, 'C');
        }

        // Pie de página
        $pdf->SetXY(15, 270);
        $pdf->SetFont('courier', '', 7);
        $pdf->Cell(170, 3, 'Documento generado el ' . date('d/m/Y H:i:s'), 0, 0, 'C');

        $pdf->Output("estado_resultados_a3.pdf", 'I');
        exit;
    }

    private function calcularSaldoCuenta($idCuenta, $sucursalId, $fechaInicio, $fechaFin, $clienteId, $deducible = null)
    {
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario as d')
            ->join('conta_diario_propiamente as dp', 'd.IdDiario', '=', 'dp.IdDiario')
            ->join('todos_fecha as f', 'd.IdFecha', '=', 'f.IdFecha')
            ->where('d.IdCliente', $clienteId)
            ->where('d.IdSucursal', $sucursalId)
            ->where('f.Fecha', '>=', $fechaInicio)
            ->where('f.Fecha', '<=', $fechaFin)
            ->where('dp.IdCuenta', $idCuenta);

        if ($deducible !== null) {
            $query->where('dp.Deducible', $deducible);
        }

        $totalDebe = (clone $query)->where('dp.D_H', 'D')->sum('dp.MontoBolivianos');
        $totalHaber = (clone $query)->where('dp.D_H', 'H')->sum('dp.MontoBolivianos');

        return ($totalDebe ?? 0) - ($totalHaber ?? 0);
    }
}