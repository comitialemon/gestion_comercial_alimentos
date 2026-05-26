<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\Ingreso;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Fecha;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngresoController extends Controller
{
    public function index(Request $request)
    {
        $query = Ingreso::porContexto()
            ->with(['identificador', 'fecha']);

        // 🔥 FILTRAR POR ESTADO
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }

        // 🔥 BUSCAR POR NÚMERO DE INGRESO
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('NumeroIngreso', 'LIKE', "%{$buscar}%");
        }

        $ingresos = $query->orderBy('IdIngreso', 'desc')->paginate(20);

        // Agregar datos adicionales para la tabla
        foreach ($ingresos as $ingreso) {
            $ingreso->numero_diario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $ingreso->IdDiario)
                ->value('NumeroDiario');
            
            $ingreso->fecha_formateada = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->where('IdFecha', $ingreso->IdFecha)
                ->value(DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y')"));
        }

        return Inertia::render('Gestion/Contabilidad/Ingresos/Index', [
            'ingresos' => $ingresos,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
        ]);
    }

    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // 🔥 BUSCAR BORRADOR (ActivoInactivo = 0) para este operador
        $borrador = Ingreso::porContexto()
            ->where('ActivoInactivo', 0)
            ->where('IdOperador', $operadorId)  // Mismo operador que lo creó
            ->orderBy('IdIngreso', 'desc')
            ->first();

        // Si existe borrador, cargar sus datos
        $ingresoData = null;
        if ($borrador) {
            $ingresoData = $borrador;
            // Obtener número de diario
            $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $borrador->IdDiario)
                ->value('NumeroDiario');
            $ingresoData->numero_diario = $numeroDiario;
        }

        $fechas = $this->getFechasDisponibles();
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $cuentasDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'D')
            ->whereIn('cs.Cuenta', ['Ingreso', 'CajaSucursal'])
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        $cuentasHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'H')
            ->where('cs.Cuenta', 'Ingreso')
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        return Inertia::render('Gestion/Contabilidad/Ingresos/Create', [
            'ingreso' => $ingresoData,  // Puede ser null o el borrador
            'fechas' => $fechas,
            'identificadores' => $identificadores,
            'cuentasDebe' => $cuentasDebe,
            'cuentasHaber' => $cuentasHaber,
            'editando' => $borrador ? true : false,  // Si hay borrador, estamos editando
        ]);
    }

    public function edit($id)
    {
        $ingreso = Ingreso::porContexto()
            ->where('ActivoInactivo', 0)
            ->findOrFail($id);

        $sucursalId = session('cliente_sucursal_id');

        $fechas = $this->getFechasDisponibles();
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $cuentasDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'D')
            ->whereIn('cs.Cuenta', ['Ingreso', 'CajaSucursal'])
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        $cuentasHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'H')
            ->where('cs.Cuenta', 'Ingreso')
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        return Inertia::render('Gestion/Contabilidad/Ingresos/Create', [
            'ingreso' => $ingreso,
            'fechas' => $fechas,
            'identificadores' => $identificadores,
            'cuentasDebe' => $cuentasDebe,
            'cuentasHaber' => $cuentasHaber,
            'editando' => true,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'IdFecha' => 'required|exists:todos_fecha,IdFecha',
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'IdCuentaDebe' => 'required|exists:conta_cuenta,IdCuenta',
            'IdCuentaHaber' => 'required|exists:conta_cuenta,IdCuenta',
            'Glosa' => 'required|string|max:200',
            'TotalBolivianos' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            $identificadorOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $operadorId)
                ->value('IdIdentificador');

            $ufvActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_factorcambio')
                ->where('IdFecha', $request->IdFecha)
                ->where('IdMoneda', 3)
                ->value('FactorCambio') ?? 1;

            $totalMontoBolivianos = $request->TotalBolivianos;
            $totalMontoUFV = round($totalMontoBolivianos / $ufvActual, 2);

            $ingreso = Ingreso::porContexto()
                ->where('ActivoInactivo', 0)
                ->findOrFail($id);

            $numeroIngreso = $ingreso->NumeroIngreso;
            $idDiario = $ingreso->IdDiario;

            // Actualizar fecha en diario
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $idDiario)
                ->update(['IdFecha' => $request->IdFecha]);

            // Eliminar asientos antiguos
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->where('IdDiario', $idDiario)
                ->delete();

            // Insertar nuevo asiento DEBE
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $idDiario,
                    'IdCuenta' => $request->IdCuentaDebe,
                    'Glosa' => $request->Glosa . ' - CI No ' . $numeroIngreso,
                    'D_H' => 'D',
                    'MontoBolivianos' => $totalMontoBolivianos,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $totalMontoBolivianos,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Insertar nuevo asiento HABER
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $idDiario,
                    'IdCuenta' => $request->IdCuentaHaber,
                    'Glosa' => $request->Glosa . ' - CI No ' . $numeroIngreso,
                    'D_H' => 'H',
                    'MontoBolivianos' => $totalMontoBolivianos,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $totalMontoUFV,
                    'IdIdentificador' => $request->IdIdentificador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Actualizar ingreso
            $ingreso->update([
                'IdFecha' => $request->IdFecha,
                'IdCuentaDebe' => $request->IdCuentaDebe,
                'IdCuentaHaber' => $request->IdCuentaHaber,
                'IdIdentificador' => $request->IdIdentificador,
                'Glosa' => $request->IdGlosa ?? $request->Glosa,
                'TotalBolivianos' => $request->TotalBolivianos,
                'ActivoInactivo' => 1,
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'pdf_url' => route('ingresos.pdf', $ingreso->IdIngreso),
                'message' => 'Ingreso actualizado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al actualizar ingreso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'IdFecha' => 'required|exists:todos_fecha,IdFecha',
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'IdCuentaDebe' => 'required|exists:conta_cuenta,IdCuenta',
            'IdCuentaHaber' => 'required|exists:conta_cuenta,IdCuenta',
            'Glosa' => 'required|string|max:200',
            'TotalBolivianos' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // 🔥 OBTENER IDENTIFICADOR DEL OPERADOR
            $identificadorOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $operadorId)
                ->value('IdIdentificador');

            // 🔥 OBTENER UFV ACTUAL
            $ufvActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_factorcambio')
                ->where('IdFecha', $request->IdFecha)
                ->where('IdMoneda', 3)
                ->value('FactorCambio') ?? 1;

            $totalMontoBolivianos = $request->TotalBolivianos;
            $totalMontoUFV = round($totalMontoBolivianos / $ufvActual, 2);

            if ($request->has('IdIngreso') && $request->IdIngreso) {
                // ==================== EDITAR INGRESO EXISTENTE ====================
                $ingreso = Ingreso::porContexto()
                    ->where('ActivoInactivo', 0)
                    ->findOrFail($request->IdIngreso);

                $numeroIngreso = $ingreso->NumeroIngreso;
                $idDiario = $ingreso->IdDiario;

                // Actualizar fecha en diario
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->where('IdDiario', $idDiario)
                    ->update(['IdFecha' => $request->IdFecha]);

                // 🔥 ELIMINAR asientos antiguos
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->where('IdDiario', $idDiario)
                    ->delete();

                // 🔥 INSERTAR NUEVOS ASIENTOS
                // Asiento DEBE (Caja)
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $idDiario,
                        'IdCuenta' => $request->IdCuentaDebe,
                        'Glosa' => $request->Glosa . ' - CI No ' . $numeroIngreso,
                        'D_H' => 'D',
                        'MontoBolivianos' => $totalMontoBolivianos,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $totalMontoBolivianos,
                        'IdIdentificador' => $identificadorOperador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                // Asiento HABER (Ingreso)
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $idDiario,
                        'IdCuenta' => $request->IdCuentaHaber,
                        'Glosa' => $request->Glosa . ' - CI No ' . $numeroIngreso,
                        'D_H' => 'H',
                        'MontoBolivianos' => $totalMontoBolivianos,
                        'TipoCambio' => $ufvActual,
                        'MontoOtraMoneda' => $totalMontoUFV,
                        'IdIdentificador' => $request->IdIdentificador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                // Actualizar ingreso
                $ingreso->update([
                    'IdFecha' => $request->IdFecha,
                    'IdCuentaDebe' => $request->IdCuentaDebe,
                    'IdCuentaHaber' => $request->IdCuentaHaber,
                    'IdIdentificador' => $request->IdIdentificador,
                    'Glosa' => $request->Glosa,
                    'TotalBolivianos' => $request->TotalBolivianos,
                    'ActivoInactivo' => 1,
                ]);

            } else {
                // ==================== NUEVO INGRESO ====================
                
                // 🔥 VERIFICAR SI YA EXISTE UN BORRADOR PARA ESTE OPERADOR
                $borradorExistente = Ingreso::porContexto()
                    ->where('ActivoInactivo', 0)
                    ->where('IdOperador', $operadorId)
                    ->first();
                
                if ($borradorExistente) {
                    DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya tienes un ingreso en edición. Finalízalo o cancélalo primero.',
                        'borrador_id' => $borradorExistente->IdIngreso
                    ], 409);
                }
                
                // Generar número de ingreso
                $maxNumero = Ingreso::porContexto()->max('NumeroIngreso');
                $numeroIngreso = ($maxNumero ?? 0) + 1;

                // Generar número de diario
                $maxNumeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->max('NumeroDiario');
                $numeroDiario = ($maxNumeroDiario ?? 0) + 1;

                // Insertar diario
                $idDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->insertGetId([
                        'IdFecha' => $request->IdFecha,
                        'IdTipoDiario' => 9,
                        'NumeroDiario' => $numeroDiario,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                        'Contabilizado' => 1,
                        'IdOperadorIngreso' => $operadorId,
                        'FechaIngreso' => now(),
                        'IdoperadorEdita' => $operadorId,
                        'FechaEdita' => now(),
                    ]);

                // 🔥 INSERTAR ASIENTOS
                // Asiento DEBE (Caja)
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $idDiario,
                        'IdCuenta' => $request->IdCuentaDebe,
                        'Glosa' => $request->Glosa . ' - CI No ' . $numeroIngreso,
                        'D_H' => 'D',
                        'MontoBolivianos' => $totalMontoBolivianos,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $totalMontoBolivianos,
                        'IdIdentificador' => $identificadorOperador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                // Asiento HABER (Ingreso)
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $idDiario,
                        'IdCuenta' => $request->IdCuentaHaber,
                        'Glosa' => $request->Glosa . ' - CI No ' . $numeroIngreso,
                        'D_H' => 'H',
                        'MontoBolivianos' => $totalMontoBolivianos,
                        'TipoCambio' => $ufvActual,
                        'MontoOtraMoneda' => $totalMontoUFV,
                        'IdIdentificador' => $request->IdIdentificador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                // Insertar ingreso (como BORRADOR - ActivoInactivo = 0)
                $ingreso = Ingreso::create([
                    'IdDiario' => $idDiario,
                    'NumeroIngreso' => $numeroIngreso,
                    'IdFecha' => $request->IdFecha,
                    'IdCuentaDebe' => $request->IdCuentaDebe,
                    'IdCuentaHaber' => $request->IdCuentaHaber,
                    'IdIdentificador' => $request->IdIdentificador,
                    'Glosa' => $request->Glosa,
                    'TotalBolivianos' => $request->TotalBolivianos,
                    'ActivoInactivo' => 0,  // 🔥 IMPORTANTE: Guardar como BORRADOR
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'IdOperador' => $operadorId,
                ]);
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'pdf_url' => route('ingresos.pdf', $ingreso->IdIngreso),
                'message' => 'Ingreso guardado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al guardar ingreso: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pdf($id)
    {
        $ingreso = Ingreso::porContexto()
            ->with(['identificador', 'fecha', 'cuentaDebe', 'cuentaHaber'])
            ->findOrFail($id);

        // Datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first();

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', session('cliente_sucursal_id'))
            ->first();

        // Número de diario
        $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->where('IdDiario', $ingreso->IdDiario)
            ->value('NumeroDiario');

        // Fecha formateada
        $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $ingreso->IdFecha)
            ->first();
        
        $fechaFormateada = $fechaData ? date('d-m-Y', strtotime($fechaData->Fecha)) : '-';

        // Operador
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $ingreso->IdOperador)
            ->first();

        // Limpiar buffer antes de generar PDF
        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 60);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 8);

        // Logo
        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 10, 50, 20);
        }

        // Empresa y sucursal
        $pdf->SetXY(15, 5);
        $pdf->SetFont('courier', 'B', 8);
        $pdf->Cell(180, 3, $empresa->Nombre ?? '', 0, 1, 'L');

        $pdf->SetXY(15, 10);
        $pdf->Cell(180, 3, $sucursal->Nombre ?? '', 0, 1, 'L');

        // Fecha y diario
        $y = 5;
        $pdf->SetXY(150, $y);
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(10, 3, "Fecha     : {$fechaFormateada}", 0, 1, 'L');

        $y = $y + 4;
        $pdf->SetXY(150, $y);
        $pdf->Cell(10, 3, "Diario No : {$numeroDiario}", 0, 1, 'L');

        // Título
        $y = $y + 20;
        $pdf->SetXY(5, $y);
        $pdf->SetFont('courier', 'B', 20);
        $pdf->Cell(200, 5, "Recibo de Ingreso No {$ingreso->NumeroIngreso}", 0, 1, 'C');

        $y = $y + 7;
        $pdf->SetFont('courier', 'B', 12);
        $pdf->SetXY(5, $y);
        $pdf->Cell(200, 3, '(Expresado en Bolivianos)', 0, 1, 'C');

        // Efectivo recibido de
        $y = $pdf->GetY() + 8;
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetXY(15, $y);
        $pdf->Cell(20, 3, 'EFECTIVO RECIBIDO DE :', 0, 1, 'L');
        
        $pdf->SetXY(57, $y);
        $pdf->SetFont('courier', '', 8);
        $identificadorNombre = $ingreso->identificador->Nombre ?? '';
        $identificadorCI = $ingreso->identificador->CI_NIT ?? '';
        $pdf->MultiCell(150, 4, "{$identificadorNombre} CON CI : {$identificadorCI}", 0, 'L', false);

        // Con deposito en la cuenta
        $y = $pdf->GetY() + 4;
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetXY(15, $y);
        $pdf->Cell(20, 3, 'CON DEPOSITO EN LA CUENTA :', 0, 1, 'L');
        
        $pdf->SetXY(65, $y);
        $pdf->SetFont('courier', '', 8);
        $cuentaDebeNombre = $ingreso->cuentaDebe->Cuenta ?? '';
        $cuentaDebeDesc = $ingreso->cuentaDebe->Descripcion ?? '';
        $pdf->MultiCell(150, 4, "{$cuentaDebeNombre} {$cuentaDebeDesc}", 0, 'L', false);

        // Por concepto de
        $y = $pdf->GetY() + 4;
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetXY(15, $y);
        $pdf->Cell(20, 3, 'POR CONCEPTO DE :', 0, 1, 'L');
        
        $pdf->SetXY(45, $y);
        $pdf->SetFont('courier', '', 8);
        $cuentaHaberNombre = $ingreso->cuentaHaber->Cuenta ?? '';
        $cuentaHaberDesc = $ingreso->cuentaHaber->Descripcion ?? '';
        $pdf->MultiCell(150, 4, "{$cuentaHaberNombre} {$cuentaHaberDesc}", 0, 'L', false);

        // Detalle
        $y = $pdf->GetY() + 4;
        $pdf->SetFont('courier', 'B', 8);
        $pdf->SetXY(15, $y);
        $pdf->Cell(20, 3, 'DETALLE :', 0, 1, 'L');
        
        $pdf->SetXY(35, $y);
        $pdf->SetFont('courier', '', 8);
        $pdf->MultiCell(150, 4, $ingreso->Glosa, 0, 'L', false);

        // Monto
        $y = $pdf->GetY() + 8;
        $pdf->SetXY(15, $y);
        $pdf->SetFont('courier', 'B', 14);
        $pdf->Cell(20, 3, "Bs.- " . number_format($ingreso->TotalBolivianos, 2, ',', '.'), 0, 1, 'L');

        // Firmas
        $y = $pdf->GetY() + 25;
        $pdf->SetFont('courier', '', 8);
        $pdf->SetXY(25, $y);
        $pdf->Cell(40, 3, 'RECIBI CONFORME', 0, 1, 'C');
        $pdf->SetXY(120, $y);
        $pdf->Cell(40, 3, 'ENTREGUE CONFORME', 0, 1, 'C');

        $y = $y + 5;
        $pdf->SetXY(25, $y);
        $operadorNombre = $operador->Nombre ?? '';
        $pdf->Cell(40, 3, $identificadorNombre, 0, 1, 'C');
        $pdf->SetXY(120, $y);
        $pdf->Cell(40, 3, $operadorNombre, 0, 1, 'C');

        $y = $y + 5;
        $pdf->SetXY(25, $y);
        $pdf->Cell(40, 3, "CI : {$identificadorCI}", 0, 1, 'C');
        $pdf->SetXY(120, $y);
        $operadorCI = $operador->CI_NIT ?? '';
        $pdf->Cell(40, 3, "CI : {$operadorCI}", 0, 1, 'C');

        // 🔥 Forzar headers correctos para mostrar en navegador (no descargar)
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="ingreso_' . $ingreso->NumeroIngreso . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        $pdf->Output('ingreso_' . $ingreso->NumeroIngreso . '.pdf', 'I');
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

        return $fechas->merge($fechasAux)->unique('id');
    }

    /**
     * Vista para gestión de estados (Activar/Inactivar ingresos)
     */
    public function gestionEstado(Request $request)
    {
        $query = Ingreso::porContexto()
            ->with(['identificador']);

        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('NumeroIngreso', 'LIKE', "%{$buscar}%");
        }

        $ingresos = $query->orderBy('IdIngreso', 'desc')->paginate(20);

        foreach ($ingresos as $ingreso) {
            $ingreso->fecha_formateada = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->where('IdFecha', $ingreso->IdFecha)
                ->value(DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y')"));
        }

        return Inertia::render('Gestion/Contabilidad/Ingresos/GestionEstado', [
            'ingresos' => $ingresos,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
        ]);
    }

    /**
     * Cambiar estado (Activar/Inactivar)
     */
    public function cambiarEstado($id)
    {
        try {
            $ingreso = Ingreso::porContexto()->findOrFail($id);
            
            $nuevoEstado = $ingreso->ActivoInactivo == 1 ? 0 : 1;
            
            if ($nuevoEstado == 1 && $ingreso->ActivoInactivo == 0) {
                if (!$ingreso->IdFecha || !$ingreso->IdIdentificador || !$ingreso->TotalBolivianos || $ingreso->TotalBolivianos <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede activar: Faltan datos obligatorios'
                    ], 400);
                }
            }
            
            $ingreso->update(['ActivoInactivo' => $nuevoEstado]);
            
            $mensaje = $nuevoEstado == 1 ? 'Ingreso activado correctamente' : 'Ingreso desactivado correctamente';
            
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'nuevo_estado' => $nuevoEstado
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de ingreso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

}