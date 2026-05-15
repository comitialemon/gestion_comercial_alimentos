<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\Egreso;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Fecha;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TCPDF;

class EgresoController extends Controller
{
    public function index()
    {
        $egresos = Egreso::porContexto()
            ->with(['identificador', 'fecha'])
            ->orderBy('IdEgreso', 'desc')
            ->paginate(20);

        $egresos->getCollection()->transform(function ($egreso) {
            $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $egreso->IdDiario)
                ->value('NumeroDiario');
            
            $egreso->numero_diario = $numeroDiario ?? '-';
            
            if ($egreso->fecha) {
                $egreso->fecha_formateada = date('d/m/Y', strtotime($egreso->fecha->Fecha));
            } else {
                $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_fecha')
                    ->where('IdFecha', $egreso->IdFecha)
                    ->first();
                
                if ($fechaData) {
                    $egreso->fecha_formateada = date('d/m/Y', strtotime($fechaData->Fecha));
                } else {
                    $egreso->fecha_formateada = '-';
                }
            }
            
            return $egreso;
        });

        return Inertia::render('Gestion/Contabilidad/Egresos/Index', [
            'egresos' => $egresos,
        ]);
    }

    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $fechas = $this->getFechasDisponibles();

        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $cuentasHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'H')
            ->whereIn('cs.Cuenta', ['Egreso', 'CajaSucursal'])
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        $cuentasDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'D')
            ->whereIn('cs.Cuenta', ['Egreso', 'CajaSucursal'])
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        return Inertia::render('Gestion/Contabilidad/Egresos/Create', [
            'fechas' => $fechas,
            'identificadores' => $identificadores,
            'cuentasHaber' => $cuentasHaber,
            'cuentasDebe' => $cuentasDebe,
        ]);
    }

    public function edit($id)
    {
        $egreso = Egreso::porContexto()
            ->where('ActivoInactivo', 0)
            ->findOrFail($id);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $fechas = $this->getFechasDisponibles();

        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $cuentasHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'H')
            ->whereIn('cs.Cuenta', ['Egreso', 'CajaSucursal'])
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        $cuentasDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'D')
            ->whereIn('cs.Cuenta', ['Egreso', 'CajaSucursal'])
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        return Inertia::render('Gestion/Contabilidad/Egresos/Create', [
            'egreso' => $egreso,
            'fechas' => $fechas,
            'identificadores' => $identificadores,
            'cuentasHaber' => $cuentasHaber,
            'cuentasDebe' => $cuentasDebe,
            'editando' => true,
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'IdFecha' => 'required|exists:todos_fecha,IdFecha',
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'IdCuentaHaber' => 'required|exists:conta_cuenta,IdCuenta',
            'IdCuentaDebe' => 'required|exists:conta_cuenta,IdCuenta',
            'Glosa' => 'required|string|max:200',
            'TotalBolivianos' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            if ($request->has('IdEgreso') && $request->IdEgreso) {
                // Actualización (mismo código)
                $egreso = Egreso::porContexto()
                    ->where('ActivoInactivo', 0)
                    ->findOrFail($request->IdEgreso);

                $numeroEgreso = $egreso->NumeroEgreso;
                $idDiario = $egreso->IdDiario;

                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->where('IdDiario', $idDiario)
                    ->update(['IdFecha' => $request->IdFecha]);

                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->where('IdDiario', $idDiario)
                    ->delete();
            } else {
                // Nuevo egreso
                $egreso = new Egreso();
                
                $maxNumero = Egreso::porContexto()->max('NumeroEgreso');
                $numeroEgreso = ($maxNumero ?? 0) + 1;

                $maxNumeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->max('NumeroDiario');
                $numeroDiario = ($maxNumeroDiario ?? 0) + 1;

                $idDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->insertGetId([
                        'IdFecha' => $request->IdFecha,
                        'IdTipoDiario' => 8,
                        'NumeroDiario' => $numeroDiario,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                        'Contabilizado' => 1,
                        'IdOperadorIngreso' => $operadorId,
                        'FechaIngreso' => now(),
                        'IdoperadorEdita' => $operadorId,
                        'FechaEdita' => now(),
                    ]);

                $egreso->IdDiario = $idDiario;
                $egreso->NumeroEgreso = $numeroEgreso;
                $egreso->IdCliente = $clienteId;
                $egreso->IdSucursal = $sucursalId;
                $egreso->IdOperador = $operadorId;
            }

            // ... resto del código de contabilidad (UFV, inserciones, etc.) ...

            $egreso->IdFecha = $request->IdFecha;
            $egreso->IdCuentaDebe = $request->IdCuentaDebe;
            $egreso->IdCuentaHaber = $request->IdCuentaHaber;
            $egreso->IdIdentificador = $request->IdIdentificador;
            $egreso->Glosa = $request->Glosa;
            $egreso->TotalBolivianos = $request->TotalBolivianos;
            $egreso->ActivoInactivo = 1;
            $egreso->save();

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            // 🔥 Devolver JSON con la URL del PDF
            return response()->json([
                'success' => true,
                'pdf_url' => route('egresos.pdf', $egreso->IdEgreso),
                'message' => 'Egreso guardado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al guardar egreso: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pdf($id)
    {
        $egreso = Egreso::porContexto()
            ->with(['identificador', 'fecha', 'cuentaDebe', 'cuentaHaber'])
            ->findOrFail($id);

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 60);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 8);

        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 10, 50, 20);
        }

        $pdf->SetXY(179, 10);
        $pdf->SetFont('courier', 'B', 16);
        $pdf->Cell(18, 3, 'No ' . $egreso->NumeroEgreso, 0, 1, 'L');

        $y = $pdf->GetY();
        $pdf->SetXY(179, $y + 1);
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(18, 3, 'Diario : ' . ($egreso->IdDiario ?? '-'), 0, 1, 'L');

        $y = $pdf->GetY();
        $pdf->SetXY(5, $y + 10);
        $pdf->SetFont('courier', 'B', 20);
        $pdf->Cell(200, 5, 'RECIBO DE EGRESO', 0, 1, 'C');
        
        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 12);
        $pdf->SetXY(5, $y + 7);
        $pdf->Cell(200, 3, '(Expresado en Bolivianos)', 0, 1, 'C');

        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 10);
        
        $pdf->SetXY(15, $y + 12);
        $pdf->Cell(200, 3, 'Entregado a : ' . ($egreso->identificador->Nombre ?? 'N/D') . ' - CI: ' . ($egreso->identificador->CI_NIT ?? 'N/D'), 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 2);
        $pdf->Cell(200, 3, 'Cuenta (Haber) : ' . ($egreso->cuentaHaber->Cuenta ?? 'N/D') . ' - ' . ($egreso->cuentaHaber->Descripcion ?? ''), 0, 1, 'L');
        
        $pdf->SetXY(155, $y + 2);
        $pdf->Cell(200, 3, 'Monto : ' . number_format($egreso->TotalBolivianos, 2, ',', '.'), 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 2);
        $pdf->Cell(200, 3, 'Cuenta (Debe) : ' . ($egreso->cuentaDebe->Cuenta ?? 'N/D') . ' - ' . ($egreso->cuentaDebe->Descripcion ?? ''), 0, 1, 'L');
        
        $pdf->SetXY(155, $y + 2);
        $pdf->Cell(200, 3, 'Fecha   : ' . date('d/m/Y', strtotime($egreso->FechaIngreso)), 0, 1, 'L');

        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 9);
        $pdf->SetXY(15, $y + 4);
        $pdf->Cell(185, 4, 'GLOSA:', 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetFont('courier', '', 9);
        $pdf->SetXY(15, $y);
        $pdf->MultiCell(185, 4, $egreso->Glosa ?? '', 0, 'L');
        
        $y = $pdf->GetY();

        $y = $y + 20;
        $pdf->SetFont('courier', 'B', 10);
        
        $pdf->SetXY(25, $y);
        $pdf->Cell(60, 3, 'RECIBI CONFORME', 0, 1, 'C');
        $pdf->SetXY(120, $y);
        $pdf->Cell(60, 3, 'ENTREGUE CONFORME', 0, 1, 'C');

        $y = $y + 8;
        $pdf->SetFont('courier', '', 8);
        $pdf->SetXY(25, $y);
        $pdf->Cell(60, 3, $egreso->identificador->Nombre ?? '_________________', 0, 1, 'C');
        $pdf->SetXY(120, $y);
        
        $operadorNombre = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $egreso->IdOperador)
            ->value('i.Nombre') ?? '_________________';
        
        $pdf->Cell(60, 3, $operadorNombre, 0, 1, 'C');

        $y = $y + 8;
        $pdf->SetXY(25, $y);
        $pdf->Cell(60, 3, 'CI: ' . ($egreso->identificador->CI_NIT ?? '_________'), 0, 1, 'C');
        $pdf->SetXY(120, $y);
        
        $operadorCI = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $egreso->IdOperador)
            ->value('i.CI_NIT') ?? '_________';
        
        $pdf->Cell(60, 3, 'CI: ' . $operadorCI, 0, 1, 'C');

        // 🔥 Limpiar buffers antes de enviar
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $pdf->Output('egreso_' . $egreso->NumeroEgreso . '.pdf', 'I');
        exit;
    }

    private function getFechasDisponibles()
    {
        $fechas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('ActivoInactivo', 0)
            ->where('CierreSucursal', 0)
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

        $todasFechas = $fechas->merge($fechasAux);
        
        if ($todasFechas->isEmpty()) {
            return collect();
        }
        
        return $todasFechas->unique('id');
    }
}