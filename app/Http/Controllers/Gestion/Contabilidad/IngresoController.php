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
    /**
     * Listado de ingresos
     */
    public function index(Request $request)
    {
        $query = Ingreso::porContexto()
            ->with(['identificador', 'fecha']);

        // Filtrar por estado
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }

        // Buscar por número de ingreso
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('NumeroIngreso', 'LIKE', "%{$buscar}%");
        }

        $ingresos = $query->orderBy('IdIngreso', 'desc')->paginate(20);

        // Agregar datos adicionales
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

    /**
     * Mostrar formulario para crear nuevo ingreso
     */
    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // Buscar borrador (ActivoInactivo = 0) para este operador
        $borrador = Ingreso::porContexto()
            ->where('ActivoInactivo', 0)
            ->where('IdOperador', $operadorId)
            ->orderBy('IdIngreso', 'desc')
            ->first();

        $ingresoData = null;
        if ($borrador) {
            $ingresoData = $borrador;
            $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $borrador->IdDiario)
                ->value('NumeroDiario');
            $ingresoData->numero_diario = $numeroDiario;
        }

        $fechas = $this->getFechasDisponibles();
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        // 🔥 IGUAL QUE SCRIPTCASE: Cuenta "CajaSucursal" para DEBE
        $cuentasDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'D')
            ->where('cs.Cuenta', 'CajaSucursal')
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        // 🔥 IGUAL QUE SCRIPTCASE: Cuentas con Dinamica 'H' para HABER
        $cuentasHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'H')
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        return Inertia::render('Gestion/Contabilidad/Ingresos/Create', [
            'ingreso' => $ingresoData,
            'fechas' => $fechas,
            'identificadores' => $identificadores,
            'cuentasDebe' => $cuentasDebe,
            'cuentasHaber' => $cuentasHaber,
            'editando' => $borrador ? true : false,
        ]);
    }

    /**
     * Mostrar formulario para editar ingreso (borrador)
     */
    public function edit($id)
    {
        $ingreso = Ingreso::porContexto()
            ->where('ActivoInactivo', 0)
            ->findOrFail($id);

        $sucursalId = session('cliente_sucursal_id');

        $fechas = $this->getFechasDisponibles();
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        // 🔥 IGUAL QUE SCRIPTCASE
        $cuentasDebe = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'D')
            ->where('cs.Cuenta', 'CajaSucursal')
            ->select('c.IdCuenta as id', DB::raw("CONCAT(c.Cuenta, ' - ', c.Descripcion) as nombre"))
            ->orderBy('c.Cuenta')
            ->get();

        // 🔥 IGUAL QUE SCRIPTCASE
        $cuentasHaber = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_cuenta as c')
            ->join('conta_cuenta_sucursales as cs', 'c.IdCuenta', '=', 'cs.IdCuenta')
            ->where('cs.IdSucursal', $sucursalId)
            ->where('cs.DinamicaCuenta', 'H')
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

    /**
     * ACTUALIZAR INGRESO (igual que Scriptcase - modo edición)
     */
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
            // 🔥 IDENTIFICADOR DEL OPERADOR (igual que Scriptcase)
            $identificadorOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $operadorId)
                ->value('IdIdentificador');

            // 🔥 UFV ACTUAL (igual que Scriptcase)
            $ufvActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_factorcambio')
                ->where('IdFecha', $request->IdFecha)
                ->where('IdMoneda', 3)
                ->value('FactorCambio') ?? 1;

            $totalMontoBolivianos = $request->TotalBolivianos;
            $totalMontoUFV = round($totalMontoBolivianos / $ufvActual, 2);

            // Obtener ingreso (borrador)
            $ingreso = Ingreso::porContexto()
                ->where('ActivoInactivo', 0)
                ->findOrFail($id);

            $numeroIngreso = $ingreso->NumeroIngreso;
            $idDiario = $ingreso->IdDiario;

            // 🔥 ACTUALIZAR FECHA EN DIARIO (igual que Scriptcase)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $idDiario)
                ->update([
                    'IdFecha' => $request->IdFecha,
                    'IdoperadorEdita' => $operadorId,
                    'FechaEdita' => now(),
                ]);

            // 🔥 ELIMINAR ASIENTOS ANTIGUOS (igual que Scriptcase)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->where('IdDiario', $idDiario)
                ->delete();

            // 🔥 INSERTAR NUEVOS ASIENTOS (igual que Scriptcase)
            // Asiento DEBE - Cuenta CajaSucursal
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

            // Asiento HABER - Contracuenta de Ingreso
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

            // 🔥 ACTUALIZAR INGRESO COMO ACTIVO (ActivoInactivo = 1)
            $ingreso->update([
                'IdFecha' => $request->IdFecha,
                'IdCuentaDebe' => $request->IdCuentaDebe,
                'IdCuentaHaber' => $request->IdCuentaHaber,
                'IdIdentificador' => $request->IdIdentificador,
                'Glosa' => $request->Glosa,
                'TotalBolivianos' => $request->TotalBolivianos,
                'ActivoInactivo' => 1,  // ✅ ACTIVO
                'IdOperador' => $operadorId,
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

    /**
     * GUARDAR NUEVO INGRESO (igual que Scriptcase)
     */
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
            // 🔥 IDENTIFICADOR DEL OPERADOR (igual que Scriptcase)
            $identificadorOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $operadorId)
                ->value('IdIdentificador');

            // 🔥 UFV ACTUAL (igual que Scriptcase)
            $ufvActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_factorcambio')
                ->where('IdFecha', $request->IdFecha)
                ->where('IdMoneda', 3)
                ->value('FactorCambio') ?? 1;

            $totalMontoBolivianos = $request->TotalBolivianos;
            $totalMontoUFV = round($totalMontoBolivianos / $ufvActual, 2);

            // 🔥 VERIFICAR SI ES EDICIÓN (IdIngreso existe)
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
                    ->update([
                        'IdFecha' => $request->IdFecha,
                        'IdoperadorEdita' => $operadorId,
                        'FechaEdita' => now(),
                    ]);

                // 🔥 ELIMINAR asientos antiguos
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->where('IdDiario', $idDiario)
                    ->delete();

                // 🔥 INSERTAR NUEVOS ASIENTOS
                // Asiento DEBE - Cuenta CajaSucursal
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

                // Asiento HABER - Contracuenta de Ingreso
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

                // Actualizar ingreso - ACTIVO
                $ingreso->update([
                    'IdFecha' => $request->IdFecha,
                    'IdCuentaDebe' => $request->IdCuentaDebe,
                    'IdCuentaHaber' => $request->IdCuentaHaber,
                    'IdIdentificador' => $request->IdIdentificador,
                    'Glosa' => $request->Glosa,
                    'TotalBolivianos' => $request->TotalBolivianos,
                    'ActivoInactivo' => 1,  // ✅ ACTIVO
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

                // 🔥 GENERAR NÚMERO DE INGRESO (igual que Scriptcase)
                $maxNumero = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_comprobante_ingreso')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->max('NumeroIngreso');
                $numeroIngreso = ($maxNumero ?? 0) + 1;

                // 🔥 GENERAR NÚMERO DE DIARIO (igual que Scriptcase)
                $maxNumeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->max('NumeroDiario');
                $numeroDiario = ($maxNumeroDiario ?? 0) + 1;

                // 🔥 INSERTAR DIARIO (igual que Scriptcase)
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

                // 🔥 INSERTAR ASIENTOS (igual que Scriptcase)
                // Asiento DEBE - Cuenta CajaSucursal
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

                // Asiento HABER - Contracuenta de Ingreso
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

                // 🔥 INSERTAR INGRESO COMO ACTIVO (ActivoInactivo = 1)
                $ingreso = Ingreso::create([
                    'IdDiario' => $idDiario,
                    'NumeroIngreso' => $numeroIngreso,
                    'IdFecha' => $request->IdFecha,
                    'IdCuentaDebe' => $request->IdCuentaDebe,
                    'IdCuentaHaber' => $request->IdCuentaHaber,
                    'IdIdentificador' => $request->IdIdentificador,
                    'Glosa' => $request->Glosa,
                    'TotalBolivianos' => $request->TotalBolivianos,
                    'ActivoInactivo' => 1,  // ✅ ACTIVO (NO BORRADOR)
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

    /**
     * Generar PDF del ingreso
     */
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
            ->select('i.Nombre as nombre', 'i.CI_NIT as ci')
            ->first();

        // Si no se encuentra el operador, usar fallback
        if (!$operador) {
            $operador = (object)[
                'nombre' => $ingreso->identificador->Nombre ?? 'OPERADOR NO ENCONTRADO',
                'ci' => $ingreso->identificador->CI_NIT ?? '0'
            ];
        }

        $identificador = $ingreso->identificador;
        $identificadorNombre = $identificador->Nombre ?? 'No especificado';
        $identificadorCI = $identificador->CI_NIT ?? '0';

        // Limpiar buffer
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
        
        // RECIBÍ CONFORME (quien entrega = Identificador)
        $pdf->SetXY(25, $y);
        $pdf->Cell(40, 3, 'RECIBI CONFORME', 0, 1, 'C');
        
        // ENTREGUE CONFORME (quien recibe = Operador)
        $pdf->SetXY(120, $y);
        $pdf->Cell(40, 3, 'ENTREGUE CONFORME', 0, 1, 'C');

        $y = $y + 5;
        
        // Nombre
        $pdf->SetXY(25, $y);
        $pdf->Cell(40, 3, $identificadorNombre, 0, 1, 'C');
        $pdf->SetXY(120, $y);
        $pdf->Cell(40, 3, $operador->nombre, 0, 1, 'C');

        $y = $y + 5;
        
        // CI
        $pdf->SetXY(25, $y);
        $pdf->Cell(40, 3, "CI : {$identificadorCI}", 0, 1, 'C');
        $pdf->SetXY(120, $y);
        $pdf->Cell(40, 3, "CI : {$operador->ci}", 0, 1, 'C');

        // Forzar headers
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="ingreso_' . $ingreso->NumeroIngreso . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        $pdf->Output('ingreso_' . $ingreso->NumeroIngreso . '.pdf', 'I');
        exit;
    }

    /**
     * Obtener fechas disponibles
     */
    private function getFechasDisponibles()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

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
            ->where('todos_fecha_auxiliar_sucursal.IdCliente', $clienteId)
            ->where('todos_fecha_auxiliar_sucursal.IdSucursal', $sucursalId)
            ->select('todos_fecha.IdFecha as id', DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d/%m/%Y') as fecha"))
            ->orderBy('todos_fecha.IdFecha', 'desc')
            ->get();

        return $fechas->merge($fechasAux)->unique('id');
    }

/**
     * Vista para gestión de estados (Activar/Inactivar ingresos) - CON SUCURSALES
     */
    public function gestionEstado(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        \Log::info('=== GESTION ESTADO INGRESOS ===');
        \Log::info('Cliente ID: ' . $clienteId);
        \Log::info('Sucursal actual: ' . $sucursalId);
        \Log::info('Sucursal seleccionada en filtro: ' . $request->sucursal_id);
        
        // =============================================
        // OBTENER TODAS LAS SUCURSALES DEL CLIENTE
        // =============================================
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // =============================================
        // CONSULTA PRINCIPAL - SIN usar scope porContexto()
        // =============================================
        $query = Ingreso::where('IdCliente', $clienteId)
            ->with(['identificador']);
        
        // 🔥 FILTRO POR SUCURSAL
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('IdSucursal', $request->sucursal_id);
        } else {
            // Por defecto, mostrar la sucursal logueada
            $query->where('IdSucursal', $sucursalId);
        }
        
        // 🔥 FILTRO POR ESTADO
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }
        
        // 🔥 BUSCADOR por número de ingreso
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('NumeroIngreso', 'LIKE', "%{$buscar}%");
        }
        
        $ingresos = $query->orderBy('IdIngreso', 'desc')->paginate(20);
        
        // Enriquecer datos
        $ingresos->getCollection()->transform(function ($ingreso) {
            $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $ingreso->IdDiario)
                ->value('NumeroDiario');
            
            $ingreso->numero_diario = $numeroDiario ?? '-';
            
            $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->where('IdFecha', $ingreso->IdFecha)
                ->first();
            
            $ingreso->fecha_formateada = $fechaData ? date('d/m/Y', strtotime($fechaData->Fecha)) : '-';
            
            // 🔥 Agregar nombre de sucursal
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $ingreso->IdSucursal)
                ->first();
            
            $ingreso->sucursal_nombre = $sucursal ? $sucursal->Nombre : 'Sin sucursal';
            $ingreso->sucursal_numero = $sucursal ? $sucursal->NumeroSucursal : null;
            
            return $ingreso;
        });
        
        return Inertia::render('Gestion/Contabilidad/Ingresos/GestionEstado', [
            'ingresos' => $ingresos,
            'sucursales' => $sucursales,
            'sucursalActual' => $sucursalId,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
            'sucursalSeleccionada' => $request->sucursal_id,
        ]);
    }

    /**
     * Cambiar estado (SOLO DESACTIVAR - Activo → Borrador)
     */
    public function cambiarEstado($id)
    {
        try {
            // 🔥 Buscar SOLO por ID y Cliente (sin scope porContexto)
            $ingreso = Ingreso::where('IdCliente', session('cliente_id'))
                ->where('IdIngreso', $id)
                ->firstOrFail();
            
            if ($ingreso->ActivoInactivo == 1) {
                $ingreso->update(['ActivoInactivo' => 0]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Ingreso desactivado correctamente (pasó a Borrador)',
                    'nuevo_estado' => 0
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Este ingreso ya está en estado BORRADOR. Solo se activa al editarlo y guardarlo.'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de ingreso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar detalle de ingreso
     */
    public function show($id)
    {
        $ingreso = Ingreso::porContexto()
            ->with(['identificador', 'fecha', 'cuentaDebe', 'cuentaHaber'])
            ->findOrFail($id);

        $ingreso->numero_diario = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->where('IdDiario', $ingreso->IdDiario)
            ->value('NumeroDiario');

        return Inertia::render('Gestion/Contabilidad/Ingresos/Show', [
            'ingreso' => $ingreso,
        ]);
    }
}