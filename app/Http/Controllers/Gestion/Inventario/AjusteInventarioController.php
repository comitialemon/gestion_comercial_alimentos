<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\AjusteInventario;
use App\Models\Gestion\Inventario\AjusteInventarioDetalle;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\TipoOperacion;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AjusteInventarioController extends Controller
{
    /**
     * Listado de ajustes contabilizados
     */
    public function index()
    {
        $ajustes = AjusteInventario::porContexto()
            ->where('ActivoInactivo', 1)
            ->with(['tipoOperacion', 'almacen', 'fecha']) // ✅ Agregar 'fecha'
            ->orderBy('IdAjustesPrincipal', 'desc')
            ->paginate(20);

        // ✅ Transformar para agregar fecha formateada
        $ajustes->getCollection()->transform(function ($ajuste) {
            $fechaMostrar = '';
            if ($ajuste->fecha && $ajuste->fecha->Fecha) {
                $fechaMostrar = date('d/m/Y', strtotime($ajuste->fecha->Fecha));
            } else {
                $fechaMostrar = $ajuste->FechaIngreso ? date('d/m/Y', strtotime($ajuste->FechaIngreso)) : '';
            }
            $ajuste->fecha_mostrar = $fechaMostrar;
            return $ajuste;
        });

        return Inertia::render('Gestion/Inventario/AjusteInventario/Index', [
            'ajustes' => $ajustes,
        ]);
    }

    /**
     * Formulario de ajuste (crea o retoma ajuste pendiente)
     */
    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // Buscar ajuste pendiente para este operador
        $ajuste = AjusteInventario::porContexto()
            ->pendientePorOperador()
            ->first();
        
        // Cargar detalles si existe ajuste
        $detalles = [];
        if ($ajuste) {
            $detalles = AjusteInventarioDetalle::where('IdAjustesPrincipal', $ajuste->IdAjustesPrincipal)
                ->with('producto')
                ->get();
        }

        // Datos para selects (siempre se cargan)
        $fechas = $this->getFechasDisponibles();
        
        $tiposOperacion = TipoOperacion::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdTipoOperacion as id', 'Detalle as nombre', 'Concepto']);

        $almacenes = Almacen::porContexto()
            ->orderBy('Almacen')
            ->get(['IdAlmacen as id', 'Almacen as nombre']);

        $personas = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $productos = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Codigo')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);

        return Inertia::render('Gestion/Inventario/AjusteInventario/Create', [
            'ajuste' => $ajuste,
            'detalles' => $detalles,
            'fechas' => $fechas,
            'tiposOperacion' => $tiposOperacion,
            'almacenes' => $almacenes,
            'personas' => $personas,
            'productos' => $productos,
        ]);
    }

    /**
     * Guardar cabecera del ajuste
     */
    public function guardarCabecera(Request $request, $id)
    {
        $ajuste = AjusteInventario::findOrFail($id);

        if ($ajuste->ActivoInactivo != 0) {
            return response()->json(['success' => false, 'message' => 'El ajuste ya fue contabilizado'], 400);
        }

        $ajuste->update([
            'IdFecha' => $request->IdFecha,
            'ConceptoOperacion' => $request->ConceptoOperacion,
            'IdTipoOperacion' => $request->IdTipoOperacion,
            'IdAlmacen' => $request->IdAlmacen,
            'IdRealizadoPor' => $request->IdRealizadoPor,
            'IdAutorizadoPor' => $request->IdAutorizadoPor,
            'Explicacion' => $request->Explicacion ?? '',
            'IdOperadorEdita' => session('operador_id'),
            'FechaActualiza' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Agregar producto al detalle
     */
    public function agregarDetalle(Request $request)
    {
        $request->validate([
            'IdAjustesPrincipal' => 'required|exists:inventario_ajustesprincipal,IdAjustesPrincipal',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'Unidades' => 'required|numeric|min:0.01',
            'Bolivianos' => 'required|numeric|min:0',
        ]);

        $ajuste = AjusteInventario::findOrFail($request->IdAjustesPrincipal);

        if ($ajuste->ActivoInactivo != 0) {
            return response()->json(['success' => false, 'message' => 'El ajuste ya fue contabilizado'], 400);
        }

        $detalle = AjusteInventarioDetalle::create([
            'IdAjustesPrincipal' => $request->IdAjustesPrincipal,
            'IdProducto' => $request->IdProducto,
            'Unidades' => $request->Unidades,
            'Bolivianos' => $request->Bolivianos,
        ]);

        $detalle->load('producto');

        return response()->json([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }

    /**
     * Eliminar producto del detalle
     */
    public function eliminarDetalle($id)
    {
        $detalle = AjusteInventarioDetalle::findOrFail($id);
        $ajuste = AjusteInventario::findOrFail($detalle->IdAjustesPrincipal);

        if ($ajuste->ActivoInactivo != 0) {
            return response()->json(['success' => false, 'message' => 'El ajuste ya fue contabilizado'], 400);
        }

        $detalle->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Contabilizar ajuste
     */
    public function contabilizar($id)
    {
        $ajuste = AjusteInventario::porContexto()
            ->with(['detalles.producto', 'tipoOperacion'])
            ->findOrFail($id);

        if ($ajuste->ActivoInactivo == 1) {
            return redirect()->back()->with('error', 'El ajuste ya fue contabilizado');
        }

        // Validaciones
        if ($ajuste->IdFecha == 0) {
            return redirect()->back()->with('error', 'Seleccione una fecha');
        }
        if ($ajuste->IdTipoOperacion == 0) {
            return redirect()->back()->with('error', 'Seleccione el tipo de operación');
        }
        if ($ajuste->IdAlmacen == 0) {
            return redirect()->back()->with('error', 'Seleccione un almacén');
        }
        if ($ajuste->IdRealizadoPor == 0) {
            return redirect()->back()->with('error', 'Seleccione quién realizó el ajuste');
        }
        if ($ajuste->IdAutorizadoPor == 0) {
            return redirect()->back()->with('error', 'Seleccione quién autorizó el ajuste');
        }
        if ($ajuste->detalles->count() == 0) {
            return redirect()->back()->with('error', 'Agregue al menos un producto');
        }

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // Número correlativo
            if ($ajuste->NumeroCorrelativo == 0) {
                $maxCorrelativo = AjusteInventario::porContexto()->max('NumeroCorrelativo');
                $numeroCorrelativo = ($maxCorrelativo ?? 0) + 1;
            } else {
                $numeroCorrelativo = $ajuste->NumeroCorrelativo;
            }

            $dH = $ajuste->ConceptoOperacion == 'Ingreso' ? 'D' : 'H';
            $idDiario = $ajuste->IdDiario;

            // ========== ELIMINAR MOVIMIENTOS DE INVENTARIO ANTIGUOS ==========
            $eliminadosInv = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->where('IdDocumento', $ajuste->IdAjustesPrincipal)
                ->delete();
            
            Log::info("Movimientos eliminados de inventario_propiamente: {$eliminadosInv}");

            // ========== ELIMINAR ASIENTOS CONTABLES ANTIGUOS (si existe diario) ==========
            if ($idDiario && $idDiario > 0) {
                $eliminadosAsientos = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->where('IdDiario', $idDiario)
                    ->delete();
                
                Log::info("Asientos contables eliminados: {$eliminadosAsientos}");
            }

            // ========== CREAR O REUTILIZAR DIARIO ==========
            if ($idDiario && $idDiario > 0) {
                // Reutilizar diario existente
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->where('IdDiario', $idDiario)
                    ->update([
                        'IdFecha' => $ajuste->IdFecha,
                        'IdOperadorEdita' => $operadorId,
                        'FechaEdita' => now(),
                    ]);
            } else {
                // Crear nuevo diario
                $maxNumeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->max('NumeroDiario');
                $numeroDiario = ($maxNumeroDiario ?? 0) + 1;

                $idDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->insertGetId([
                        'IdFecha' => $ajuste->IdFecha,
                        'IdTipoDiario' => 7,
                        'NumeroDiario' => $numeroDiario,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                        'Contabilizado' => 1,
                        'IdOperadorIngreso' => $operadorId,
                        'FechaIngreso' => now(),
                        'IdoperadorEdita' => $operadorId,
                        'FechaEdita' => now(),
                    ]);
            }

            // ========== INSERTAR NUEVOS MOVIMIENTOS DE INVENTARIO ==========
            foreach ($ajuste->detalles as $detalle) {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->insert([
                        'IdTipoDeOperacion' => $ajuste->IdTipoOperacion,
                        'IdDocumento' => $ajuste->IdAjustesPrincipal,
                        'IdFecha' => $ajuste->IdFecha,
                        'IdAlmacen' => $ajuste->IdAlmacen,
                        'IdProducto' => $detalle->IdProducto,
                        'Glosa' => "Nota de Ajuste No {$numeroCorrelativo}; {$ajuste->tipoOperacion->Detalle}",
                        'D_H' => $dH,
                        'Unidades' => $detalle->Unidades,
                        'Bolivianos' => $detalle->Bolivianos,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                    ]);
            }
            
            Log::info("Nuevos movimientos insertados: " . $ajuste->detalles->count());

            // ========== INSERTAR ASIENTOS CONTABLES ==========
            $totalMonto = $ajuste->detalles->sum('Bolivianos');
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $idDiario,
                    'IdCuenta' => $dH == 'D' ? 1 : 2,
                    'Glosa' => "Ajuste de Inventario No {$numeroCorrelativo}",
                    'D_H' => $dH == 'D' ? 'D' : 'H',
                    'MontoBolivianos' => $totalMonto,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $totalMonto,
                    'IdIdentificador' => $ajuste->IdRealizadoPor,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $idDiario,
                    'IdCuenta' => $dH == 'D' ? 2 : 1,
                    'Glosa' => "Ajuste de Inventario No {$numeroCorrelativo}",
                    'D_H' => $dH == 'D' ? 'H' : 'D',
                    'MontoBolivianos' => $totalMonto,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $totalMonto,
                    'IdIdentificador' => $ajuste->IdAutorizadoPor,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Actualizar ajuste
            $ajuste->update([
                'NumeroCorrelativo' => $numeroCorrelativo,
                'IdDiario' => $idDiario,
                'ActivoInactivo' => 1,
                'IdOperadorEdita' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('Ajuste contabilizado correctamente', [
                'IdAjuste' => $ajuste->IdAjustesPrincipal,
                'Numero' => $numeroCorrelativo,
                'IdDiario' => $idDiario
            ]);

            return redirect()->route('ajustes-inventario.pdf', $ajuste->IdAjustesPrincipal);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al contabilizar ajuste: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Error al contabilizar: ' . $e->getMessage());
        }
    }

    /**
     * Calcular total de un ajuste
     */
    private function calcularTotal($detalles)
    {
        $total = 0;
        foreach ($detalles as $detalle) {
            $total += $detalle->Bolivianos;
        }
        return $total;
    }

    /**
     * Mostrar ajuste contabilizado
     */
    public function show($id)
    {
        $ajuste = AjusteInventario::porContexto()
            ->with(['detalles.producto', 'fecha', 'tipoOperacion', 'almacen', 'realizadoPor', 'autorizadoPor'])
            ->findOrFail($id);

        // ✅ Agregar fecha formateada
        $fechaMostrar = '';
        if ($ajuste->fecha && $ajuste->fecha->Fecha) {
            $fechaMostrar = date('d/m/Y', strtotime($ajuste->fecha->Fecha));
        } else {
            $fechaMostrar = $ajuste->FechaIngreso ? date('d/m/Y', strtotime($ajuste->FechaIngreso)) : '';
        }
        $ajuste->fecha_mostrar = $fechaMostrar;

        return Inertia::render('Gestion/Inventario/AjusteInventario/Show', [
            'ajuste' => $ajuste,
        ]);
    }

    /**
     * Generar PDF
     */
    public function pdf($id)
    {
        $ajuste = AjusteInventario::with([
                'detalles.producto', 
                'fecha', 
                'tipoOperacion', 
                'almacen', 
                'realizadoPor', 
                'autorizadoPor'
            ])
            ->findOrFail($id);

        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 20, 60);
        $pdf->AddPage();
        $pdf->SetFont('courier', '', 8);

        // Logo
        $logoPath = public_path('images/logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 15, 10, 70, 20);
        }

        // Número de ajuste
        $pdf->SetXY(180, 25);
        $pdf->SetFont('courier', 'B', 16);
        $pdf->Cell(18, 3, 'No ' . $ajuste->NumeroCorrelativo, 0, 1, 'L');

        // Título
        $y = $pdf->GetY();
        $pdf->SetXY(5, $y + 2);
        $pdf->SetFont('courier', 'B', 20);
        $pdf->Cell(200, 5, 'NOTA DE AJUSTES', 0, 1, 'C');
        
        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 12);
        $pdf->SetXY(5, $y + 1);
        $pdf->Cell(200, 3, '(Expresado en Unidades)', 0, 1, 'C');

        // ✅ Fecha correcta
        $fechaFormateada = '';
        if ($ajuste->fecha && $ajuste->fecha->Fecha) {
            $fechaFormateada = date('d/m/Y', strtotime($ajuste->fecha->Fecha));
        } else {
            $fechaFormateada = date('d/m/Y', strtotime($ajuste->FechaIngreso));
        }
        $y = $pdf->GetY();
        $pdf->SetFont('courier', 'B', 10);
        $pdf->SetXY(5, $y + 1);
        $pdf->Cell(200, 3, $fechaFormateada, 0, 1, 'C');

        // Datos
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 4);
        $pdf->Cell(200, 3, 'Realizado por       : ' . ($ajuste->realizadoPor->CI_NIT ?? '') . ' - ' . ($ajuste->realizadoPor->Nombre ?? ''), 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 2);
        $pdf->Cell(200, 3, 'Autorizado por      : ' . ($ajuste->autorizadoPor->CI_NIT ?? '') . ' - ' . ($ajuste->autorizadoPor->Nombre ?? ''), 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 2);
        $pdf->Cell(200, 3, 'Dinamica            : ' . $ajuste->ConceptoOperacion, 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 2);
        $pdf->Cell(200, 3, 'Tipo Operacion      : ' . ($ajuste->tipoOperacion->Detalle ?? ''), 0, 1, 'L');
        
        $y = $pdf->GetY();
        $pdf->SetXY(15, $y + 2);
        $pdf->MultiCell(185, 4, 'Explicacion - Motivo: ' . ($ajuste->Explicacion ?? ''), 0, 'L', false);

        // Tabla
        $y = $pdf->GetY();
        $pdf->Line(15, $y + 4, 200, $y + 4);
        
        $pdf->SetFont('courier', 'B', 10);
        $pdf->SetXY(14, $y + 5);
        $pdf->Cell(10, 3, 'No', 0, 1, 'C');
        $pdf->SetXY(38, $y + 5);
        $pdf->Cell(10, 3, 'Codigo', 0, 1, 'C');
        $pdf->SetXY(100, $y + 5);
        $pdf->Cell(10, 3, 'Descripcion', 0, 1, 'C');
        $pdf->SetXY(164, $y + 5);
        $pdf->Cell(10, 3, 'Unidad', 0, 1, 'C');
        $pdf->SetXY(186, $y + 5);
        $pdf->Cell(10, 3, 'Cantidad', 0, 1, 'C');

        $y = $pdf->GetY();
        $pdf->Line(15, $y + 2, 200, $y + 2);

        $y = $y + 6;
        $contador = 0;
        
        foreach ($ajuste->detalles as $detalle) {
            $contador++;
            $producto = $detalle->producto;
            
            $pdf->SetFont('courier', '', 10);
            $pdf->SetXY(14, $y);
            $pdf->Cell(10, 4, $contador . '.-', 0, 1, 'R');
            
            $pdf->SetXY(26, $y);
            $pdf->MultiCell(50, 4, $producto->Codigo ?? '', 0, 'L', false);
            
            $pdf->SetXY(82, $y);
            $pdf->MultiCell(75, 4, $producto->Descripcion ?? '', 0, 'L', false);
            
            // Unidad de medida
            $unidadMedida = '';
            if ($producto->unidadMedida) {
                $unidadMedida = $producto->unidadMedida->UnidadMedida ?? '';
            }
            $pdf->SetXY(161, $y);
            $pdf->Cell(10, 4, $unidadMedida, 0, 1, 'L');
            
            $pdf->SetXY(190, $y);
            $pdf->Cell(10, 4, number_format($detalle->Unidades, 2, ',', '.'), 0, 1, 'R');
            
            $y = $y + 6;
        }

        $pdf->Line(15, $y, 200, $y);

        // Firmas
        $y = $y + 30;
        $pdf->SetFont('courier', 'B', 10);
        
        $pdf->SetXY(15, $y);
        $pdf->MultiCell(80, 3, '------------------------------------', 0, 'L');
        $pdf->SetXY(15, $y + 2);
        $pdf->MultiCell(80, 3, '           REALIZADO POR', 0, 'L');
        $pdf->SetXY(15, $y + 6);
        $pdf->MultiCell(80, 3, 'Nombre : _________________', 0, 'L');
        $pdf->SetXY(15, $y + 12);
        $pdf->MultiCell(80, 3, 'CI     : _________________', 0, 'L');

        $pdf->SetXY(123, $y);
        $pdf->MultiCell(80, 3, '------------------------------------', 0, 'L');
        $pdf->SetXY(123, $y + 2);
        $pdf->MultiCell(80, 3, '           AUTORIZADO POR', 0, 'L');
        $pdf->SetXY(123, $y + 6);
        $pdf->MultiCell(80, 3, 'Nombre : _________________', 0, 'L');
        $pdf->SetXY(123, $y + 12);
        $pdf->MultiCell(80, 3, 'CI     : _________________', 0, 'L');

        $pdf->Output('ajuste_' . $ajuste->NumeroCorrelativo . '.pdf', 'I');
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
     * Crear un nuevo ajuste (primera vez que se guarda cabecera)
     */
    public function crearAjuste(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $ajuste = AjusteInventario::create([
            'IdFecha' => 0,
            'ConceptoOperacion' => '',
            'IdTipoOperacion' => 0,
            'NumeroCorrelativo' => 0,
            'IdAlmacen' => 0,
            'IdRealizadoPor' => 0,
            'IdAutorizadoPor' => 0,
            'Explicacion' => '',
            'IdCliente' => $clienteId,
            'IdSucursal' => $sucursalId,
            'IdOperadorIngresa' => $operadorId,
            'FechaIngreso' => now(),
            'IdOperadorEdita' => $operadorId,
            'FechaActualiza' => now(),
            'ActivoInactivo' => 0,
        ]);

        return response()->json([
            'success' => true,
            'ajuste' => $ajuste,
            'message' => 'Ajuste creado correctamente'
        ]);
    }

    /**
     * Vista para gestión de estados (Activar/Inactivar ajustes) - CON SUCURSALES
     */
    public function gestionEstado(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        \Log::info('=== GESTION ESTADO AJUSTES ===');
        \Log::info('Cliente ID: ' . $clienteId);
        \Log::info('Sucursal actual: ' . $sucursalId);
        \Log::info('Sucursal seleccionada en filtro: ' . $request->sucursal_id);
        
        // OBTENER TODAS LAS SUCURSALES DEL CLIENTE
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // CONSULTA PRINCIPAL
        $query = AjusteInventario::where('IdCliente', $clienteId)
            ->with(['tipoOperacion', 'almacen', 'fecha']); // ✅ Agregar 'fecha'
        
        // FILTRO POR SUCURSAL
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('IdSucursal', $request->sucursal_id);
        } else {
            $query->where('IdSucursal', $sucursalId);
        }
        
        // FILTRO POR ESTADO
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
            }
        }
        
        // BUSCADOR
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('NumeroCorrelativo', 'LIKE', "%{$buscar}%");
        }
        
        $ajustes = $query->orderBy('IdAjustesPrincipal', 'desc')->paginate(20);
        
        // Enriquecer datos
        $ajustes->getCollection()->transform(function ($ajuste) {
            // ✅ Fecha formateada
            $fechaMostrar = '';
            if ($ajuste->fecha && $ajuste->fecha->Fecha) {
                $fechaMostrar = date('d/m/Y', strtotime($ajuste->fecha->Fecha));
            } else {
                $fechaMostrar = $ajuste->FechaIngreso ? date('d/m/Y', strtotime($ajuste->FechaIngreso)) : '';
            }
            $ajuste->fecha_formateada = $fechaMostrar;
            
            // Agregar nombre de sucursal
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $ajuste->IdSucursal)
                ->first();
            
            $ajuste->sucursal_nombre = $sucursal ? $sucursal->Nombre : 'Sin sucursal';
            $ajuste->sucursal_numero = $sucursal ? $sucursal->NumeroSucursal : null;
            
            return $ajuste;
        });
        
        return Inertia::render('Gestion/Inventario/AjusteInventario/GestionEstado', [
            'ajustes' => $ajustes,
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
            $ajuste = AjusteInventario::where('IdCliente', session('cliente_id'))
                ->where('IdAjustesPrincipal', $id)
                ->firstOrFail();
            
            if ($ajuste->ActivoInactivo == 1) {
                $ajuste->update(['ActivoInactivo' => 0]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Ajuste desactivado correctamente (pasó a Borrador)',
                    'nuevo_estado' => 0
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Este ajuste ya está en estado BORRADOR. Solo se activa al editarlo y guardarlo.'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de ajuste: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Editar ajuste (borrador)
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $ajuste = AjusteInventario::porContexto()
            ->where('ActivoInactivo', 0)
            ->findOrFail($id);

        $detalles = AjusteInventarioDetalle::where('IdAjustesPrincipal', $id)
            ->with('producto')
            ->get();

        $fechas = $this->getFechasDisponibles();
        
        $tiposOperacion = TipoOperacion::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdTipoOperacion as id', 'Detalle as nombre', 'Concepto']);

        $almacenes = Almacen::porContexto()
            ->orderBy('Almacen')
            ->get(['IdAlmacen as id', 'Almacen as nombre']);

        $personas = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        $productos = ProductoDetalle::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Codigo')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);

        return Inertia::render('Gestion/Inventario/AjusteInventario/Create', [
            'ajuste' => $ajuste,
            'detalles' => $detalles,
            'fechas' => $fechas,
            'tiposOperacion' => $tiposOperacion,
            'almacenes' => $almacenes,
            'personas' => $personas,
            'productos' => $productos,
        ]);
    }

    /**
     * Actualizar detalle del ajuste
     */
    public function actualizarDetalle(Request $request, $id)
    {
        $request->validate([
            'Unidades' => 'required|numeric|min:0.01',
            'Bolivianos' => 'required|numeric|min:0',
        ]);

        $detalle = AjusteInventarioDetalle::findOrFail($id);
        $ajuste = AjusteInventario::findOrFail($detalle->IdAjustesPrincipal);

        if ($ajuste->ActivoInactivo != 0) {
            return response()->json(['success' => false, 'message' => 'El ajuste ya fue contabilizado'], 400);
        }

        $detalle->update([
            'Unidades' => $request->Unidades,
            'Bolivianos' => $request->Bolivianos,
        ]);

        $detalle->load('producto');

        return response()->json([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }

}