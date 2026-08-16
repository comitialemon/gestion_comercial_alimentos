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
        
        $contenedores = $query->orderBy('Codigo')
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
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        $contenedor = Contenedor::porCliente($clienteId)
            ->where('IdContenedor', $id)
            ->where('IdOperadorInserta', $operadorId)
            ->with(['tipoContenedor', 'gruposAnalisis', 'sucursal'])
            ->firstOrFail();

        if ($contenedor->ActivoInactivo == 1) {
            return redirect()->route('operacion.pedidos.clientes-mayoristas.contenedores.index')
                ->with('error', 'No se puede editar un contenedor ya activo');
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
}