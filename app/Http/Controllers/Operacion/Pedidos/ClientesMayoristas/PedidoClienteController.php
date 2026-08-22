<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClienteDetalle;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PrecioProducto;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ContenedorCliente; // ✅ AGREGAR ESTO

use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PedidoClienteController extends Controller
{
    /**
     * ✅ OBTENER IdIdentificador DEL OPERADOR LOGUEADO (CON CACHÉ)
     */
    private function getIdIdentificadorOperador()
    {
        $operadorId = session('operador_id');
        
        $cacheKey = 'operador_identificador_' . $operadorId;
        
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }
        
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->where('IdOperador', $operadorId)
            ->first(['IdIdentificador']);
        
        $idIdentificador = $operador ? $operador->IdIdentificador : null;
        
        cache()->put($cacheKey, $idIdentificador, 3600);
        
        return $idIdentificador;
    }

    /**
     * ✅ OBTENER NOMBRE DEL OPERADOR
     */
    private function getNombreOperador()
    {
        $operadorId = session('operador_id');
        
        $cacheKey = 'operador_nombre_' . $operadorId;
        
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }
        
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $operadorId)
            ->first(['todos_identificador.Nombre']);
        
        $nombre = $operador ? $operador->Nombre : 'Sin nombre';
        
        cache()->put($cacheKey, $nombre, 3600);
        
        return $nombre;
    }

    /**
     * Lista de pedidos del operador logueado
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        $pedidos = PedidoCliente::porContexto()
            ->where('IdOperador', $operadorId)
            ->with(['cliente', 'sucursal', 'operador'])
            ->orderBy('IdPedidoCliente', 'desc')
            ->paginate(20);

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Index', [
            'pedidos' => $pedidos,
        ]);
    }

    /**
     * ✅ NUEVO PEDIDO - Menú de contenedores (solo asignados al operador)
     */
    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        $idIdentificador = $this->getIdIdentificadorOperador();
        
        if (!$idIdentificador) {
            return redirect()->back()->with('error', 
                'No se encontró el perfil del operador. Contacte al administrador.'
            );
        }

        // ✅ OBTENER SOLO CONTENEDORES ASIGNADOS A ESTE OPERADOR
        $contenedores = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->whereExists(function($query) use ($idIdentificador, $clienteId, $sucursalId) {
                $query->select(DB::raw(1))
                    ->from('operacion_pedidos_clientes_contenedor_cliente as cc')
                    ->whereColumn('cc.IdContenedor', 'operacion_pedidos_clientes_contenedor.IdContenedor')
                    ->where('cc.IdIdentificador', $idIdentificador)
                    ->where('cc.IdCliente', $clienteId)
                    ->where('cc.IdSucursal', $sucursalId)
                    ->where('cc.ActivoInactivo', 1);
            })
            ->with(['tipoContenedor', 'gruposAnalisis'])
            ->orderBy('Codigo')
            ->get()
            ->filter(function($contenedor) {
                // ✅ Solo mostrar contenedores que tengan al menos un producto activo
                return $contenedor->contarProductosActivos() > 0;
            })
            ->map(function($contenedor) {
                return [
                    'IdContenedor' => $contenedor->IdContenedor,
                    'Codigo' => $contenedor->Codigo,
                    'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                    'CapacidadTotal' => $contenedor->CapacidadTotal,
                    'CapacidadTotalFormateada' => number_format($contenedor->CapacidadTotal, 0, ',', '.'),
                    'total_productos' => $contenedor->contarProductosActivos(),
                    'grupos' => $contenedor->gruposAnalisis->pluck('Grupo')->implode(', '),
                ];
            })
            ->values();

        // ✅ Obtener clientes (empresas)
        $clientes = Cliente::where('IdCliente', $clienteId)
            ->get(['IdCliente as id', 'Nombre']);

        // ✅ Obtener sucursales
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre']);

        // ✅ Buscar pedido BORRADOR
        $pedidoBorrador = PedidoCliente::obtenerBorradorActivo();

        // ✅ Cargar carrito
        $carrito = [];
        if ($pedidoBorrador) {
            $detalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedidoBorrador->IdPedidoCliente)
                ->with(['producto', 'contenedor'])
                ->orderBy('OrdenContenedor')
                ->orderBy('IdProducto')
                ->get();

            $carrito = $detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                $total = $items->sum('Cantidad');
                
                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'productos' => $items->map(function($item) {
                        return [
                            'IdProducto' => $item->IdProducto,
                            'Codigo' => $item->producto ? $item->producto->Codigo : '-',
                            'Descripcion' => $item->producto ? $item->producto->Descripcion : '-',
                            'Cantidad' => $item->Cantidad,
                            'IdPedidoClienteDetalle' => $item->IdPedidoClienteDetalle,
                        ];
                    }),
                    'total_unidades' => $total,
                    'esta_completo' => $total == ($contenedor ? $contenedor->CapacidadTotal : 0),
                ];
            })->values();
        }

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Create', [
            'contenedores' => $contenedores,
            'clientes' => $clientes,
            'sucursales' => $sucursales,
            'pedidoBorrador' => $pedidoBorrador,
            'carrito' => $carrito,
            'sucursalDefault' => $sucursalId,
            'idIdentificador' => $idIdentificador,
            'nombreOperador' => $this->getNombreOperador(),
        ]);
    }

    /**
     * ✅ OBTENER PRODUCTOS DE UN CONTENEDOR (SIN PRECIOS - LEGADO)
     */
    public function getProductosContenedor($id)
    {
        $clienteId = session('cliente_id');

        $contenedor = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with(['gruposAnalisis', 'tipoContenedor'])
            ->findOrFail($id);

        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $contenedor->gruposAnalisis->pluck('IdGrupoAnalisis'))
            ->where('ActivoInactivo', 0)
            ->orderBy('IdGrupoAnalisis')
            ->orderBy('Descripcion')
            ->get();

        $productosAgrupados = $productos->groupBy('IdGrupoAnalisis')->map(function($items, $grupoId) {
            $grupo = \App\Models\Gestion\Inventario\ProductoGrupoAnalisis::find($grupoId);
            return [
                'grupo_id' => $grupoId,
                'grupo_nombre' => $grupo ? $grupo->Grupo : 'Sin grupo',
                'productos' => $items->map(function($producto) {
                    return [
                        'IdProducto' => $producto->IdProducto,
                        'Codigo' => $producto->Codigo,
                        'Descripcion' => $producto->Descripcion,
                        'Precio' => $producto->Precio,
                        'IdGrupoAnalisis' => $producto->IdGrupoAnalisis,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'CapacidadTotalFormateada' => number_format($contenedor->CapacidadTotal, 0, ',', '.'),
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'productos_agrupados' => $productosAgrupados,
                'total_productos' => $productos->count(),
            ]
        ]);
    }

    /**
     * ✅ OBTENER PRODUCTOS DE UN CONTENEDOR CON PRECIOS Y CANTIDAD MÍNIMA
     * ✅ SOLO PRODUCTOS QUE TIENEN PRECIO ASIGNADO
     */
    public function getProductosContenedorConPrecios($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $idIdentificador = $this->getIdIdentificadorOperador();
        
        if (!$idIdentificador) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el perfil del operador.'
            ], 400);
        }

        $contenedor = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with(['gruposAnalisis', 'tipoContenedor'])
            ->findOrFail($id);

        // ✅ OBTENER CANTIDAD MÍNIMA DEL CONTENEDOR PARA ESTE CLIENTE
        $cantidadMinima = ContenedorCliente::where('IdContenedor', $id)
            ->where('IdIdentificador', $idIdentificador)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->value('CantidadMinima') ?? 0;

        // ✅ OBTENER TODOS LOS PRODUCTOS DE LOS GRUPOS
        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $contenedor->gruposAnalisis->pluck('IdGrupoAnalisis'))
            ->where('ActivoInactivo', 0)
            ->orderBy('IdGrupoAnalisis')
            ->orderBy('Descripcion')
            ->get();

        // ✅ OBTENER PRECIOS PARA ESTE IDENTIFICADOR
        $precios = PrecioProducto::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdIdentificador', $idIdentificador)
            ->where('ActivoInactivo', 1)
            ->get()
            ->keyBy('IdProducto');

        // ✅ FILTRAR: SOLO PRODUCTOS QUE TIENEN PRECIO ASIGNADO
        $productosFiltrados = $productos->filter(function($producto) use ($precios) {
            return isset($precios[$producto->IdProducto]);
        });

        // ✅ AGRUPAR POR GRUPO
        $productosAgrupados = $productosFiltrados->groupBy('IdGrupoAnalisis')->map(function($items, $grupoId) use ($precios, $contenedor, $cantidadMinima) {
            $grupo = \App\Models\Gestion\Inventario\ProductoGrupoAnalisis::find($grupoId);
            return [
                'grupo_id' => $grupoId,
                'grupo_nombre' => $grupo ? $grupo->Grupo : 'Sin grupo',
                'productos' => $items->map(function($producto) use ($precios, $contenedor, $cantidadMinima) {
                    $precio = $precios[$producto->IdProducto];
                    return [
                        'IdProducto' => $producto->IdProducto,
                        'Codigo' => $producto->Codigo,
                        'Descripcion' => $producto->Descripcion,
                        'Precio' => $producto->Precio,
                        'PrecioEspecial' => $precio ? $precio->Precio : null,
                        'tiene_precio' => true, // ✅ Siempre true porque filtramos
                        'IdGrupoAnalisis' => $producto->IdGrupoAnalisis,
                        'CantidadMinima' => $cantidadMinima,
                        'CapacidadTotal' => $contenedor->CapacidadTotal,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'productos_agrupados' => $productosAgrupados,
                'total_productos' => $productosFiltrados->count(),
                'idIdentificador' => $idIdentificador,
                'cantidadMinima' => $cantidadMinima,
                'mensaje' => $productosFiltrados->count() === 0 ? 'No hay productos con precio asignado para este contenedor' : null,
            ]
        ]);
    }

    /**
     * ✅ AGREGAR PRODUCTOS AL CARRITO (con precios)
     */
    public function agregarAlCarrito(Request $request)
    {
        $request->validate([
            'IdContenedor' => 'required|exists:operacion_pedidos_clientes_contenedor,IdContenedor',
            'productos' => 'required|array|min:1',
            'productos.*.IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'productos.*.Cantidad' => 'required|numeric|min:0.01',
            'productos.*.Precio' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $contenedor = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with(['gruposAnalisis'])
            ->findOrFail($request->IdContenedor);

        $totalUnidades = array_sum(array_column($request->productos, 'Cantidad'));

        if ((float) $totalUnidades > (float) $contenedor->CapacidadTotal) {
            return response()->json([
                'success' => false,
                'message' => "La suma de productos ({$totalUnidades}) excede la capacidad del contenedor ({$contenedor->CapacidadTotal})"
            ], 400);
        }

        $gruposIds = $contenedor->gruposAnalisis->pluck('IdGrupoAnalisis')->toArray();
        $productosIds = array_column($request->productos, 'IdProducto');
        
        $productosValidos = ProductoDetalle::whereIn('IdProducto', $productosIds)
            ->whereIn('IdGrupoAnalisis', $gruposIds)
            ->where('ActivoInactivo', 0)
            ->count();

        if ($productosValidos != count($productosIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Uno o más productos no pertenecen a los grupos de este contenedor'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $pedido = PedidoCliente::obtenerOCrearBorrador([
                'IdSucursal' => $sucursalId,
            ]);

            $maxOrden = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                ->max('OrdenContenedor') ?? 0;
            $nuevoOrden = $maxOrden + 1;

            foreach ($request->productos as $producto) {
                PedidoClienteDetalle::create([
                    'IdPedidoCliente' => $pedido->IdPedidoCliente,
                    'IdContenedor' => $request->IdContenedor,
                    'IdProducto' => $producto['IdProducto'],
                    'Cantidad' => $producto['Cantidad'],
                    'Precio' => $producto['Precio'],
                    'OrdenContenedor' => $nuevoOrden,
                ]);
            }

            $totales = $this->calcularTotales($pedido->IdPedidoCliente);
            $pedido->update([
                'TotalUnidades' => $totales['total_unidades'],
                'TotalContenedores' => $totales['total_contenedores'],
                'TotalGeneral' => $totales['total_general'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contenedor agregado al carrito correctamente',
                'pedido' => $pedido,
                'totales' => $totales,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al agregar al carrito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar al carrito: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ ACTUALIZAR CONTENEDOR DEL CARRITO (EDITAR)
     */
    public function actualizarContenedor(Request $request)
    {
        $request->validate([
            'IdPedidoCliente' => 'required|exists:pedidos_clientes,IdPedidoCliente',
            'IdContenedor' => 'required|exists:operacion_pedidos_clientes_contenedor,IdContenedor',
            'OrdenContenedor' => 'required|integer',
            'productos' => 'required|array|min:1',
            'productos.*.IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'productos.*.Cantidad' => 'required|numeric|min:0',
            'productos.*.Precio' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');

        $pedido = PedidoCliente::where('IdCliente', $clienteId)
            ->where('IdPedidoCliente', $request->IdPedidoCliente)
            ->where('ActivoInactivo', 0)
            ->first();

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'El pedido no existe o ya fue finalizado.'
            ], 404);
        }

        $existe = PedidoClienteDetalle::where('IdPedidoCliente', $request->IdPedidoCliente)
            ->where('IdContenedor', $request->IdContenedor)
            ->where('OrdenContenedor', $request->OrdenContenedor)
            ->exists();

        if (!$existe) {
            return response()->json([
                'success' => false,
                'message' => 'El contenedor no pertenece a este pedido.'
            ], 404);
        }

        $totalUnidades = array_sum(array_column($request->productos, 'Cantidad'));

        $contenedor = Contenedor::find($request->IdContenedor);
        if ((float) $totalUnidades > (float) $contenedor->CapacidadTotal) {
            return response()->json([
                'success' => false,
                'message' => "La suma de productos ({$totalUnidades}) excede la capacidad del contenedor ({$contenedor->CapacidadTotal})"
            ], 400);
        }

        DB::beginTransaction();

        try {
            PedidoClienteDetalle::where('IdPedidoCliente', $request->IdPedidoCliente)
                ->where('IdContenedor', $request->IdContenedor)
                ->where('OrdenContenedor', $request->OrdenContenedor)
                ->delete();

            foreach ($request->productos as $producto) {
                if ($producto['Cantidad'] > 0) {
                    PedidoClienteDetalle::create([
                        'IdPedidoCliente' => $request->IdPedidoCliente,
                        'IdContenedor' => $request->IdContenedor,
                        'IdProducto' => $producto['IdProducto'],
                        'Cantidad' => $producto['Cantidad'],
                        'Precio' => $producto['Precio'],
                        'OrdenContenedor' => $request->OrdenContenedor,
                    ]);
                }
            }

            $totales = $this->calcularTotales($request->IdPedidoCliente);
            $pedido->update([
                'TotalUnidades' => $totales['total_unidades'],
                'TotalContenedores' => $totales['total_contenedores'],
                'TotalGeneral' => $totales['total_general'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Contenedor actualizado correctamente',
                'pedido' => $pedido,
                'totales' => $totales,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar contenedor: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar contenedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ ELIMINAR CONTENEDOR DEL CARRITO
     */
    public function eliminarDelCarrito($idDetalle)
    {
        try {
            $detalle = PedidoClienteDetalle::findOrFail($idDetalle);
            $pedido = PedidoCliente::findOrFail($detalle->IdPedidoCliente);

            if ($pedido->ActivoInactivo == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido ya está finalizado'
                ], 400);
            }

            $orden = $detalle->OrdenContenedor;
            PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                ->where('OrdenContenedor', $orden)
                ->delete();

            $totales = $this->calcularTotales($pedido->IdPedidoCliente);
            $pedido->update([
                'TotalUnidades' => $totales['total_unidades'],
                'TotalContenedores' => $totales['total_contenedores'],
                'TotalGeneral' => $totales['total_general'],
            ]);

            if ($totales['total_unidades'] == 0) {
                $pedido->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Carrito vacío',
                    'carrito_vacio' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Contenedor eliminado del carrito',
                'totales' => $totales,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar del carrito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar del carrito: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ VACIAR CARRITO COMPLETO
     */
    public function vaciarCarrito($idPedido)
    {
        try {
            $pedido = PedidoCliente::findOrFail($idPedido);

            if ($pedido->ActivoInactivo == 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido ya está finalizado'
                ], 400);
            }

            PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)->delete();
            
            $pedido->update([
                'TotalUnidades' => 0,
                'TotalContenedores' => 0,
                'TotalGeneral' => 0,
            ]);

            $pedido->delete();

            return response()->json([
                'success' => true,
                'message' => 'Carrito vaciado correctamente',
                'carrito_vacio' => true,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al vaciar carrito: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al vaciar carrito: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ REVISAR PEDIDO
     */
    public function review($id)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');
            
            $idIdentificador = $this->getIdIdentificadorOperador();
            
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->where('ActivoInactivo', 0)
                ->with(['detalles.producto', 'detalles.contenedor'])
                ->first();

            if (!$pedido) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                    ->with('error', 'El pedido no existe o ya fue finalizado.');
            }

            $totalDetalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)->count();
            if ($totalDetalles === 0) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                    ->with('error', 'El pedido no tiene productos.');
            }

            $detallesAgrupados = $pedido->detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                $total = $items->sum('Cantidad');
                $subtotal = $items->sum(function($item) {
                    return $item->Cantidad * $item->Precio;
                });
                
                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'productos' => $items->map(function($item) {
                        return [
                            'IdProducto' => $item->IdProducto,
                            'Codigo' => $item->producto ? $item->producto->Codigo : '-',
                            'Descripcion' => $item->producto ? $item->producto->Descripcion : '-',
                            'Cantidad' => $item->Cantidad,
                            'Precio' => $item->Precio,
                            'Subtotal' => $item->Cantidad * $item->Precio,
                            'IdPedidoClienteDetalle' => $item->IdPedidoClienteDetalle,
                        ];
                    }),
                    'total_unidades' => $total,
                    'subtotal' => $subtotal,
                    'esta_completo' => $total == ($contenedor ? $contenedor->CapacidadTotal : 0),
                ];
            })->values();

            $cliente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first(['Nombre']);
            
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $sucursalId)
                ->first(['Nombre']);
            
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $operadorId)
                ->first(['todos_identificador.Nombre as nombre']);

            $totalGeneral = $pedido->detalles->sum(function($item) {
                return $item->Cantidad * $item->Precio;
            });

            return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Review', [
                'pedido' => $pedido,
                'detallesAgrupados' => $detallesAgrupados,
                'clienteNombre' => $cliente->Nombre ?? 'Sin cliente',
                'sucursalNombre' => $sucursal->Nombre ?? 'Sin sucursal',
                'operadorNombre' => $operador->nombre ?? 'Sin operador',
                'totalGeneral' => $totalGeneral,
                'idIdentificador' => $idIdentificador,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en review: ' . $e->getMessage());
            return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                ->with('error', 'Error al cargar la revisión: ' . $e->getMessage());
        }
    }

    /**
     * ✅ FINALIZAR PEDIDO
     */
    public function finalizarPedido(Request $request, $idPedido)
    {
        \Log::info('=== 🚀 FINALIZAR PEDIDO ===');
        \Log::info('📝 Datos recibidos:', $request->all());

        $request->validate([
            'IdCliente' => 'required|exists:todos_cliente,IdCliente',
            'IdSucursal' => [
                'required',
                'exists:todos_cliente_sucursal,IdClienteSucursal',
                function ($attribute, $value, $fail) use ($request) {
                    $exists = ClienteSucursal::where('IdClienteSucursal', $value)
                        ->where('IdCliente', $request->IdCliente)
                        ->exists();
                    if (!$exists) {
                        $fail('La sucursal no pertenece al cliente seleccionado.');
                    }
                }
            ],
            'Observaciones' => 'nullable|string|max:500',
        ]);

        $fechaEntrega = $request->input('FechaEntrega');
        $fechaEntregaFormateada = null;
        
        if (!empty($fechaEntrega)) {
            $fechaEntrega = trim($fechaEntrega);
            
            if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fechaEntrega, $matches)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Formato de fecha inválido. Use DD/MM/YYYY.'
                ], 422);
            }

            $dia = (int)$matches[1];
            $mes = (int)$matches[2];
            $anio = (int)$matches[3];
            
            $timestampEntrega = mktime(0, 0, 0, $mes, $dia, $anio);
            
            date_default_timezone_set('America/La_Paz');
            $timestampHoy = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
            
            $diferenciaDias = ($timestampEntrega - $timestampHoy) / 86400;
            $diferenciaDias = floor($diferenciaDias);
            
            \Log::info('📅 VALIDACIÓN FECHA:', [
                'fecha_entrega' => $fechaEntrega,
                'fecha_hoy' => date('d/m/Y'),
                'diferencia_dias' => $diferenciaDias
            ]);

            if ($diferenciaDias < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'La fecha de entrega debe ser mínimo 1 día después de hoy. Hoy es ' . date('d/m/Y')
                ], 422);
            }

            $fechaEntregaFormateada = date('Y-m-d', $timestampEntrega);
            \Log::info('✅ Fecha aceptada: ' . $fechaEntregaFormateada);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'La fecha de entrega es obligatoria.'
            ], 422);
        }

        $sucursalValida = ClienteSucursal::where('IdClienteSucursal', $request->IdSucursal)
            ->where('IdCliente', $request->IdCliente)
            ->exists();

        if (!$sucursalValida) {
            return response()->json([
                'success' => false,
                'message' => 'La sucursal no pertenece al cliente seleccionado.'
            ], 400);
        }

        try {
            $clienteId = session('cliente_id');
            $operadorId = session('operador_id');

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $idPedido)
                ->where('ActivoInactivo', 0)
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido no existe o ya fue finalizado.'
                ], 404);
            }

            $detalles = PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)->get();
            $ordenes = $detalles->groupBy('OrdenContenedor');
            
            foreach ($ordenes as $orden => $items) {
                $total = $items->sum('Cantidad');
                $contenedor = Contenedor::find($items->first()->IdContenedor);
                $capacidad = $contenedor ? $contenedor->CapacidadTotal : 0;
                
                if ((float) $total > (float) $capacidad) {
                    return response()->json([
                        'success' => false,
                        'message' => "El contenedor #{$orden} tiene {$total} unidades, excede la capacidad de {$capacidad} unidades"
                    ], 400);
                }
            }

            $totales = $this->calcularTotales($pedido->IdPedidoCliente);

            $maxNumero = PedidoCliente::where('IdCliente', $request->IdCliente)
                ->where('IdSucursal', $request->IdSucursal)
                ->where('NumeroPedido', '!=', '0')
                ->whereNotNull('NumeroPedido')
                ->max(DB::raw('CAST(NumeroPedido AS UNSIGNED)')) ?? 0;

            $nuevoNumero = $maxNumero + 1;
            $numeroPedidoFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);

            $existe = PedidoCliente::where('IdCliente', $request->IdCliente)
                ->where('IdSucursal', $request->IdSucursal)
                ->where('NumeroPedido', $numeroPedidoFormateado)
                ->exists();

            if ($existe) {
                $nuevoNumero = $nuevoNumero + 1;
                $numeroPedidoFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);
                
                $existeNuevamente = PedidoCliente::where('IdCliente', $request->IdCliente)
                    ->where('IdSucursal', $request->IdSucursal)
                    ->where('NumeroPedido', $numeroPedidoFormateado)
                    ->exists();
                    
                if ($existeNuevamente) {
                    do {
                        $nuevoNumero++;
                        $numeroPedidoFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);
                        $existeNuevamente = PedidoCliente::where('IdCliente', $request->IdCliente)
                            ->where('IdSucursal', $request->IdSucursal)
                            ->where('NumeroPedido', $numeroPedidoFormateado)
                            ->exists();
                    } while ($existeNuevamente);
                }
            }

            $pedido->update([
                'IdCliente' => $request->IdCliente,
                'IdSucursal' => $request->IdSucursal,
                'NumeroPedido' => $numeroPedidoFormateado,
                'FechaEntrega' => $fechaEntregaFormateada,
                'Observaciones' => $request->Observaciones,
                'ActivoInactivo' => 1,
                'EstadoPedido' => 'Pendiente',
                'FechaPedido' => Carbon::now('America/La_Paz'),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
                'TotalUnidades' => $totales['total_unidades'],
                'TotalContenedores' => $totales['total_contenedores'],
                'TotalGeneral' => $totales['total_general'],
            ]);

            \Log::info('🎉 Pedido finalizado correctamente', [
                'IdPedidoCliente' => $pedido->IdPedidoCliente,
                'NumeroPedido' => $pedido->NumeroPedido,
                'FechaEntrega' => $fechaEntregaFormateada,
                'TotalGeneral' => $totales['total_general']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pedido finalizado correctamente',
                'pedido_id' => $pedido->IdPedidoCliente,
                'numero_pedido' => $pedido->NumeroPedido,
                'pdf_url' => url("/operacion/pedidos/clientes-mayoristas/pedidos-clientes/{$pedido->IdPedidoCliente}/pdf")
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error al finalizar pedido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ MOSTRAR DETALLE DEL PEDIDO
     */
    public function show($id)
    {
        try {
            $clienteId = session('cliente_id');

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with(['cliente', 'sucursal', 'operador', 'detalles.producto', 'detalles.contenedor'])
                ->first();

            if (!$pedido) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                    ->with('error', 'El pedido no existe.');
            }

            $detallesAgrupados = $pedido->detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                $subtotal = $items->sum(function($item) {
                    return $item->Cantidad * $item->Precio;
                });
                
                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'productos' => $items->map(function($item) {
                        return [
                            'IdProducto' => $item->IdProducto,
                            'Codigo' => $item->producto ? $item->producto->Codigo : '-',
                            'Descripcion' => $item->producto ? $item->producto->Descripcion : '-',
                            'Cantidad' => $item->Cantidad,
                            'Precio' => $item->Precio,
                            'Subtotal' => $item->Cantidad * $item->Precio,
                            'IdPedidoClienteDetalle' => $item->IdPedidoClienteDetalle,
                        ];
                    }),
                    'total_unidades' => $items->sum('Cantidad'),
                    'subtotal' => $subtotal,
                ];
            })->values();

            $estados = [
                ['key' => 'Borrador', 'label' => 'Borrador', 'icon' => 'fa-pencil-alt', 'color' => 'yellow'],
                ['key' => 'Pendiente', 'label' => 'Pendiente', 'icon' => 'fa-clock', 'color' => 'blue'],
                ['key' => 'En Proceso', 'label' => 'En Proceso', 'icon' => 'fa-cog', 'color' => 'orange'],
                ['key' => 'Entregado', 'label' => 'Entregado', 'icon' => 'fa-check-circle', 'color' => 'green'],
                ['key' => 'Cancelado', 'label' => 'Cancelado', 'icon' => 'fa-times-circle', 'color' => 'red'],
            ];

            $totalGeneral = $pedido->detalles->sum(function($item) {
                return $item->Cantidad * $item->Precio;
            });

            return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/ShowPedido', [
                'pedido' => $pedido,
                'detallesAgrupados' => $detallesAgrupados,
                'estados' => $estados,
                'estadoActual' => $pedido->EstadoPedido,
                'totalGeneral' => $totalGeneral,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en show: ' . $e->getMessage());
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', 'Error al cargar el pedido: ' . $e->getMessage());
        }
    }

    /**
     * ✅ OBTENER DETALLES DEL PEDIDO PARA EL MODAL
     */
    public function getDetalles($id)
    {
        try {
            $clienteId = session('cliente_id');

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with(['detalles.producto', 'detalles.contenedor'])
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado'
                ], 404);
            }

            $detallesAgrupados = $pedido->detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                $subtotal = $items->sum(function($item) {
                    return $item->Cantidad * $item->Precio;
                });
                
                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'productos' => $items->map(function($item) {
                        return [
                            'IdProducto' => $item->IdProducto,
                            'Codigo' => $item->producto ? $item->producto->Codigo : '-',
                            'Descripcion' => $item->producto ? $item->producto->Descripcion : '-',
                            'Cantidad' => $item->Cantidad,
                            'Precio' => $item->Precio,
                            'Subtotal' => $item->Cantidad * $item->Precio,
                            'IdPedidoClienteDetalle' => $item->IdPedidoClienteDetalle,
                        ];
                    }),
                    'total_unidades' => $items->sum('Cantidad'),
                    'subtotal' => $subtotal,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'detalles' => $detallesAgrupados
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en getDetalles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los detalles: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ GENERAR PDF DEL PEDIDO
     */
    public function generarPdf($id)
    {
        try {
            if (ob_get_length()) {
                ob_end_clean();
            }

            $clienteId = session('cliente_id');
            
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with(['detalles.producto', 'detalles.contenedor'])
                ->first();

            if (!$pedido) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                    ->with('error', 'El pedido no existe.');
            }

            $totalDetalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)->count();
            if ($totalDetalles === 0) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                    ->with('error', 'El pedido no tiene productos.');
            }

            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first(['Nombre', 'NIT', 'Direccion']);

            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $pedido->IdSucursal)
                ->first(['Nombre', 'NumeroSucursal', 'Direccion']);

            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $pedido->IdOperador)
                ->first(['todos_identificador.Nombre as nombre']);

            $detallesAgrupados = $pedido->detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                $totalUnidadesContenedor = $items->sum('Cantidad');
                $subtotal = $items->sum(function($item) {
                    return $item->Cantidad * $item->Precio;
                });
                
                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'productos' => $items->map(function($item) {
                        return [
                            'IdProducto' => $item->IdProducto,
                            'Codigo' => $item->producto ? $item->producto->Codigo : '-',
                            'Descripcion' => $item->producto ? $item->producto->Descripcion : '-',
                            'Cantidad' => $item->Cantidad,
                            'Precio' => $item->Precio,
                            'Subtotal' => $item->Cantidad * $item->Precio,
                        ];
                    }),
                    'total_unidades' => $totalUnidadesContenedor,
                    'subtotal' => $subtotal,
                ];
            })->values();

            $totalUnidades = $pedido->detalles->sum('Cantidad');
            $totalContenedores = $pedido->detalles->groupBy('OrdenContenedor')->count();
            $totalGeneral = $pedido->detalles->sum(function($item) {
                return $item->Cantidad * $item->Precio;
            });

            $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(12, 10, 12);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            $y = 10;
            
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->SetXY(12, $y);
            $pdf->Cell(186, 5, $empresa->Nombre ?? 'EMPRESA', 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY(12, $y);
            $pdf->Cell(186, 3.5, $sucursal->Nombre ?? '', 0, 1, 'C');
            $y += 3.5;
            
            $pdf->SetXY(12, $y);
            $pdf->Cell(186, 3.5, $sucursal->Direccion ?? '', 0, 1, 'C');
            $y += 3.5;
            
            $pdf->SetXY(12, $y);
            $pdf->Cell(186, 3.5, "NIT: " . ($empresa->NIT ?? ''), 0, 1, 'C');
            $y += 5;
            
            $pdf->SetXY(12, $y);
            $pdf->Cell(186, 0.3, '', 'T', 1);
            $y += 4;

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetXY(12, $y);
            $pdf->Cell(186, 5, 'PEDIDO DE PRODUCTOS', 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(12, $y);
            $pdf->Cell(186, 4, 'N° ' . ($pedido->NumeroPedido ?? '000000'), 0, 1, 'C');
            $y += 5;

            $pdf->SetFont('helvetica', '', 7.5);
            
            $x = 12;
            $pdf->SetXY($x, $y);
            $pdf->Cell(30, 3.5, 'Fecha Pedido:', 0, 0, 'L');
            $pdf->SetXY($x + 30, $y);
            $pdf->Cell(50, 3.5, Carbon::parse($pedido->FechaPedido)->format('d/m/Y H:i'), 0, 0, 'L');
            $y += 3.5;
            
            if ($pedido->FechaEntrega) {
                $pdf->SetXY($x, $y);
                $pdf->Cell(30, 3.5, 'Fecha Entrega:', 0, 0, 'L');
                $pdf->SetXY($x + 30, $y);
                $pdf->Cell(50, 3.5, Carbon::parse($pedido->FechaEntrega)->format('d/m/Y'), 0, 0, 'L');
                $y += 3.5;
            }
            
            $pdf->SetXY($x, $y);
            $pdf->Cell(30, 3.5, 'Operador:', 0, 0, 'L');
            $pdf->SetXY($x + 30, $y);
            $pdf->Cell(50, 3.5, $operador->nombre ?? 'Sin operador', 0, 0, 'L');
            $y += 3.5;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell(30, 3.5, 'Sucursal:', 0, 0, 'L');
            $pdf->SetXY($x + 30, $y);
            $pdf->Cell(50, 3.5, $sucursal->Nombre ?? 'Sin sucursal', 0, 0, 'L');
            $y += 3.5;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell(30, 3.5, 'Estado:', 0, 0, 'L');
            $pdf->SetXY($x + 30, $y);
            $pdf->Cell(50, 3.5, $pedido->EstadoPedido ?? 'Pendiente', 0, 0, 'L');
            $y += 5;

            // TABLA DE PRODUCTOS
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->SetXY(12, $y);
            $pdf->Cell(6, 4, '#', 'TB', 0, 'C', 1);
            $pdf->Cell(64, 4, 'PRODUCTO', 'TB', 0, 'L', 1);
            $pdf->Cell(22, 4, 'CANTIDAD', 'TB', 0, 'C', 1);
            $pdf->Cell(28, 4, 'PRECIO UNIT.', 'TB', 0, 'C', 1);
            $pdf->Cell(36, 4, 'SUBTOTAL', 'TB', 1, 'C', 1);
            $y += 4;

            $pdf->SetFont('helvetica', '', 6.5);
            $contador = 0;
            $fill = false;
            
            foreach ($detallesAgrupados as $item) {
                $pdf->SetFont('helvetica', 'B', 6.5);
                $pdf->SetFillColor(250, 250, 250);
                $pdf->SetXY(12, $y);
                $pdf->Cell(156, 3.5, '[' . $item['Codigo'] . ']', 'LTR', 1, 'L', 1);
                $y += 3.5;
                
                $pdf->SetFont('helvetica', '', 6.5);
                $fill = !$fill;
                
                foreach ($item['productos'] as $producto) {
                    $contador++;
                    $nombreProducto = $producto['Descripcion'] ?? '-';
                    if (strlen($nombreProducto) > 38) {
                        $nombreProducto = substr($nombreProducto, 0, 35) . '...';
                    }
                    
                    $pdf->SetXY(12, $y);
                    $pdf->Cell(6, 3.5, $contador . '.', 'LR', 0, 'C', $fill);
                    $pdf->Cell(64, 3.5, $nombreProducto, 'LR', 0, 'L', $fill);
                    $pdf->Cell(22, 3.5, number_format($producto['Cantidad'], 0, ',', '.'), 'LR', 0, 'C', $fill);
                    $pdf->Cell(28, 3.5, number_format($producto['Precio'], 2, ',', '.'), 'LR', 0, 'C', $fill);
                    $pdf->Cell(36, 3.5, number_format($producto['Subtotal'], 2, ',', '.'), 'LR', 1, 'C', $fill);
                    $y += 3.5;
                    $fill = !$fill;
                }
                
                $pdf->SetFont('helvetica', 'B', 6.5);
                $pdf->SetFillColor(240, 248, 255);
                $pdf->SetXY(12, $y);
                $pdf->Cell(6, 3.5, '', 'LRB', 0, 'C', 1);
                $pdf->Cell(64, 3.5, 'TOTAL CONTENEDOR', 'LRB', 0, 'R', 1);
                $pdf->Cell(22, 3.5, number_format($item['total_unidades'], 0, ',', '.'), 'LRB', 0, 'C', 1);
                $pdf->Cell(28, 3.5, '', 'LRB', 0, 'C', 1);
                $pdf->Cell(36, 3.5, number_format($item['subtotal'], 2, ',', '.'), 'LRB', 1, 'C', 1);
                $y += 3.5;
                
                $pdf->SetFont('helvetica', '', 6.5);
            }

            $y += 3;
            
            $pdf->SetFont('helvetica', 'B', 8);
            
            $pdf->SetXY(12, $y);
            $pdf->Cell(60, 4, 'Total Contenedores: ' . number_format($totalContenedores, 0, ',', '.'), 0, 0, 'L');
            $pdf->SetXY(90, $y);
            $pdf->Cell(60, 4, 'Total Unidades: ' . number_format($totalUnidades, 0, ',', '.'), 0, 0, 'L');
            $y += 5;
            
            $pdf->SetXY(12, $y);
            $pdf->Cell(156, 0.3, '', 'T', 1);
            $y += 4;
            
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetXY(12, $y);
            $pdf->Cell(100, 6, 'TOTAL GENERAL DEL PEDIDO', 0, 0, 'R');
            $pdf->SetXY(112, $y);
            $pdf->Cell(56, 6, 'Bs. ' . number_format($totalGeneral, 2, ',', '.'), 0, 1, 'R');
            $y += 7;

            if ($pedido->Observaciones) {
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetXY(12, $y);
                $pdf->Cell(156, 3.5, 'OBSERVACIONES:', 0, 1, 'L');
                $y += 3.5;
                
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetXY(12, $y);
                $pdf->MultiCell(156, 3, $pedido->Observaciones, 0, 'L');
                $y = $pdf->GetY() + 3;
            }

            $nombreArchivo = 'Pedido_' . ($pedido->NumeroPedido ?? '000000') . '.pdf';
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            $pdf->Output($nombreArchivo, 'I');
            exit;

        } catch (\Exception $e) {
            Log::error('Error generando PDF: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ CALCULAR TOTALES
     */
    private function calcularTotales($idPedido)
    {
        $detalles = PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)->get();
        
        $totalUnidades = $detalles->sum('Cantidad');
        $totalContenedores = $detalles->groupBy('OrdenContenedor')->count();
        $totalGeneral = $detalles->sum(function($item) {
            return $item->Cantidad * $item->Precio;
        });

        return [
            'total_unidades' => $totalUnidades,
            'total_contenedores' => $totalContenedores,
            'total_general' => $totalGeneral,
        ];
    }
}