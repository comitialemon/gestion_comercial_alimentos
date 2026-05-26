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
    /**
     * Listado de egresos (con filtro por estado)
     */
    public function index(Request $request)
    {
        $query = Egreso::porContexto()
            ->with(['identificador', 'fecha']);

        // 🔥 FILTRAR POR ESTADO si se especifica
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }

        $egresos = $query->orderBy('IdEgreso', 'desc')->paginate(20);

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
            'filtroEstado' => $request->estado,
        ]);
    }

    /**
     * Formulario de creación (carga borrador automáticamente)
     */
    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // 🔥 BUSCAR BORRADOR (ActivoInactivo = 0) para este operador
        $borrador = Egreso::porContexto()
            ->where('ActivoInactivo', 0)
            ->where('IdOperador', $operadorId)
            ->orderBy('IdEgreso', 'desc')
            ->first();

        $egresoData = null;
        if ($borrador) {
            $egresoData = $borrador;
            $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $borrador->IdDiario)
                ->value('NumeroDiario');
            $egresoData->numero_diario = $numeroDiario;
        }

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
            'egreso' => $egresoData,
            'fechas' => $fechas,
            'identificadores' => $identificadores,
            'cuentasHaber' => $cuentasHaber,
            'cuentasDebe' => $cuentasDebe,
            'editando' => $borrador ? true : false,
        ]);
    }

    /**
     * Editar egreso específico
     */
    public function edit($id)
    {
        $egreso = Egreso::porContexto()
            ->where('ActivoInactivo', 0)
            ->findOrFail($id);

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

    /**
     * Actualizar egreso existente
     */
    public function update(Request $request, $id)
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

            // Buscar el egreso (puede ser borrador o contabilizado)
            $egreso = Egreso::porContexto()->findOrFail($id);

            $numeroEgreso = $egreso->NumeroEgreso;
            $idDiario = $egreso->IdDiario;

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
                    'Glosa' => $request->Glosa . ' - CE No ' . $numeroEgreso,
                    'D_H' => 'D',
                    'MontoBolivianos' => $totalMontoBolivianos,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $totalMontoUFV,
                    'IdIdentificador' => $request->IdIdentificador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Insertar nuevo asiento HABER
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $idDiario,
                    'IdCuenta' => $request->IdCuentaHaber,
                    'Glosa' => $request->Glosa . ' - CE No ' . $numeroEgreso,
                    'D_H' => 'H',
                    'MontoBolivianos' => $totalMontoBolivianos,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $totalMontoBolivianos,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Actualizar egreso (ActivoInactivo se pone a 1 porque ya se guardó definitivamente)
            $egreso->update([
                'IdFecha' => $request->IdFecha,
                'IdCuentaDebe' => $request->IdCuentaDebe,
                'IdCuentaHaber' => $request->IdCuentaHaber,
                'IdIdentificador' => $request->IdIdentificador,
                'Glosa' => $request->Glosa,
                'TotalBolivianos' => $request->TotalBolivianos,
                'ActivoInactivo' => 1,  // Al guardar, queda contabilizado
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'pdf_url' => route('egresos.pdf', $egreso->IdEgreso),
                'message' => 'Egreso actualizado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            \Log::error('Error al actualizar egreso: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar egreso (nuevo o edición)
     */
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

            // 🔥 LOG PARA DEPURACIÓN
            \Log::info('=== INICIO STORE EGRESO ===');
            \Log::info('IdEgreso recibido: ' . ($request->IdEgreso ?? 'NULL'));

            if ($request->has('IdEgreso') && $request->IdEgreso) {
                // ==================== EDITAR EGRESO EXISTENTE ====================
                \Log::info('Modo: EDITAR EGRESO EXISTENTE - ID: ' . $request->IdEgreso);
                
                $egreso = Egreso::porContexto()
                    ->where('ActivoInactivo', 0)
                    ->findOrFail($request->IdEgreso);

                $numeroEgreso = $egreso->NumeroEgreso;
                $idDiario = $egreso->IdDiario;

                \Log::info('Egreso encontrado - Numero: ' . $numeroEgreso . ', IdDiario: ' . $idDiario);

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
                        'Glosa' => $request->Glosa . ' - CE No ' . $numeroEgreso,
                        'D_H' => 'D',
                        'MontoBolivianos' => $totalMontoBolivianos,
                        'TipoCambio' => $ufvActual,
                        'MontoOtraMoneda' => $totalMontoUFV,
                        'IdIdentificador' => $request->IdIdentificador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                // Insertar nuevo asiento HABER
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $idDiario,
                        'IdCuenta' => $request->IdCuentaHaber,
                        'Glosa' => $request->Glosa . ' - CE No ' . $numeroEgreso,
                        'D_H' => 'H',
                        'MontoBolivianos' => $totalMontoBolivianos,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $totalMontoBolivianos,
                        'IdIdentificador' => $identificadorOperador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                // 🔥 ACTUALIZAR EGRESO (NO cambiar ActivoInactivo aquí, ya está en 1)
                $egreso->update([
                    'IdFecha' => $request->IdFecha,
                    'IdCuentaDebe' => $request->IdCuentaDebe,
                    'IdCuentaHaber' => $request->IdCuentaHaber,
                    'IdIdentificador' => $request->IdIdentificador,
                    'Glosa' => $request->Glosa,
                    'TotalBolivianos' => $request->TotalBolivianos,
                    // 'ActivoInactivo' => 1,  // ← QUITAR ESTA LÍNEA, el borrador ya está en 1 cuando se guarda
                ]);

                \Log::info('Egreso actualizado correctamente - ID: ' . $egreso->IdEgreso);

            } else {
                // ==================== NUEVO EGRESO ====================
                \Log::info('Modo: NUEVO EGRESO');
                
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

                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $idDiario,
                        'IdCuenta' => $request->IdCuentaDebe,
                        'Glosa' => $request->Glosa . ' - CE No ' . $numeroEgreso,
                        'D_H' => 'D',
                        'MontoBolivianos' => $totalMontoBolivianos,
                        'TipoCambio' => $ufvActual,
                        'MontoOtraMoneda' => $totalMontoUFV,
                        'IdIdentificador' => $request->IdIdentificador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $idDiario,
                        'IdCuenta' => $request->IdCuentaHaber,
                        'Glosa' => $request->Glosa . ' - CE No ' . $numeroEgreso,
                        'D_H' => 'H',
                        'MontoBolivianos' => $totalMontoBolivianos,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $totalMontoBolivianos,
                        'IdIdentificador' => $identificadorOperador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);

                $egreso = Egreso::create([
                    'IdDiario' => $idDiario,
                    'NumeroEgreso' => $numeroEgreso,
                    'IdFecha' => $request->IdFecha,
                    'IdCuentaDebe' => $request->IdCuentaDebe,
                    'IdCuentaHaber' => $request->IdCuentaHaber,
                    'IdIdentificador' => $request->IdIdentificador,
                    'Glosa' => $request->Glosa,
                    'TotalBolivianos' => $request->TotalBolivianos,
                    'ActivoInactivo' => 1,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'IdOperador' => $operadorId,
                ]);

                \Log::info('Nuevo egreso creado - ID: ' . $egreso->IdEgreso . ', Número: ' . $numeroEgreso);
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            \Log::info('=== EGRESO GUARDADO EXITOSAMENTE ===');

            return response()->json([
                'success' => true,
                'pdf_url' => route('egresos.pdf', $egreso->IdEgreso),
                'message' => 'Egreso guardado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            \Log::error('=== ERROR AL GUARDAR EGRESO ===');
            \Log::error('Mensaje: ' . $e->getMessage());
            \Log::error('Archivo: ' . $e->getFile());
            \Log::error('Línea: ' . $e->getLine());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vista para gestión de estados (Activar/Inactivar egresos)
     */
    public function gestionEstado(Request $request)
    {
        $query = Egreso::porContexto()
            ->with(['identificador']);

        // 🔥 FILTRAR POR ESTADO
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }

        // 🔥 NUEVO: BUSCADOR por número de egreso (búsqueda en servidor)
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('NumeroEgreso', 'LIKE', "%{$buscar}%");
        }

        $egresos = $query->orderBy('IdEgreso', 'desc')->paginate(20);

        // Agregar número de diario y fecha formateada
        $egresos->getCollection()->transform(function ($egreso) {
            $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $egreso->IdDiario)
                ->value('NumeroDiario');
            
            $egreso->numero_diario = $numeroDiario ?? '-';
            
            $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->where('IdFecha', $egreso->IdFecha)
                ->first();
            
            $egreso->fecha_formateada = $fechaData ? date('d/m/Y', strtotime($fechaData->Fecha)) : '-';
            
            return $egreso;
        });

        return Inertia::render('Gestion/Contabilidad/Egresos/GestionEstado', [
            'egresos' => $egresos,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,  // 🔥 Enviar el término de búsqueda a la vista
        ]);
    }

    /**
     * 🔥 CAMBIAR ESTADO (Activar/Inactivar)
     */
    public function cambiarEstado($id)
    {
        try {
            $egreso = Egreso::porContexto()->findOrFail($id);
            
            $nuevoEstado = $egreso->ActivoInactivo == 1 ? 0 : 1;
            
            // Validación al activar
            if ($nuevoEstado == 1 && $egreso->ActivoInactivo == 0) {
                if (!$egreso->IdFecha || !$egreso->IdIdentificador || !$egreso->TotalBolivianos || $egreso->TotalBolivianos <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede activar: Faltan datos obligatorios'
                    ], 400);
                }
            }
            
            $egreso->update(['ActivoInactivo' => $nuevoEstado]);
            
            $mensaje = $nuevoEstado == 1 ? 'Egreso activado correctamente' : 'Egreso desactivado correctamente';
            
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'nuevo_estado' => $nuevoEstado
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar PDF del egreso
     */
    public function pdf($id)
    {
        $egreso = Egreso::porContexto()
            ->with(['identificador', 'fecha', 'cuentaDebe', 'cuentaHaber'])
            ->findOrFail($id);

        $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('conta_diario')
            ->where('IdDiario', $egreso->IdDiario)
            ->value('NumeroDiario');

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', session('cliente_id'))
            ->first();

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', session('cliente_sucursal_id'))
            ->first();

        $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $egreso->IdFecha)
            ->first();
        
        $fechaFormateada = $fechaData ? date('d-m-Y', strtotime($fechaData->Fecha)) : '-';

        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $egreso->IdOperador)
            ->select('i.Nombre', 'i.CI_NIT')
            ->first();

        if (ob_get_length()) {
            ob_end_clean();
        }

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

        $pdf->SetXY(15, 5);
        $pdf->SetFont('courier', 'B', 8);
        $pdf->Cell(180, 3, $empresa->Nombre ?? '', 0, 1, 'L');

        $pdf->SetXY(15, 10);
        $pdf->Cell(180, 3, $sucursal->Nombre ?? '', 0, 1, 'L');

        $y = 6;
        $pdf->SetXY(150, $y);
        $pdf->SetFont('courier', '', 8);
        $pdf->Cell(10, 3, "Fecha     : {$fechaFormateada}", 0, 1, 'L');

        $y = $y + 4;
        $pdf->SetXY(150, $y);
        $pdf->Cell(10, 3, "Diario No : {$numeroDiario}", 0, 1, 'L');

        $y = $pdf->GetY() + 5;
        $pdf->SetXY(5, $y);
        $pdf->SetFont('courier', 'B', 20);
        $pdf->Cell(200, 5, 'RECIBO DE EGRESO', 0, 1, 'C');
        
        $y = $y + 7;
        $pdf->SetXY(93, $y);
        $pdf->SetFont('courier', 'B', 16);
        $pdf->Cell(18, 3, 'N°' . $egreso->NumeroEgreso, 0, 1, 'L');

        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 12);
        $pdf->SetXY(5, $y);
        $pdf->Cell(200, 3, '(Expresado en Bolivianos)', 0, 1, 'C');

        $y = $pdf->GetY() + 8;
        $pdf->SetFont('courier', 'B', 10);
        
        $pdf->SetXY(15, $y);
        $pdf->Cell(200, 3, 'Entregado a : ' . ($egreso->identificador->Nombre ?? 'N/D') . ' - CI: ' . ($egreso->identificador->CI_NIT ?? 'N/D'), 0, 1, 'L');
        
        $y = $pdf->GetY() + 4;
        $pdf->SetXY(15, $y);
        $pdf->Cell(200, 3, 'Cuenta (Debe) : ' . ($egreso->cuentaDebe->Cuenta ?? 'N/D') . ' - ' . ($egreso->cuentaDebe->Descripcion ?? ''), 0, 1, 'L');
        
        $y = $pdf->GetY() + 4;
        $pdf->SetXY(15, $y);
        $pdf->Cell(200, 3, 'Cuenta (Haber) : ' . ($egreso->cuentaHaber->Cuenta ?? 'N/D') . ' - ' . ($egreso->cuentaHaber->Descripcion ?? ''), 0, 1, 'L');
        
        $pdf->SetXY(155, $y);
        $pdf->Cell(200, 3, 'Monto : ' . number_format($egreso->TotalBolivianos, 2, ',', '.'), 0, 1, 'L');

        $y = $pdf->GetY() + 8;
        $pdf->SetFont('courier', 'B', 9);
        $pdf->SetXY(15, $y);
        $pdf->Cell(185, 4, 'GLOSA:', 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetFont('courier', '', 9);
        $pdf->SetXY(15, $y);
        $pdf->MultiCell(185, 4, $egreso->Glosa ?? '', 0, 'L');

        $y = $pdf->GetY() + 20;
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
        $pdf->Cell(60, 3, $operador->Nombre ?? '_________________', 0, 1, 'C');

        $y = $y + 8;
        $pdf->SetXY(25, $y);
        $pdf->Cell(60, 3, 'CI: ' . ($egreso->identificador->CI_NIT ?? '_________'), 0, 1, 'C');
        $pdf->SetXY(120, $y);
        $pdf->Cell(60, 3, 'CI: ' . ($operador->CI_NIT ?? '_________'), 0, 1, 'C');

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="egreso_' . $egreso->NumeroEgreso . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
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

        return $fechas->merge($fechasAux)->unique('id');
    }
    
}