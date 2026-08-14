<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ContenedorDetalle;
use App\Models\Gestion\Inventario\ProductoDetalle;
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
            ->with(['detalles.producto', 'sucursal']);
        
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
                $q->where('Codigo', 'LIKE', "%{$buscar}%")
                  ->orWhere('Nombre', 'LIKE', "%{$buscar}%");
            });
        }
        
        $contenedores = $query->orderBy('Nombre')
            ->paginate(20)
            ->appends($request->all());
        
        $contenedores->getCollection()->transform(function($contenedor) {
            return [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'Nombre' => $contenedor->Nombre,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'TotalUnidades' => $contenedor->calcularTotalUnidades(),
                'ActivoInactivo' => $contenedor->ActivoInactivo,
                'EstadoTexto' => $contenedor->EstadoTexto,
                'EstadoColor' => $contenedor->EstadoColor,
                'IdSucursal' => $contenedor->IdSucursal,
                'cantidad_productos' => $contenedor->detalles->count(),
                'sucursal' => $contenedor->sucursal ? [
                    'Nombre' => $contenedor->sucursal->Nombre,
                    'NumeroSucursal' => $contenedor->sucursal->NumeroSucursal,
                ] : null,
                'detalles' => $contenedor->detalles->map(function($detalle) {
                    return [
                        'IdProducto' => $detalle->IdProducto,
                        'Producto' => $detalle->producto ? $detalle->producto->Descripcion : '-',
                        'Codigo' => $detalle->producto ? $detalle->producto->Codigo : '-',
                        'Cantidad' => $detalle->Cantidad,
                        'CantidadFormateada' => $detalle->CantidadFormateada,
                    ];
                }),
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
     * ✅ VISTA DE GESTIÓN DE ESTADOS
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
            ->with(['detalles.producto', 'sucursal']);
        
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
                $q->where('Codigo', 'LIKE', "%{$buscar}%")
                  ->orWhere('Nombre', 'LIKE', "%{$buscar}%");
            });
        }
        
        $contenedores = $query->orderBy('IdContenedor', 'desc')->paginate(20);
        $contenedores->appends($request->all());
        
        $contenedores->getCollection()->transform(function($contenedor) {
            return [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'Nombre' => $contenedor->Nombre,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'TotalUnidades' => $contenedor->calcularTotalUnidades(),
                'ActivoInactivo' => $contenedor->ActivoInactivo,
                'EstadoTexto' => $contenedor->EstadoTexto,
                'EstadoColor' => $contenedor->EstadoColor,
                'IdSucursal' => $contenedor->IdSucursal,
                'cantidad_productos' => $contenedor->detalles->count(),
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
     * ✅ MODIFICADO: Si existe un borrador, redirige a edit
     */
    public function create()
    {
        // ✅ Verificar si existe un borrador para este operador
        $borrador = Contenedor::borradorPorOperador()->first();
        
        if ($borrador) {
            // ✅ Si existe, redirigir a edit
            return redirect()->route('operacion.pedidos.clientes-mayoristas.contenedores.edit', $borrador->IdContenedor)
                ->with('info', 'Continuando con el borrador existente.');
        }

        // Si no existe, mostrar el formulario para crear uno nuevo
        $clienteId = session('cliente_id');
        
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'codigo' => $item->Codigo,
                    'descripcion' => $item->Descripcion,
                    'texto' => $item->Codigo . ' - ' . $item->Descripcion,
                ];
            });

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Create', [
            'contenedor' => null,
            'sucursales' => $sucursales,
            'productos' => $productos,
        ]);
    }

    /**
     * PASO 1: Guardar cabecera del contenedor (BORRADOR)
     * ✅ MODIFICADO: Usa obtenerOCrearBorrador
     */
    public function store(Request $request)
    {
        $request->validate([
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Nombre' => 'required|string|max:100',
            'CapacidadTotal' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        // ✅ Verificar si ya existe un borrador para este operador
        $borradorExistente = Contenedor::borradorPorOperador()->first();

        if ($borradorExistente) {
            // ✅ Si existe, actualizarlo en lugar de crear uno nuevo
            $codigoBase = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $request->Nombre), 0, 3));
            $codigo = $codigoBase . '-' . intval($request->CapacidadTotal);
            
            $borradorExistente->update([
                'IdSucursal' => $request->IdSucursal,
                'Nombre' => $request->Nombre,
                'CapacidadTotal' => $request->CapacidadTotal,
                'Codigo' => $codigo,
            ]);

            return response()->json([
                'success' => true,
                'contenedor' => $borradorExistente,
                'message' => 'Borrador actualizado correctamente'
            ]);
        }

        // Si no existe, crear uno nuevo
        $codigoBase = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $request->Nombre), 0, 3));
        $codigo = $codigoBase . '-' . intval($request->CapacidadTotal);
        
        $existe = Contenedor::where('IdCliente', $clienteId)
            ->where(function($q) use ($codigo, $request) {
                $q->where('Codigo', $codigo)
                ->orWhere(function($q2) use ($request) {
                    $q2->where('Nombre', $request->Nombre)
                        ->where('CapacidadTotal', $request->CapacidadTotal);
                });
            })
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un contenedor con este nombre y capacidad, o con el código ' . $codigo
            ], 422);
        }

        DB::beginTransaction();

        try {
            $contenedor = Contenedor::create([
                'Codigo' => $codigo,
                'Nombre' => $request->Nombre,
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
                'message' => 'Contenedor creado como borrador. Agrega los productos.'
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
     * PASO 2: Mostrar formulario de edición con productos
     * ✅ MODIFICADO: Verifica que el contenedor pertenezca al operador
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        // ✅ Buscar el contenedor verificando que pertenezca al operador
        $contenedor = Contenedor::porCliente($clienteId)
            ->where('IdContenedor', $id)
            ->where('IdOperadorInserta', $operadorId)
            ->with(['detalles.producto', 'sucursal'])
            ->firstOrFail();

        // Si está ACTIVO (1), no se puede editar
        if ($contenedor->ActivoInactivo == 1) {
            return redirect()->route('operacion.pedidos.clientes-mayoristas.contenedores.index')
                ->with('error', 'No se puede editar un contenedor ya activo');
        }

        // ✅ Sucursales para el autocomplete
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        // Productos para el buscador
        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto as id', 'Codigo', 'Descripcion'])
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'codigo' => $item->Codigo,
                    'descripcion' => $item->Descripcion,
                    'texto' => $item->Codigo . ' - ' . $item->Descripcion,
                ];
            });

        return Inertia::render('Operacion/ClientesMayoristas/Contenedores/Create', [
            'contenedor' => $contenedor,
            'sucursales' => $sucursales,
            'productos' => $productos,
        ]);
    }

    /**
     * Actualizar cabecera del contenedor
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Nombre' => 'required|string|max:100',
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

        $codigoBase = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $request->Nombre), 0, 3));
        $codigo = $codigoBase . '-' . intval($request->CapacidadTotal);
        
        $existe = Contenedor::where('IdCliente', session('cliente_id'))
            ->where('IdContenedor', '!=', $id)
            ->where(function($q) use ($codigo, $request) {
                $q->where('Codigo', $codigo)
                ->orWhere(function($q2) use ($request) {
                    $q2->where('Nombre', $request->Nombre)
                        ->where('CapacidadTotal', $request->CapacidadTotal);
                });
            })
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un contenedor con este nombre y capacidad, o con el código ' . $codigo
            ], 422);
        }

        $contenedor->update([
            'IdSucursal' => $request->IdSucursal,
            'Nombre' => $request->Nombre,
            'CapacidadTotal' => $request->CapacidadTotal,
            'Codigo' => $codigo,
        ]);

        return response()->json([
            'success' => true,
            'contenedor' => $contenedor,
            'message' => 'Contenedor actualizado correctamente'
        ]);
    }

    /**
     * PASO 2: Agregar producto al contenedor
     */
    public function agregarProducto(Request $request)
    {
        $request->validate([
            'IdContenedor' => 'required|exists:operacion_pedidos_clientes_contenedor,IdContenedor',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'Cantidad' => 'required|numeric|min:0.01',
        ]);

        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $request->IdContenedor)
            ->firstOrFail();

        if ($contenedor->ActivoInactivo == 1) {
            return response()->json(['success' => false, 'message' => 'El contenedor ya está activo'], 400);
        }

        // ✅ VALIDACIÓN: La cantidad no debe exceder la CapacidadTotal
        if ($request->Cantidad > $contenedor->CapacidadTotal) {
            return response()->json([
                'success' => false,
                'message' => "La cantidad ({$request->Cantidad}) excede el límite máximo de {$contenedor->CapacidadTotal} unidades por producto"
            ], 400);
        }

        $existe = ContenedorDetalle::where('IdContenedor', $request->IdContenedor)
            ->where('IdProducto', $request->IdProducto)
            ->exists();

        if ($existe) {
            return response()->json(['success' => false, 'message' => 'El producto ya está agregado al contenedor'], 400);
        }

        $detalle = ContenedorDetalle::create([
            'IdContenedor' => $request->IdContenedor,
            'IdProducto' => $request->IdProducto,
            'Cantidad' => $request->Cantidad,
        ]);

        $detalle->load('producto');

        return response()->json([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }

    /**
     * PASO 2: Actualizar cantidad de un producto
     */
    public function actualizarProducto(Request $request, $id)
    {
        $request->validate([
            'Cantidad' => 'required|numeric|min:0.01',
        ]);

        $detalle = ContenedorDetalle::findOrFail($id);
        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $detalle->IdContenedor)
            ->firstOrFail();

        if ($contenedor->ActivoInactivo == 1) {
            return response()->json(['success' => false, 'message' => 'El contenedor ya está activo'], 400);
        }

        // ✅ VALIDACIÓN: La cantidad no debe exceder la CapacidadTotal
        if ($request->Cantidad > $contenedor->CapacidadTotal) {
            return response()->json([
                'success' => false,
                'message' => "La cantidad ({$request->Cantidad}) excede el límite máximo de {$contenedor->CapacidadTotal} unidades por producto"
            ], 400);
        }

        $detalle->update([
            'Cantidad' => $request->Cantidad,
        ]);

        $detalle->load('producto');

        return response()->json([
            'success' => true,
            'detalle' => $detalle,
        ]);
    }

    /**
     * PASO 2: Eliminar producto del contenedor
     */
    public function eliminarProducto($id)
    {
        $detalle = ContenedorDetalle::findOrFail($id);
        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $detalle->IdContenedor)
            ->firstOrFail();

        if ($contenedor->ActivoInactivo == 1) {
            return response()->json(['success' => false, 'message' => 'El contenedor ya está activo'], 400);
        }

        $detalle->delete();

        return response()->json(['success' => true]);
    }

    /**
     * PASO 3: Finalizar contenedor (cambiar estado a ACTIVO)
     */
    public function finalizar($id)
    {
        $contenedor = Contenedor::porCliente()
            ->where('IdContenedor', $id)
            ->with(['detalles'])
            ->firstOrFail();

        if ($contenedor->ActivoInactivo == 1) {
            return response()->json([
                'success' => false,
                'message' => 'El contenedor ya está activo'
            ], 400);
        }

        if ($contenedor->detalles->count() == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Agregue al menos un producto al contenedor'
            ], 400);
        }

        // ✅ Verificar que ningún producto exceda la capacidad
        foreach ($contenedor->detalles as $detalle) {
            if ($detalle->Cantidad > $contenedor->CapacidadTotal) {
                return response()->json([
                    'success' => false,
                    'message' => "El producto '{$detalle->producto->Descripcion}' excede el límite máximo de {$contenedor->CapacidadTotal} unidades"
                ], 400);
            }
        }

        $contenedor->update([
            'ActivoInactivo' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contenedor activado correctamente'
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
                ->with(['detalles'])
                ->firstOrFail();
            
            if ($contenedor->ActivoInactivo == 1) {
                $contenedor->update(['ActivoInactivo' => 0]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Contenedor desactivado correctamente (pasó a Borrador)',
                    'nuevo_estado' => 0
                ]);
                
            } else {
                if ($contenedor->detalles->count() == 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El contenedor no tiene productos. Agregue productos primero.'
                    ], 400);
                }
                
                // ✅ Verificar que ningún producto exceda la capacidad
                foreach ($contenedor->detalles as $detalle) {
                    if ($detalle->Cantidad > $contenedor->CapacidadTotal) {
                        return response()->json([
                            'success' => false,
                            'message' => "El producto '{$detalle->producto->Descripcion}' excede el límite máximo de {$contenedor->CapacidadTotal} unidades"
                        ], 400);
                    }
                }
                
                $contenedor->update(['ActivoInactivo' => 1]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Contenedor activado correctamente',
                    'nuevo_estado' => 1
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
            ->with(['detalles.producto', 'sucursal', 'cliente'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'Nombre' => $contenedor->Nombre,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => $contenedor->CapacidadTotalFormateada,
                'TotalUnidades' => $contenedor->calcularTotalUnidades(),
                'ActivoInactivo' => $contenedor->ActivoInactivo,
                'EstadoTexto' => $contenedor->EstadoTexto,
                'Sucursal' => $contenedor->sucursal ? $contenedor->sucursal->Nombre : '-',
                'detalles' => $contenedor->detalles->map(function($detalle) {
                    return [
                        'IdProducto' => $detalle->IdProducto,
                        'Producto' => $detalle->producto ? $detalle->producto->Descripcion : '-',
                        'Codigo' => $detalle->producto ? $detalle->producto->Codigo : '-',
                        'Cantidad' => $detalle->Cantidad,
                        'CantidadFormateada' => $detalle->CantidadFormateada,
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

            $contenedor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contenedor eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}