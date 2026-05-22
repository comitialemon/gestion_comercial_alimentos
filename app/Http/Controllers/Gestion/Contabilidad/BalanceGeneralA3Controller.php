<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class BalanceGeneralA3Controller extends Controller
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

        return Inertia::render('Gestion/Contabilidad/BalanceGeneralA3/Index', [
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
        $nombreEmpresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->value('Nombre');

        $nombreSucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->value('Nombre');

        $fechaTituloInforme = 'Al (' . date('d/m/Y', strtotime($fecha)) . ')';

        $pdf = new \TCPDF();
        $pdf->SetMargins(14, 20, 60);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 10);

        // Sucursal
        $pdf->Setxy(20, 12);
        $pdf->Cell(32, 6, $nombreSucursal, 0, 0, 'L');

        // Fecha
        $pdf->Setxy(85, 24);
        $pdf->Cell(32, 6, $fechaTituloInforme, 0, 0, 'C');

        // Logo / espacio
        $pdf->SetFont('Courier', 'B', 6);
        $pdf->SetXY(80, 7);
        $pdf->Cell(30, 5, '', 0, 1, 'C');
        $pdf->SetFont('Courier', 'B', 10);

        // Nombre Empresa
        $pdf->SetXY(20, 7);
        $pdf->Cell(10, 6, $nombreEmpresa, 0, 1, 'L');

        // Título
        $pdf->SetFont('Courier', 'U', 16);
        $pdf->SetXY(90, 18);
        $pdf->Cell(15, 6, 'Balance General A3', 0, 1, 'C');
        $pdf->SetFont('Courier', '', 10);

        $pdf->Setxy(85, 28);

        // Cuentas principales (3 dígitos)
        $cuentasPrincipales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta')
            ->where('IdCliente', $clienteId)
            ->where('TipoDeCuenta', 'B')
            ->whereRaw('LENGTH(Cuenta) = 3')
            ->orderBy('Cuenta')
            ->get();

        $totalBalanceA3 = 0;
        $yActual = 35;

        foreach ($cuentasPrincipales as $principal) {
            // Subcuentas (XXX.___)
            $subcuentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_cuenta')
                ->where('IdCliente', $clienteId)
                ->where('Cuenta', 'like', $principal->Cuenta . '.___')
                ->orderBy('Cuenta')
                ->get();

            $totalGrupoCuenta = 0;

            foreach ($subcuentas as $subcuenta) {
                // Calcular saldo
                $totalDebe = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario as d')
                    ->join('conta_diario_propiamente as dp', 'd.IdDiario', '=', 'dp.IdDiario')
                    ->join('todos_fecha as f', 'd.IdFecha', '=', 'f.IdFecha')
                    ->where('d.IdCliente', $clienteId)
                    ->where('d.IdSucursal', $sucursalId)
                    ->where('f.Fecha', '<=', $fecha)
                    ->where('dp.IdCuenta', $subcuenta->IdCuenta)
                    ->where('dp.D_H', 'D')
                    ->sum('dp.MontoBolivianos');

                $totalHaber = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario as d')
                    ->join('conta_diario_propiamente as dp', 'd.IdDiario', '=', 'dp.IdDiario')
                    ->join('todos_fecha as f', 'd.IdFecha', '=', 'f.IdFecha')
                    ->where('d.IdCliente', $clienteId)
                    ->where('d.IdSucursal', $sucursalId)
                    ->where('f.Fecha', '<=', $fecha)
                    ->where('dp.IdCuenta', $subcuenta->IdCuenta)
                    ->where('dp.D_H', 'H')
                    ->sum('dp.MontoBolivianos');

                $totalCuenta = ($totalDebe ?? 0) - ($totalHaber ?? 0);
                $totalGrupoCuenta += $totalCuenta;

                if ($totalCuenta != 0) {
                    // Verificar espacio en página
                    if ($yActual > 260) {
                        $pdf->AddPage();
                        $yActual = 30;
                        $pdf->Setxy(20, $yActual);
                        $pdf->Cell(32, 6, $nombreSucursal, 0, 0, 'L');
                        $pdf->Setxy(85, $yActual + 12);
                        $pdf->Cell(32, 6, $fechaTituloInforme, 0, 0, 'C');
                        $pdf->Setxy(20, $yActual - 5);
                        $pdf->Cell(10, 6, $nombreEmpresa, 0, 1, 'L');
                        $pdf->Setxy(90, $yActual + 6);
                        $pdf->Cell(15, 6, 'Balance General A3', 0, 1, 'C');
                        $yActual = $yActual + 20;
                    }

                    $pdf->Setxy(20, $yActual);
                    $pdf->Cell(19, 6, $subcuenta->Cuenta, 0, 0, 'L');
                    $pdf->Cell(39, 6, $subcuenta->Descripcion, 0, 0, 'L');
                    $pdf->Cell(90, 6, number_format(abs($totalCuenta), 2, '.', ','), 0, 0, 'R');

                    $d_h = $totalCuenta > 0 ? 'D' : 'H';
                    $pdf->Cell(95, 6, $d_h, 0, 0, 'L');

                    $yActual += 7;
                }
            }

            $totalBalanceA3 += $totalGrupoCuenta;

            if ($totalGrupoCuenta != 0) {
                // Línea separadora antes del total
                $pdf->Setxy(40, $yActual);
                $pdf->Cell(30, 3, '------------------------------------------------------------', 0, 0, 'L');
                $yActual += 5;

                // Total del grupo
                $pdf->Setxy(40, $yActual);
                $pdf->Cell(20, 6, 'Total', 0, 0, 'L');
                $pdf->Cell(2, 6, '', 0, 0, 'L');
                $pdf->Cell(40, 6, $principal->Descripcion, 0, 0, 'L');
                $pdf->Cell(66, 6, number_format(abs($totalGrupoCuenta), 2, '.', ','), 0, 0, 'R');

                $d_h_grupo = $totalGrupoCuenta > 0 ? 'D' : 'H';
                $pdf->Cell(95, 6, $d_h_grupo, 0, 0, 'L');

                $yActual += 7;

                // Línea separadora después del total
                $pdf->Setxy(40, $yActual);
                $pdf->Cell(30, 3, '------------------------------------------------------------', 0, 0, 'L');
                $yActual += 6;
            }
        }

        // Total Balance A3
        $yActual += 3;
        $pdf->Setxy(60, $yActual);
        $pdf->Cell(20, 7, ' TOTAL BALANCE ', 0, 0, 'L');
        $pdf->Cell(88, 7, number_format(abs($totalBalanceA3), 2, '.', ','), 0, 0, 'R');

        $d_h_total = $totalBalanceA3 > 0 ? 'D' : 'H';
        $pdf->Cell(95, 7, $d_h_total, 0, 0, 'L');

        $yActual += 8;
        $pdf->Setxy(40, $yActual);
        $pdf->Cell(30, 3, '------------------------------------------------------------', 0, 0, 'L');
        $yActual += 4;
        $pdf->Setxy(40, $yActual);
        $pdf->Cell(30, 3, '------------------------------------------------------------', 0, 0, 'L');

        $pdf->Output('balance_general_a3.pdf', 'I');
        exit;
    }
}