<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\Diario;
use App\Models\Gestion\Contabilidad\DiarioPropiamente;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Models\Gestion\Contabilidad\FactorCambio;
use App\Models\Gestion\Contabilidad\Moneda;
use App\Models\Gestion\Contabilidad\TipoDiario;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\ClienteActividad;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Fecha;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiarioIngresoController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        // Buscar diario pendiente para este operador
        $diarioPendiente = Diario::porContexto()
            ->pendientes()
            ->noCerrados()
            ->first();

        if ($diarioPendiente) {
            return redirect()->route('contabilidad.diario-ingreso.edit', $diarioPendiente->IdDiario)
                ->with('info', 'Tienes un diario en proceso. Continúa editándolo.');
        }

        $fechas = $this->getFechasDisponibles();
        $tiposDiario = TipoDiario::whereNotIn('IdTipoDiario', [6, 7, 11, 12, 15, 17, 19])
            ->orderBy('TipoDiario')
            ->get();
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        return Inertia::render('Gestion/Contabilidad/DiarioIngreso/Create', [
            'fechas' => $fechas,
            'tiposDiario' => $tiposDiario,
            'sucursales' => $sucursales,
        ]);
    }

    public function edit($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $diario = Diario::porContexto()
            ->with(['asientos.cuenta', 'asientos.identificador', 'asientos.actividad'])
            ->findOrFail($id);

        // 🔥 OBTENER LA ACTIVIDAD DEL CLIENTE
        $actividad = ClienteActividad::where('IdCliente', $clienteId)->first();

        $fechas = $this->getFechasDisponibles();
        $tiposDiario = TipoDiario::whereNotIn('IdTipoDiario', [6, 7, 11, 12, 15, 17, 19])
            ->orderBy('TipoDiario')
            ->get();
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        $cuentas = ContaCuenta::where('AbiertoCerrado', 0)
            ->where('IdCliente', $clienteId)
            ->orderBy('Cuenta')
            ->get(['IdCuenta as id', 'Cuenta', 'Descripcion', 'TipoDeCuenta', 'IdMoneda', 'ActivoFijo']);

        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        return Inertia::render('Gestion/Contabilidad/DiarioIngreso/Create', [
            'diario' => $diario,
            'asientos' => $diario->asientos,
            'fechas' => $fechas,
            'tiposDiario' => $tiposDiario,
            'sucursales' => $sucursales,
            'cuentas' => $cuentas,
            'identificadores' => $identificadores,
            'actividadCliente' => $actividad ? $actividad->Actividad : 'SIN ACTIVIDAD', // 🔥 PASAR ACTIVIDAD
            'editando' => true,
        ]);
    }

    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $request->validate([
            'IdFecha' => 'required|exists:todos_fecha,IdFecha',
            'IdTipoDiario' => 'required|exists:conta_tipodiario,IdTipoDiario',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);

        try {
            $diario = Diario::create([
                'IdFecha' => $request->IdFecha,
                'IdTipoDiario' => $request->IdTipoDiario,
                'NumeroDiario' => 0,
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
                'Contabilizado' => 0,
                'IdOperadorIngreso' => $operadorId,
                'FechaIngreso' => now(),
                'IdOperadorEdita' => $operadorId,
                'FechaEdita' => now(),
            ]);

            return redirect()->route('contabilidad.diario-ingreso.edit', $diario->IdDiario)
                ->with('success', 'Diario creado. Agrega los asientos contables.');

        } catch (\Exception $e) {
            Log::error('Error al crear diario: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al crear: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $request->validate([
            'IdFecha' => 'required|exists:todos_fecha,IdFecha',
            'IdTipoDiario' => 'required|exists:conta_tipodiario,IdTipoDiario',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);

        try {
            $diario = Diario::porContexto()->findOrFail($id);
            $diario->update([
                'IdFecha' => $request->IdFecha,
                'IdTipoDiario' => $request->IdTipoDiario,
                'IdSucursal' => $request->IdSucursal,
                'IdOperadorEdita' => $operadorId,
                'FechaEdita' => now(),
            ]);

            return redirect()->back()->with('success', 'Cabecera actualizada correctamente');

        } catch (\Exception $e) {
            Log::error('Error al actualizar diario: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }
    public function storeAsiento(Request $request)
    {
        $request->validate([
            'IdDiario' => 'required|exists:conta_diario,IdDiario',
            'IdCuenta' => 'required|exists:conta_cuenta,IdCuenta',
            'D_H' => 'required|in:D,H',
            'MontoBolivianos' => 'required|numeric|min:0',
            'Glosa' => 'required|string|max:400',
            'IdIdentificador' => 'nullable|exists:todos_identificador,IdIdentificador',
            'Deducible' => 'nullable|in:D,N',
        ]);

        $diario = Diario::porContexto()->findOrFail($request->IdDiario);

        if ($diario->Contabilizado == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Este diario ya está contabilizado y no puede ser modificado'
            ], 422);
        }

        try {
            // Obtener la actividad del cliente actual
            $clienteId = session('cliente_id');
            $actividad = ClienteActividad::where('IdCliente', $clienteId)->first();
            $actividadId = $actividad ? $actividad->IdActividad : null;

            // Obtener moneda de la cuenta y factor de cambio
            $cuenta = ContaCuenta::find($request->IdCuenta);
            $factorCambio = 1;
            $montoOtraMoneda = 0;

            if ($cuenta && $cuenta->IdMoneda) {
                $factor = FactorCambio::where('IdFecha', $diario->IdFecha)
                    ->where('IdMoneda', $cuenta->IdMoneda)
                    ->first();
                $factorCambio = $factor ? $factor->FactorCambio : 1;

                if (in_array($cuenta->IdMoneda, [2, 3])) {
                    $montoOtraMoneda = $request->MontoBolivianos / $factorCambio;
                } else {
                    $montoOtraMoneda = $request->MontoBolivianos;
                }
            }

            $asiento = DiarioPropiamente::create([
                'IdDiario' => $request->IdDiario,
                'IdCuenta' => $request->IdCuenta,
                'Glosa' => $request->Glosa,
                'D_H' => $request->D_H,
                'MontoBolivianos' => $request->MontoBolivianos,
                'TipoCambio' => $factorCambio,
                'MontoOtraMoneda' => $montoOtraMoneda,
                'IdIdentificador' => $request->IdIdentificador,
                'IdActividad' => $actividadId,
                'Deducible' => $request->Deducible,
            ]);

            $asiento->load(['cuenta', 'identificador', 'actividad']);

            return response()->json([
                'success' => true,
                'message' => 'Asiento agregado correctamente',
                'asiento' => $asiento
            ]);

        } catch (\Exception $e) {
            Log::error('Error al agregar asiento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar: ' . $e->getMessage()
            ], 500);
        }
    }
    public function updateAsiento(Request $request, $id)
    {
        $request->validate([
            'MontoBolivianos' => 'required|numeric|min:0',
            'Glosa' => 'required|string|max:400',
            'IdIdentificador' => 'nullable|exists:todos_identificador,IdIdentificador',
            'Deducible' => 'nullable|in:D,N',
        ]);

        $asiento = DiarioPropiamente::findOrFail($id);
        $diario = Diario::porContexto()->findOrFail($asiento->IdDiario);

        if ($diario->Contabilizado == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Este diario ya está contabilizado y no puede ser modificado'
            ], 422);
        }

        try {
            // Obtener la actividad del cliente actual
            $clienteId = session('cliente_id');
            $actividad = ClienteActividad::where('IdCliente', $clienteId)->first();
            $actividadId = $actividad ? $actividad->IdActividad : $asiento->IdActividad;

            // Recalcular tipo de cambio y monto otra moneda si cambia el monto
            $factorCambio = $asiento->TipoCambio;
            $montoOtraMoneda = $asiento->MontoOtraMoneda;

            if ($request->MontoBolivianos != $asiento->MontoBolivianos) {
                $cuenta = ContaCuenta::find($asiento->IdCuenta);
                if ($cuenta && in_array($cuenta->IdMoneda, [2, 3])) {
                    $montoOtraMoneda = $request->MontoBolivianos / $factorCambio;
                } else {
                    $montoOtraMoneda = $request->MontoBolivianos;
                }
            }

            $asiento->update([
                'MontoBolivianos' => $request->MontoBolivianos,
                'MontoOtraMoneda' => $montoOtraMoneda,
                'Glosa' => $request->Glosa,
                'IdIdentificador' => $request->IdIdentificador,
                'IdActividad' => $actividadId,
                'Deducible' => $request->Deducible,
            ]);

            $asiento->load(['cuenta', 'identificador', 'actividad']);

            return response()->json([
                'success' => true,
                'message' => 'Asiento actualizado correctamente',
                'asiento' => $asiento
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar asiento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroyAsiento($id)
    {
        $asiento = DiarioPropiamente::findOrFail($id);
        $diario = Diario::porContexto()->findOrFail($asiento->IdDiario);

        if ($diario->Contabilizado == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Este diario ya está contabilizado y no puede ser modificado'
            ], 422);
        }

        try {
            $asiento->delete();

            return response()->json([
                'success' => true,
                'message' => 'Asiento eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function contabilizar($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $diario = Diario::porContexto()
            ->with('asientos')
            ->findOrFail($id);

        if ($diario->Contabilizado == 1) {
            return redirect()->back()->with('error', 'Este diario ya está contabilizado');
        }

        // Calcular totales
        $totalDebe = $diario->asientos->where('D_H', 'D')->sum('MontoBolivianos');
        $totalHaber = $diario->asientos->where('D_H', 'H')->sum('MontoBolivianos');

        if ($totalDebe != $totalHaber) {
            return redirect()->back()->with('error', "El diario no cuadra. Total Debe: " . number_format($totalDebe, 2) . " Bs | Total Haber: " . number_format($totalHaber, 2) . " Bs");
        }

        if ($diario->asientos->count() == 0) {
            return redirect()->back()->with('error', 'No hay asientos para contabilizar');
        }

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // Generar número de diario
            $maxNumero = Diario::where('IdCliente', $clienteId)
                ->where('IdSucursal', $diario->IdSucursal)
                ->max('NumeroDiario');
            $numeroDiario = ($maxNumero ?? 0) + 1;

            $diario->update([
                'NumeroDiario' => $numeroDiario,
                'Contabilizado' => 1,
                'IdOperadorEdita' => $operadorId,
                'FechaEdita' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return redirect()->route('contabilidad.diario-ingreso.pdf', $diario->IdDiario)
                ->with('success', "Diario contabilizado correctamente. N° {$numeroDiario}");

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al contabilizar diario: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al contabilizar: ' . $e->getMessage());
        }
    }

    public function pdf($id)
    {
        $diario = Diario::porContexto()
            ->with(['asientos.cuenta', 'asientos.identificador', 'fecha', 'sucursal', 'tipoDiario'])
            ->findOrFail($id);

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first();

        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $diario->IdOperadorIngreso)
            ->first();

        $totalDebe = $diario->asientos->where('D_H', 'D')->sum('MontoBolivianos');
        $totalHaber = $diario->asientos->where('D_H', 'H')->sum('MontoBolivianos');

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 60);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 8);

        // Encabezado
        $pdf->SetXY(6, 5);
        $pdf->Cell(10, 3, $empresa->Nombre ?? '', 0, 1, 'L');

        $pdf->SetXY(6, 8);
        $pdf->Cell(10, 3, $diario->sucursal->Nombre ?? '', 0, 1, 'L');

        $pdf->SetXY(195, 5);
        $pdf->Cell(10, 3, 'Pg. ' . $pdf->PageNo(), 0, 0, 'L');

        $pdf->SetXY(6, 11);
        $pdf->Cell(18, 3, 'NUMERO DIARIO:', 0, 1, 'L');
        $pdf->SetXY(30, 11);
        $pdf->Cell(15, 3, $diario->NumeroDiario, 0, 1, 'L');

        $pdf->SetXY(6, 15);
        $pdf->Cell(15, 3, 'FECHA DIARIO:', 0, 1, 'L');
        $pdf->SetXY(30, 15);
        $pdf->Cell(15, 3, date('d/m/Y', strtotime($diario->fecha->Fecha ?? '')), 0, 1, 'L');

        $pdf->SetXY(170, 11);
        $pdf->Cell(15, 3, 'TIPO DIARIO:', 0, 1, 'C');
        $pdf->SetXY(190, 11);
        $pdf->Cell(15, 3, $diario->tipoDiario->TipoDiario ?? '', 0, 1, 'C');

        $pdf->SetXY(80, 11);
        $pdf->Cell(15, 3, 'Origen Diario:', 0, 1, 'C');
        $pdf->SetXY(100, 11);
        $pdf->Cell(15, 3, $operador->Nombre ?? '', 0, 1, 'C');

        // Línea separadora
        $pdf->SetXY(5, 17);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------------------------------------------------------------------------------------------', 0, 1, 'L');

        $pdf->SetXY(8, 20);
        $pdf->Cell(15, 3, 'Cuenta', 0, 1, 'L');
        $pdf->SetXY(10, 22);
        $pdf->Cell(15, 3, 'Glosa', 0, 1, 'L');
        $pdf->SetXY(105, 22);
        $pdf->Cell(15, 3, 'Tipo Cambio', 0, 1, 'L');
        $pdf->SetXY(160, 22);
        $pdf->Cell(15, 3, 'Debe', 0, 1, 'L');
        $pdf->SetXY(190, 22);
        $pdf->Cell(15, 3, 'Haber', 0, 1, 'L');
        $pdf->SetXY(12, 24);
        $pdf->Cell(15, 3, 'Identificador', 0, 1, 'L');

        $pdf->SetXY(5, 26);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------------------------------------------------------------------------------------------', 0, 1, 'L');

        $y = 28;
        foreach ($diario->asientos as $asiento) {
            if ($y > 250) {
                $pdf->AddPage();
                $y = 28;
            }

            $pdf->SetXY(8, $y);
            $pdf->Cell(14, 2, $asiento->cuenta->Cuenta ?? '', 0, 0, 'L');
            $pdf->Cell(14, 2, $asiento->cuenta->Descripcion ?? '', 0, 0, 'L');

            $pdf->SetXY(20, $y + 3);
            $pdf->MultiCell(80, 2, $asiento->Glosa, 0, 'L');

            $pdf->SetXY(105, $y);
            $pdf->Cell(14, 2, number_format($asiento->TipoCambio, 4, ',', '.'), 0, 0, 'R');

            if ($asiento->D_H == 'D') {
                $pdf->SetXY(160, $y);
                $pdf->Cell(14, 2, number_format($asiento->MontoBolivianos, 2, ',', '.'), 0, 0, 'R');
            } else {
                $pdf->SetXY(190, $y);
                $pdf->Cell(14, 2, number_format($asiento->MontoBolivianos, 2, ',', '.'), 0, 0, 'R');
            }

            $pdf->SetXY(20, $pdf->GetY() + 2);
            $pdf->Cell(18, 2, $asiento->identificador->CI_NIT ?? '', 0, 0, 'L');
            $pdf->Cell(78, 2, $asiento->identificador->Nombre ?? '', 0, 0, 'L');

            $y = $pdf->GetY() + 6;
        }

        // Totales
        $pdf->SetXY(112, $y + 4);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');

        $pdf->SetXY(115, $y + 6);
        $pdf->Cell(15, 3, 'TOTALES EN BOLIVIANOS ', 0, 1, 'L');
        $pdf->SetXY(128, $y + 6);
        $pdf->MultiCell(45, 3, number_format($totalDebe, 2, ',', '.'), 0, 'R');
        $pdf->SetXY(153, $y + 6);
        $pdf->MultiCell(45, 3, number_format($totalHaber, 2, ',', '.'), 0, 'R');

        $pdf->SetXY(112, $y + 8);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');
        $pdf->SetXY(112, $y + 9);
        $pdf->Cell(15, 3, '----------------------------------------------------------------------------', 0, 1, 'L');

        // Firmas
        $pdf->SetXY(40, $y + 30);
        $pdf->Cell(15, 3, $operador->Nombre ?? '', 0, 1, 'C');
        $pdf->SetXY(40, $y + 33);
        $pdf->Cell(15, 3, 'Realizado', 0, 1, 'C');

        $pdf->Output("diario_{$diario->NumeroDiario}.pdf", 'I');
        exit;
    }

    private function getFechasDisponibles()
    {
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('ActivoInactivo', 0)
            ->where('CierrePermanente', 0)
            ->select('IdFecha as id', DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y') as fecha"))
            ->orderBy('IdFecha', 'desc')
            ->get();

        $fechasAux = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha_auxiliar_sucursal')
            ->join('todos_fecha', 'todos_fecha_auxiliar_sucursal.IdFecha', '=', 'todos_fecha.IdFecha')
            ->where('todos_fecha_auxiliar_sucursal.IdCliente', session('cliente_id'))
            ->where('todos_fecha_auxiliar_sucursal.IdSucursal', session('cliente_sucursal_id'))
            ->select('todos_fecha.IdFecha as id', DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d/%m/%Y') as fecha"))
            ->orderBy('todos_fecha.IdFecha', 'desc')
            ->get();

        return $fechas->merge($fechasAux)->unique('id');
    }
    
}