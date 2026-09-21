<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ContenedorGrupo;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;
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
            ->with(['tipoContenedor', 'gruposAnalisis', 'sucursal']);
        
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
        // Ejemplo: TERMO-20, TERMO-30, TERMO-80, TERMO-100
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
                'GruposAnalisis' => $contenedor->gruposNombres,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'TotalProductos' => $contenedor->totalProductos,
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
            ->with(['tipoContenedor', 'gruposAnalisis', 'sucursal']);
        
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
                'GruposAnalisis' => $contenedor->gruposNombres,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'TotalProductos' => $contenedor->totalProductos,
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

        $gruposAnalisis = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
            ->orderBy('Grupo')
            ->get(['IdGrupoAnalisis as id', 'Grupo as nombre']);

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Create', [
            'contenedor' => null,
            'sucursales' => $sucursales,
            'tiposContenedor' => $tiposContenedor,
            'gruposAnalisis' => $gruposAnalisis,
            'gruposSeleccionados' => [],
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

        // Obtener el nombre del tipo para generar el código
        $tipo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdTipoContenedor', $request->IdTipoContenedor)
            ->value('Nombre');

        // Generar código automáticamente
        $codigo = strtoupper($tipo) . '-' . intval($request->CapacidadTotal);

        // Verificar si ya existe un borrador para este operador
        $borradorExistente = Contenedor::borradorPorOperador()->first();

        if ($borradorExistente) {
            // Actualizar borrador existente
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
     * ✅ MODIFICADO: 
     *  - Ya NO filtra por IdOperadorInserta (cualquier operador puede editar)
     *  - Si el contenedor está ACTIVO, lo pasa automáticamente a BORRADOR
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        
        $contenedor = Contenedor::porCliente($clienteId)
            ->where('IdContenedor', $id)
            ->with(['tipoContenedor', 'gruposAnalisis', 'sucursal'])
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

        $gruposAnalisis = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
            ->orderBy('Grupo')
            ->get(['IdGrupoAnalisis as id', 'Grupo as nombre']);

        $gruposSeleccionados = $contenedor->gruposAnalisis->pluck('IdGrupoAnalisis')->toArray();

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Create', [
            'contenedor' => $contenedor,
            'sucursales' => $sucursales,
            'tiposContenedor' => $tiposContenedor,
            'gruposAnalisis' => $gruposAnalisis,
            'gruposSeleccionados' => $gruposSeleccionados,
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

        // Obtener el nombre del tipo
        $tipo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_tipo')
            ->where('IdTipoContenedor', $request->IdTipoContenedor)
            ->value('Nombre');

        // Regenerar código
        $codigo = strtoupper($tipo) . '-' . intval($request->CapacidadTotal);

        // Verificar que no exista otro con el mismo código
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
     * ✅ ASIGNAR GRUPOS DE ANÁLISIS AL CONTENEDOR
     * 🔥 CORREGIDO: Ya no requiere 'grupos' como campo obligatorio
     */
    public function asignarGrupos(Request $request, $id)
    {
        // ✅ ELIMINAR 'required' del campo grupos
        $request->validate([
            'grupos' => 'array',  // Puede ser un array vacío
            'grupos.*' => 'exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
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

        // Sincronizar grupos (si el array está vacío, elimina todos)
        $contenedor->gruposAnalisis()->sync($request->grupos ?? []);

        // Contar productos activos
        $totalProductos = $contenedor->contarProductosActivos();

        return response()->json([
            'success' => true,
            'message' => $totalProductos > 0 
                ? 'Grupos actualizados correctamente' 
                : 'Todos los grupos fueron eliminados',
            'total_productos' => $totalProductos
        ]);
    }

    /**
     * ✅ OBTENER PRODUCTOS DE UN CONTENEDOR
     */
    public function getProductos($id)
    {
        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $id)
            ->with(['gruposAnalisis', 'tipoContenedor'])
            ->firstOrFail();

        if ($contenedor->ActivoInactivo != 1) {
            return response()->json([
                'success' => false,
                'message' => 'El contenedor no está activo'
            ], 400);
        }

        $productos = $contenedor->productos;

        return response()->json([
            'success' => true,
            'contenedor' => [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'Tipo' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'Grupos' => $contenedor->gruposNombres,
            ],
            'productos' => $productos->map(function($producto) {
                return [
                    'IdProducto' => $producto->IdProducto,
                    'Codigo' => $producto->Codigo,
                    'Descripcion' => $producto->Descripcion,
                    'Precio' => $producto->Precio,
                    'IdGrupoAnalisis' => $producto->IdGrupoAnalisis,
                    'GrupoAnalisis' => $producto->grupoAnalisis ? $producto->grupoAnalisis->Grupo : '-',
                ];
            }),
        ]);
    }

    /**
     * PASO 3: Finalizar contenedor (cambiar estado a ACTIVO)
     * 🔥 CORREGIDO: Ya no valida si los grupos tienen productos activos
     */
    public function finalizar($id)
    {
        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $id)
            ->with(['gruposAnalisis'])
            ->firstOrFail();

        if ($contenedor->ActivoInactivo == 1) {
            return response()->json([
                'success' => false,
                'message' => 'El contenedor ya está activo'
            ], 400);
        }

        // ✅ SOLO verificar que tenga grupos asignados (no importa si tienen productos)
        if ($contenedor->gruposAnalisis->count() == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Asigne al menos un grupo de análisis al contenedor'
            ], 400);
        }

        // ✅ ACTIVAR sin validar productos activos
        $contenedor->update([
            'ActivoInactivo' => 1,
            'IdOperadorActualiza' => session('operador_id'),
            'FechaActualiza' => Carbon::now('America/La_Paz'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contenedor activado correctamente',
            'total_productos' => $contenedor->contarProductosActivos() // Solo informativo
        ]);
    }

    /**
     * CAMBIAR ESTADO (Activo ↔ Inactivo)
     * 🔥 CORREGIDO: Ya no valida si los grupos tienen productos activos
     */
    public function cambiarEstado($id)
    {
        try {
            $contenedor = Contenedor::porCliente()
                ->where('IdContenedor', $id)
                ->with(['gruposAnalisis'])
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
                // ✅ SOLO verificar que tenga grupos asignados
                if ($contenedor->gruposAnalisis->count() == 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El contenedor no tiene grupos asignados. Asigne grupos primero.'
                    ], 400);
                }
                
                // ✅ ACTIVAR sin validar productos activos
                $contenedor->update([
                    'ActivoInactivo' => 1,
                    'IdOperadorActualiza' => session('operador_id'),
                    'FechaActualiza' => Carbon::now('America/La_Paz'),
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Contenedor activado correctamente',
                    'nuevo_estado' => 1,
                    'total_productos' => $contenedor->contarProductosActivos() // Solo informativo
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
            ->with(['tipoContenedor', 'gruposAnalisis', 'sucursal', 'cliente'])
            ->firstOrFail();

        $productos = $contenedor->productos;

        return response()->json([
            'success' => true,
            'data' => [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'GruposAnalisis' => $contenedor->gruposNombres,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'TotalProductos' => $productos->count(),
                'ActivoInactivo' => $contenedor->ActivoInactivo,
                'EstadoTexto' => $contenedor->EstadoTexto,
                'Sucursal' => $contenedor->sucursal ? $contenedor->sucursal->Nombre : '-',
                'productos' => $productos->map(function($producto) {
                    return [
                        'IdProducto' => $producto->IdProducto,
                        'Codigo' => $producto->Codigo,
                        'Descripcion' => $producto->Descripcion,
                        'Precio' => $producto->Precio,
                        'IdGrupoAnalisis' => $producto->IdGrupoAnalisis,
                        'GrupoAnalisis' => $producto->grupoAnalisis ? $producto->grupoAnalisis->Grupo : '-',
                    ];
                }),
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

            $contenedor->gruposAnalisis()->detach();
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
     * ✅ EXPORTAR PDF: Contenedores con sus grupos y productos
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
        $pdf->Cell(196, 6, 'REPORTE DE CONTENEDORES Y GRUPOS', 0, 1, 'C');
        $y += 6;

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(10, $y);
        $pdf->Cell(196, 3.5, 'Fecha de impresión: ' . $fechaImpresion . '  ·  Generado por: ' . ($operador->nombre ?? '-'), 0, 1, 'C');
        $y += 5;

        // ============ CONTADORES ============
        $totalContenedores = $contenedores->count();
        $totalGrupos = 0;
        $totalProductos = 0;
        foreach ($contenedores as $c) {
            $totalGrupos += $c->gruposAnalisis->count();
            $totalProductos += $c->productos->count();
        }

        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(240, 245, 255);
        $pdf->SetTextColor(30, 60, 120);
        $textoContadores = 'Contenedores: ' . $totalContenedores
            . '  ·  Grupos: ' . $totalGrupos
            . '  ·  Productos: ' . $totalProductos;
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
            $pdf->Cell(196, 5.5, '  ' . $contenedor->Codigo, 'LTR', 1, 'L', 1);
            $y += 5.5;

            // Info del contenedor (una sola línea, sin sucursal)
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetXY(10, $y);
            $estado = $contenedor->ActivoInactivo == 1 ? 'Activo' : 'Borrador';
            $tipo = $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-';
            $info = '  Tipo: ' . $tipo
                . ' · Capacidad: ' . number_format($contenedor->CapacidadTotal, 2, ',', '.') . ' und'
                . ' · Estado: ' . $estado
                . ' - ' . $contenedor->gruposAnalisis->count() . ' grupo(s)'
                . ' · ' . $contenedor->productos->count() . ' producto(s)';
            $pdf->Cell(196, 4.5, $info, 'LRB', 1, 'L', 1);
            $y += 5.5;

            // Recorrer grupos
            foreach ($contenedor->gruposAnalisis as $grupo) {
                if ($y > 250) {
                    $pdf->AddPage();
                    $y = 15;
                }

                // Header del grupo
                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->SetFillColor(245, 248, 255);
                $pdf->SetTextColor(20, 50, 110);
                $pdf->SetXY(12, $y);
                $pdf->Cell(192, 5, '[' . $grupo->Grupo . ']', 'LR', 1, 'L', 1);
                $y += 4;

                // Productos del grupo
                $productos = \App\Models\Gestion\Inventario\ProductoDetalle::where('IdCliente', $clienteId)
                    ->where('IdGrupoAnalisis', $grupo->IdGrupoAnalisis)
                    ->where('ActivoInactivo', 0)
                    ->orderBy('Descripcion')
                    ->get(['IdProducto', 'Codigo', 'Descripcion']);

                if ($productos->isEmpty()) {
                    $pdf->SetFont('helvetica', 'I', 7);
                    $pdf->SetTextColor(150, 150, 150);
                    $pdf->SetXY(14, $y);
                    $pdf->Cell(190, 4, '  (Sin productos en este grupo)', 'LR', 1, 'L');
                    $y += 4;
                } else {
                    // ✅ ENCABEZADOS: CÓDIGO y PRODUCTO al 50% cada uno
                    // Total: 10 + 91 + 91 = 192mm
                    $pdf->SetFont('helvetica', 'B', 7.5);
                    $pdf->SetFillColor(245, 245, 245);
                    $pdf->SetTextColor(80, 80, 80);
                    $pdf->SetXY(14, $y);
                    $pdf->Cell(10, 4.5, '#', 'TB', 0, 'C', 1);
                    $pdf->Cell(91, 4.5, 'CÓDIGO', 'TB', 0, 'C', 1);
                    $pdf->Cell(91, 4.5, 'PRODUCTO', 'TB', 1, 'L', 1);
                    $y += 4.5;

                    // Filas
                    $pdf->SetFont('helvetica', '', 7.5);
                    $pdf->SetTextColor(60, 60, 60);
                    $fill = false;
                    $contador = 0;

                    foreach ($productos as $producto) {
                        if ($y > 260) {
                            $pdf->AddPage();
                            $y = 15;

                            // Re-imprimir encabezados
                            $pdf->SetFont('helvetica', 'B', 7.5);
                            $pdf->SetFillColor(245, 245, 245);
                            $pdf->SetTextColor(80, 80, 80);
                            $pdf->SetXY(14, $y);
                            $pdf->Cell(10, 4.5, '#', 'TB', 0, 'C', 1);
                            $pdf->Cell(91, 4.5, 'CÓDIGO', 'TB', 0, 'C', 1);
                            $pdf->Cell(91, 4.5, 'PRODUCTO', 'TB', 1, 'L', 1);
                            $y += 4.5;

                            $pdf->SetFont('helvetica', '', 7.5);
                            $pdf->SetTextColor(60, 60, 60);
                        }

                        $contador++;
                        $nombreProducto = $producto->Descripcion ?? '-';
                        if (mb_strlen($nombreProducto, 'UTF-8') > 55) {
                            $nombreProducto = mb_substr($nombreProducto, 0, 53, 'UTF-8') . '...';
                        }

                        $pdf->SetXY(14, $y);
                        $pdf->Cell(10, 4.5, $contador, 'LR', 0, 'C', $fill);
                        $pdf->Cell(91, 4.5, ' ' . $producto->Codigo, 'LR', 0, 'L', $fill);
                        $pdf->Cell(91, 4.5, ' ' . $nombreProducto, 'LR', 1, 'L', $fill);
                        $y += 4.5;
                        $fill = !$fill;
                    }

                    // Línea final
                    $pdf->SetXY(14, $y);
                    $pdf->Cell(192, 0.3, '', 'T', 1);
                    $y += 2;
                }
            }

            $y += 2;
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
            ->with(['tipoContenedor', 'gruposAnalisis', 'sucursal']);

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
            ->with(['tipoContenedor', 'gruposAnalisis', 'sucursal']);
        
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
        // Ejemplo: termo20, termo30, termo80, termo100
        $contenedores = $query
            ->orderByRaw("
                LOWER(
                    REGEXP_REPLACE(Codigo, '[0-9]+', '')
                ) ASC,
                CAST(
                    REGEXP_REPLACE(Codigo, '[^0-9]+', '') AS UNSIGNED
                ) ASC
            ")
            ->paginate(20)
            ->appends($request->all());
        
        $contenedores->getCollection()->transform(function($contenedor) {
            return [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'IdTipoContenedor' => $contenedor->IdTipoContenedor,
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'GruposAnalisis' => $contenedor->gruposNombres,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'TotalProductos' => $contenedor->totalProductos,
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