<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ContenedorController extends Controller
{
    /**
     * Lista de contenedores
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        $sucursalFiltro = $request->get('sucursal_id', $sucursalId);
        
        $query = Contenedor::porCliente()
            ->with(['tipoContenedor', 'sucursal']);
        
        if ($sucursalFiltro) {
            $query->where('IdSucursal', $sucursalFiltro);
        }
        
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->activos();
            } elseif ($request->estado === 'borradores') {
                $query->borradores();
            }
        }
        
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function($q) use ($buscar) {
                $q->where('Codigo', 'LIKE', "%{$buscar}%");
            });
        }
        
        // ✅ ORDENAMIENTO NATURAL: alfabético por prefijo + numérico por sufijo
        $contenedores = $query
            ->orderByRaw("
                LOWER(SUBSTRING_INDEX(Codigo, '-', 1)) ASC,
                CAST(SUBSTRING_INDEX(Codigo, '-', -1) AS UNSIGNED) ASC
            ")
            ->paginate(20)
            ->appends($request->all());
        
        $contenedores->getCollection()->transform(function($contenedor) {
            return [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'IdTipoContenedor' => $contenedor->IdTipoContenedor,
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'ActivoInactivo' => $contenedor->ActivoInactivo,
                'EstadoTexto' => $contenedor->EstadoTexto,
                'EstadoColor' => $contenedor->EstadoColor,
                'IdSucursal' => $contenedor->IdSucursal,
                'sucursal' => $contenedor->sucursal ? [
                    'Nombre' => $contenedor->sucursal->Nombre,
                    'NumeroSucursal' => $contenedor->sucursal->NumeroSucursal,
                ] : null,
            ];
        });

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Index', [
            'contenedores' => $contenedores,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalFiltro,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
        ]);
    }

    /**
     * Vista de gestión de estados
     */
    public function gestionEstado(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        $query = Contenedor::porCliente()
            ->with(['tipoContenedor', 'sucursal']);
        
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('IdSucursal', $request->sucursal_id);
        } else {
            $query->where('IdSucursal', $sucursalId);
        }
        
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->activos();
            } elseif ($request->estado === 'borradores') {
                $query->borradores();
            }
        }
        
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function($q) use ($buscar) {
                $q->where('Codigo', 'LIKE', "%{$buscar}%");
            });
        }
        
        $contenedores = $query->orderBy('IdContenedor', 'desc')->paginate(20);
        $contenedores->appends($request->all());
        
        $contenedores->getCollection()->transform(function($contenedor) {
            return [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'ActivoInactivo' => $contenedor->ActivoInactivo,
                'EstadoTexto' => $contenedor->EstadoTexto,
                'EstadoColor' => $contenedor->EstadoColor,
                'IdSucursal' => $contenedor->IdSucursal,
                'sucursal' => $contenedor->sucursal ? [
                    'Nombre' => $contenedor->sucursal->Nombre,
                    'NumeroSucursal' => $contenedor->sucursal->NumeroSucursal,
                ] : null,
            ];
        });
        
        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/GestionEstado', [
            'contenedores' => $contenedores,
            'sucursales' => $sucursales,
            'sucursalActual' => $sucursalId,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
            'sucursalSeleccionada' => $request->sucursal_id,
        ]);
    }

    /**
     * PASO 1: Mostrar formulario de creación
     */
    public function create()
    {
        // Verificar si existe un borrador para este operador
        $borrador = Contenedor::borradorPorOperador()->first();
        
        if ($borrador) {
            return redirect()->route('operacion.pedidos.clientes-mayoristas.contenedores.edit', $borrador->IdContenedor)
                ->with('info', 'Continuando con el borrador existente.');
        }

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');   // ✅

        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        $tiposContenedor = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->orderBy('Nombre')
            ->get(['IdTipoContenedor as id', 'Nombre as nombre']);

        // ✅ Obtener nombre de la sucursal actual
        $sucursalActual = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre', 'NumeroSucursal']);

        $sucursalActualNombre = $sucursalActual 
            ? $sucursalActual->Nombre . ($sucursalActual->NumeroSucursal ? ' (N° ' . $sucursalActual->NumeroSucursal . ')' : '')
            : 'Sucursal Actual';

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Create', [
            'contenedor' => null,
            'sucursales' => $sucursales,
            'tiposContenedor' => $tiposContenedor,
            'sucursalActual' => $sucursalId,               // ✅
            'sucursalActualNombre' => $sucursalActualNombre, // ✅
        ]);
    }

    /**
     * PASO 1: Guardar cabecera del contenedor (BORRADOR)
     */
    public function store(Request $request)
    {
        $request->validate([
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'IdTipoContenedor' => 'required|exists:operacion_pedidos_clientes_contenedor_tipo,IdTipoContenedor',
            'CapacidadTotal' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $tipo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdTipoContenedor', $request->IdTipoContenedor)
            ->value('Nombre');

        $codigo = strtoupper($tipo) . '-' . intval($request->CapacidadTotal);

        $borradorExistente = Contenedor::borradorPorOperador()->first();

        if ($borradorExistente) {
            $borradorExistente->update([
                'IdSucursal' => $request->IdSucursal,
                'IdTipoContenedor' => $request->IdTipoContenedor,
                'CapacidadTotal' => $request->CapacidadTotal,
                'Codigo' => $codigo,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            return response()->json([
                'success' => true,
                'contenedor' => $borradorExistente,
                'message' => 'Borrador actualizado correctamente'
            ]);
        }

        // Verificar que no exista otro con el mismo código
        $existe = Contenedor::where('IdCliente', $clienteId)
            ->where('Codigo', $codigo)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un contenedor con el código ' . $codigo
            ], 422);
        }

        DB::beginTransaction();

        try {
            $contenedor = Contenedor::create([
                'IdTipoContenedor' => $request->IdTipoContenedor,
                'Codigo' => $codigo,
                'CapacidadTotal' => $request->CapacidadTotal,
                'ActivoInactivo' => 0,
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => Carbon::now('America/La_Paz'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'contenedor' => $contenedor,
                'message' => 'Contenedor creado como borrador.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear contenedor: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear contenedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PASO 2: Mostrar formulario de edición
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');   // ✅
        
        $contenedor = Contenedor::porCliente($clienteId)
            ->where('IdContenedor', $id)
            ->with(['tipoContenedor', 'sucursal'])
            ->firstOrFail();

        // ✅ Si está activo, lo pasamos automáticamente a BORRADOR
        if ($contenedor->ActivoInactivo == 1) {
            $contenedor->update([
                'ActivoInactivo' => 0,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);
        }

        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        $tiposContenedor = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->orderBy('Nombre')
            ->get(['IdTipoContenedor as id', 'Nombre as nombre']);

        // ✅ Obtener nombre de la sucursal actual
        $sucursalActual = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first(['Nombre', 'NumeroSucursal']);

        $sucursalActualNombre = $sucursalActual 
            ? $sucursalActual->Nombre . ($sucursalActual->NumeroSucursal ? ' (N° ' . $sucursalActual->NumeroSucursal . ')' : '')
            : 'Sucursal Actual';

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Create', [
            'contenedor' => $contenedor,
            'sucursales' => $sucursales,
            'tiposContenedor' => $tiposContenedor,
            'sucursalActual' => $sucursalId,               // ✅
            'sucursalActualNombre' => $sucursalActualNombre, // ✅
        ]);
    }

    /**
     * Actualizar cabecera del contenedor
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'IdTipoContenedor' => 'required|exists:operacion_pedidos_clientes_contenedor_tipo,IdTipoContenedor',
            'CapacidadTotal' => 'required|numeric|min:0.01',
        ]);

        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $id)
            ->firstOrFail();

        if ($contenedor->ActivoInactivo == 1) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar un contenedor activo'
            ], 400);
        }

        $tipo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdTipoContenedor', $request->IdTipoContenedor)
            ->value('Nombre');

        $codigo = strtoupper($tipo) . '-' . intval($request->CapacidadTotal);

        $existe = Contenedor::where('IdCliente', session('cliente_id'))
            ->where('IdContenedor', '!=', $id)
            ->where('Codigo', $codigo)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un contenedor con el código ' . $codigo
            ], 422);
        }

        $contenedor->update([
            'IdTipoContenedor' => $request->IdTipoContenedor,
            'IdSucursal' => $request->IdSucursal,
            'Codigo' => $codigo,
            'CapacidadTotal' => $request->CapacidadTotal,
            'IdOperadorActualiza' => session('operador_id'),
            'FechaActualiza' => Carbon::now('America/La_Paz'),
        ]);

        return response()->json([
            'success' => true,
            'contenedor' => $contenedor,
            'message' => 'Contenedor actualizado correctamente'
        ]);
    }

    /**
     * PASO 3: Finalizar contenedor (cambiar estado a ACTIVO)
     */
    public function finalizar($id)
    {
        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $id)
            ->firstOrFail();

        if ($contenedor->ActivoInactivo == 1) {
            return response()->json([
                'success' => false,
                'message' => 'El contenedor ya está activo'
            ], 400);
        }

        $contenedor->update([
            'ActivoInactivo' => 1,
            'IdOperadorActualiza' => session('operador_id'),
            'FechaActualiza' => Carbon::now('America/La_Paz'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contenedor activado correctamente',
        ]);
    }

    /**
     * CAMBIAR ESTADO (Activo ↔ Inactivo)
     */
    public function cambiarEstado($id)
    {
        try {
            $contenedor = Contenedor::porCliente()
                ->where('IdContenedor', $id)
                ->firstOrFail();
            
            if ($contenedor->ActivoInactivo == 1) {
                // Desactivar (Activo → Borrador)
                $contenedor->update([
                    'ActivoInactivo' => 0,
                    'IdOperadorActualiza' => session('operador_id'),
                    'FechaActualiza' => Carbon::now('America/La_Paz'),
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Contenedor desactivado correctamente (pasó a Borrador)',
                    'nuevo_estado' => 0
                ]);
                
            } else {
                // Activar (Borrador → Activo)
                $contenedor->update([
                    'ActivoInactivo' => 1,
                    'IdOperadorActualiza' => session('operador_id'),
                    'FechaActualiza' => Carbon::now('America/La_Paz'),
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Contenedor activado correctamente',
                    'nuevo_estado' => 1,
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de contenedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle estado (mantener compatibilidad)
     */
    public function toggleEstado($id)
    {
        return $this->cambiarEstado($id);
    }

    /**
     * Ver detalle del contenedor
     */
    public function show($id)
    {
        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $id)
            ->with(['tipoContenedor', 'sucursal', 'cliente'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'ActivoInactivo' => $contenedor->ActivoInactivo,
                'EstadoTexto' => $contenedor->EstadoTexto,
                'Sucursal' => $contenedor->sucursal ? $contenedor->sucursal->Nombre : '-',
                'detalles' => [],
            ]
        ]);
    }

    /**
     * Eliminar contenedor (solo si está inactivo/borrador)
     */
    public function destroy($id)
    {
        try {
            $contenedor = Contenedor::porCliente()
                ->where('IdContenedor', $id)
                ->firstOrFail();

            if ($contenedor->ActivoInactivo == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un contenedor activo'
                ], 400);
            }

            $contenedor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contenedor eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar contenedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ EXPORTAR PDF: Contenedores con su cabecera
     */
    public function exportarPdf(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $contenedores = $this->obtenerContenedoresParaReporte($request, $clienteId, $sucursalId);

        if ($contenedores->isEmpty()) {
            return redirect()->back()->with('error', 'No hay contenedores para exportar.');
        }

        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT']);

        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre as nombre']);

        $fechaImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i');

        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        $y = 8;

        // ============ HEADER EMPRESA ============
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 5.5, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'), 0, 1, 'C');
        $y += 5.5;

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        if (!empty($empresa->NIT)) {
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 3.5, 'NIT: ' . $empresa->NIT, 0, 1, 'C');
            $y += 3.5;
        }

        $y += 1;
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(10, $y, 206, $y);
        $y += 4;

        // ============ TÍTULO ============
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 6, 'REPORTE DE CONTENEDORES', 0, 1, 'C');
        $y += 6;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 3.5, 'Fecha de impresión: ' . $fechaImpresion . '  ·  Generado por: ' . ($operador->nombre ?? '-'), 0, 1, 'C');
        $y += 5;

        // ============ CONTADORES ============
        $totalContenedores = $contenedores->count();

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(240, 245, 255);
        $pdf->SetTextColor(30, 60, 120);
        $textoContadores = 'Total Contenedores: ' . $totalContenedores;
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 5, $textoContadores, 1, 1, 'C', 1);
        $y += 7;

        // ============ RECORRER CONTENEDORES ============
        foreach ($contenedores as $contenedor) {
            if ($y > 250) {
                $pdf->AddPage();
                $y = 15;
            }

            // ============ HEADER DEL CONTENEDOR ============
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(230, 240, 255);
            $pdf->SetTextColor(20, 50, 110);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 5.5, '  ' . $contenedor->Codigo, 'LTRB', 1, 'L', 1);
            $y += 5.5;

            // Info del contenedor
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetXY(10, $y);
            $estado = $contenedor->ActivoInactivo == 1 ? 'Activo' : 'Borrador';
            $tipo = $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-';
            $info = '  Tipo: ' . $tipo
                . ' · Capacidad: ' . number_format($contenedor->CapacidadTotal, 2, ',', '.') . ' und'
                . ' · Estado: ' . $estado;
            $pdf->Cell(196, 4.5, $info, 'LRB', 1, 'L', 1);
            $y += 7;
        }

        $nombreArchivo = 'Contenedores_' . Carbon::now('America/La_Paz')->format('Y-m-d') . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit;
    }

    /**
     * ✅ HELPER PRIVADO: Obtener contenedores aplicando los mismos filtros del index
     */
    private function obtenerContenedoresParaReporte(Request $request, $clienteId, $sucursalId)
    {
        $sucursalFiltro = $request->get('sucursal_id', $sucursalId);

        $query = Contenedor::porCliente()
            ->with(['tipoContenedor', 'sucursal']);

        if ($sucursalFiltro) {
            $query->where('IdSucursal', $sucursalFiltro);
        }

        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->activos();
            } elseif ($request->estado === 'borradores') {
                $query->borradores();
            }
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('Codigo', 'LIKE', "%{$buscar}%");
            });
        }

        return $query
            ->orderByRaw("
                LOWER(SUBSTRING_INDEX(Codigo, '-', 1)) ASC,
                CAST(SUBSTRING_INDEX(Codigo, '-', -1) AS UNSIGNED) ASC
            ")
            ->get();
    }

    /**
     * ✅ LISTA DE CONTENEDORES - VERSIÓN SUPERVISOR (sin botón "Nuevo")
     */
    public function indexSupervisor(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        $sucursalFiltro = $request->get('sucursal_id', $sucursalId);
        
        $query = Contenedor::porCliente()
            ->with(['tipoContenedor', 'sucursal']);
        
        if ($sucursalFiltro) {
            $query->where('IdSucursal', $sucursalFiltro);
        }
        
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->activos();
            } elseif ($request->estado === 'borradores') {
                $query->borradores();
            }
        }
        
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where(function($q) use ($buscar) {
                $q->where('Codigo', 'LIKE', "%{$buscar}%");
            });
        }
        
        $contenedores = $query
            ->orderByRaw("
                LOWER(SUBSTRING_INDEX(Codigo, '-', 1)) ASC,
                CAST(SUBSTRING_INDEX(Codigo, '-', -1) AS UNSIGNED) ASC
            ")
            ->paginate(20)
            ->appends($request->all());
        
        $contenedores->getCollection()->transform(function($contenedor) {
            return [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'IdTipoContenedor' => $contenedor->IdTipoContenedor,
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'ActivoInactivo' => $contenedor->ActivoInactivo,
                'EstadoTexto' => $contenedor->EstadoTexto,
                'EstadoColor' => $contenedor->EstadoColor,
                'IdSucursal' => $contenedor->IdSucursal,
                'sucursal' => $contenedor->sucursal ? [
                    'Nombre' => $contenedor->sucursal->Nombre,
                    'NumeroSucursal' => $contenedor->sucursal->NumeroSucursal,
                ] : null,
            ];
        });

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/AdministrarClientes', [
            'contenedores' => $contenedores,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalFiltro,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
        ]);
    }
}