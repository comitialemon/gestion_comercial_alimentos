<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClienteDetalle;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PedidoClienteController extends Controller
{
    /**
     * Lista de pedidos
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $pedidos = PedidoCliente::porContexto()
            ->with(['cliente', 'sucursal', 'operador'])
            ->orderBy('IdPedidoCliente', 'desc')
            ->paginate(20);

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Index', [
            'pedidos' => $pedidos,
        ]);
    }

    /**
     * ✅ NUEVO PEDIDO - Menú de contenedores (táctil)
     */
    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // ✅ Obtener contenedores ACTIVOS
        $contenedores = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with(['tipoContenedor', 'gruposAnalisis'])
            ->orderBy('Codigo')
            ->get()
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
            });

        // ✅ Obtener clientes
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
        ]);
    }

    /**
     * ✅ OBTENER PRODUCTOS DE UN CONTENEDOR
     */
    public function getProductosContenedor($id)
    {
        $clienteId = session('cliente_id');

        $contenedor = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with(['gruposAnalisis', 'tipoContenedor'])
            ->findOrFail($id);

        // ✅ Obtener productos de los grupos
        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $contenedor->gruposAnalisis->pluck('IdGrupoAnalisis'))
            ->where('ActivoInactivo', 0)
            ->orderBy('IdGrupoAnalisis')
            ->orderBy('Descripcion')
            ->get();

        // ✅ Agrupar por grupo de análisis
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
     * ✅ AGREGAR PRODUCTOS AL CARRITO (nuevo contenedor)
     */
    public function agregarAlCarrito(Request $request)
    {
        $request->validate([
            'IdContenedor' => 'required|exists:operacion_pedidos_clientes_contenedor,IdContenedor',
            'productos' => 'required|array|min:1',
            'productos.*.IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'productos.*.Cantidad' => 'required|numeric|min:0.01',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // ✅ Verificar contenedor
        $contenedor = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with(['gruposAnalisis'])
            ->findOrFail($request->IdContenedor);

        // ✅ Calcular total de unidades
        $totalUnidades = array_sum(array_column($request->productos, 'Cantidad'));

        // ✅ Validar que la cantidad sea EXACTAMENTE la capacidad del contenedor
        if ((float) $totalUnidades != (float) $contenedor->CapacidadTotal) {
            return response()->json([
                'success' => false,
                'message' => "La suma de productos ({$totalUnidades}) debe ser EXACTAMENTE la capacidad del contenedor ({$contenedor->CapacidadTotal})"
            ], 400);
        }

        // ✅ Validar que los productos pertenezcan a los grupos del contenedor
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
            // ✅ Buscar o crear pedido BORRADOR
            $pedido = PedidoCliente::obtenerOCrearBorrador([
                'IdSucursal' => $sucursalId,
            ]);

            // ✅ Obtener el máximo OrdenContenedor actual
            $maxOrden = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                ->max('OrdenContenedor') ?? 0;
            $nuevoOrden = $maxOrden + 1;

            // ✅ Guardar los productos con el nuevo orden
            foreach ($request->productos as $producto) {
                PedidoClienteDetalle::create([
                    'IdPedidoCliente' => $pedido->IdPedidoCliente,
                    'IdContenedor' => $request->IdContenedor,
                    'IdProducto' => $producto['IdProducto'],
                    'Cantidad' => $producto['Cantidad'],
                    'OrdenContenedor' => $nuevoOrden,
                ]);
            }

            // ✅ Actualizar totales
            $totales = $this->calcularTotales($pedido->IdPedidoCliente);
            $pedido->update([
                'TotalUnidades' => $totales['total_unidades'],
                'TotalContenedores' => $totales['total_contenedores'],
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

            // ✅ Eliminar TODOS los productos del mismo contenedor (mismo orden)
            $orden = $detalle->OrdenContenedor;
            PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                ->where('OrdenContenedor', $orden)
                ->delete();

            // ✅ Actualizar totales
            $totales = $this->calcularTotales($pedido->IdPedidoCliente);
            $pedido->update([
                'TotalUnidades' => $totales['total_unidades'],
                'TotalContenedores' => $totales['total_contenedores'],
            ]);

            // ✅ Si no quedan productos, eliminar el pedido borrador
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

            // ✅ Agrupar por OrdenContenedor
            $detallesAgrupados = $pedido->detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
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

            // ✅ Datos adicionales
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

            return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Review', [
                'pedido' => $pedido,
                'detallesAgrupados' => $detallesAgrupados,
                'clienteNombre' => $cliente->Nombre ?? 'Sin cliente',
                'sucursalNombre' => $sucursal->Nombre ?? 'Sin sucursal',
                'operadorNombre' => $operador->nombre ?? 'Sin operador',
            ]);

        } catch (\Exception $e) {
            Log::error('Error en review: ' . $e->getMessage());
            return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                ->with('error', 'Error al cargar la revisión: ' . $e->getMessage());
        }
    }

    /**
     * ✅ FINALIZAR PEDIDO - VALIDACIÓN CORREGIDA CON diffInDays()
     */
    public function finalizarPedido(Request $request, $idPedido)
    {
        // ✅ VALIDACIÓN CON FECHA DE ENTREGA MÍNIMO 1 DÍA DESPUÉS DE HOY
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
            'FechaEntrega' => [
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    $fechaEntrega = Carbon::parse($value);
                    $hoy = Carbon::now('America/La_Paz');
                    
                    // ✅ Calcular diferencia en días
                    $diasDiferencia = $hoy->diffInDays($fechaEntrega, false);
                    
                    // ✅ Si la diferencia es menor a 1 día (0 o negativa), falla
                    if ($diasDiferencia < 1) {
                        $fail('La fecha de entrega debe ser mínimo 1 día después de hoy.');
                    }
                }
            ],
            'Observaciones' => 'nullable|string|max:500',
        ]);

        // ✅ VALIDAR QUE LA SUCURSAL PERTENEZCA AL CLIENTE
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

            // ✅ Verificar que todos los contenedores estén completos
            $detalles = PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)->get();
            $ordenes = $detalles->groupBy('OrdenContenedor');
            
            foreach ($ordenes as $orden => $items) {
                $total = $items->sum('Cantidad');
                $contenedor = Contenedor::find($items->first()->IdContenedor);
                $capacidad = $contenedor ? $contenedor->CapacidadTotal : 0;
                
                if ((float) $total != (float) $capacidad) {
                    return response()->json([
                        'success' => false,
                        'message' => "El contenedor #{$orden} tiene {$total} unidades, pero debe tener exactamente {$capacidad} unidades"
                    ], 400);
                }
            }

            // ✅ OBTENER NUEVO NÚMERO DE PEDIDO POR SUCURSAL
            $maxNumero = PedidoCliente::where('IdCliente', $request->IdCliente)
                ->where('IdSucursal', $request->IdSucursal)
                ->where('NumeroPedido', '!=', '0')
                ->whereNotNull('NumeroPedido')
                ->max(DB::raw('CAST(NumeroPedido AS UNSIGNED)')) ?? 0;

            $nuevoNumero = $maxNumero + 1;
            $numeroPedidoFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);

            // 🔒 VERIFICAR QUE NO EXISTA
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

            // ✅ ACTUALIZAR PEDIDO
            $pedido->update([
                'IdCliente' => $request->IdCliente,
                'IdSucursal' => $request->IdSucursal,
                'NumeroPedido' => $numeroPedidoFormateado,
                'FechaEntrega' => $request->FechaEntrega,
                'Observaciones' => $request->Observaciones,
                'ActivoInactivo' => 1,
                'EstadoPedido' => 'Pendiente',
                'FechaPedido' => Carbon::now('America/La_Paz'),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            Log::info('Pedido finalizado correctamente', [
                'IdPedidoCliente' => $pedido->IdPedidoCliente,
                'NumeroPedido' => $pedido->NumeroPedido,
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pedido finalizado correctamente',
                'pedido_id' => $pedido->IdPedidoCliente,
                'numero_pedido' => $pedido->NumeroPedido,
                'pdf_url' => route('operacion.pedidos-clientes.pedidos.pdf', $pedido->IdPedidoCliente)
            ]);

        } catch (\Exception $e) {
            Log::error('Error al finalizar pedido: ' . $e->getMessage());
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
                    'total_unidades' => $items->sum('Cantidad'),
                ];
            })->values();

            $estados = [
                ['key' => 'Borrador', 'label' => 'Borrador', 'icon' => 'fa-pencil-alt', 'color' => 'yellow'],
                ['key' => 'Pendiente', 'label' => 'Pendiente', 'icon' => 'fa-clock', 'color' => 'blue'],
                ['key' => 'En Proceso', 'label' => 'En Proceso', 'icon' => 'fa-cog', 'color' => 'orange'],
                ['key' => 'Entregado', 'label' => 'Entregado', 'icon' => 'fa-check-circle', 'color' => 'green'],
                ['key' => 'Cancelado', 'label' => 'Cancelado', 'icon' => 'fa-times-circle', 'color' => 'red'],
            ];

            return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Show', [
                'pedido' => $pedido,
                'detallesAgrupados' => $detallesAgrupados,
                'estados' => $estados,
                'estadoActual' => $pedido->EstadoPedido,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en show: ' . $e->getMessage());
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', 'Error al cargar el pedido: ' . $e->getMessage());
        }
    }

    /**
     * ✅ GENERAR PDF DEL PEDIDO
     */
    public function generarPdf($id)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            
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
                ->where('IdClienteSucursal', $sucursalId)
                ->first(['Nombre', 'NumeroSucursal', 'Direccion', 'Telefono']);

            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $pedido->IdOperador)
                ->first(['todos_identificador.Nombre as nombre']);

            $detallesAgrupados = $pedido->detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                
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
                        ];
                    }),
                    'total_unidades' => $items->sum('Cantidad'),
                ];
            })->values();

            $totalUnidades = $pedido->detalles->sum('Cantidad');
            $totalContenedores = $pedido->detalles->groupBy('OrdenContenedor')->count();

            $pdf = new \TCPDF('P', 'mm', array(80, 300), true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(5, 5, 5);
            $pdf->SetAutoPageBreak(true, 10);
            $pdf->AddPage();

            $y = 10;
            
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 6, $empresa->Nombre ?? 'EMPRESA', 0, 1, 'C');
            $y += 6;
            
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 4, $sucursal->Nombre ?? '', 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 4, $sucursal->Direccion ?? '', 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 4, "Tel.: " . ($sucursal->Telefono ?? ''), 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 2, '----------------------------------------', 0, 1, 'C');
            $y += 4;

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 5, 'PEDIDO DE PRODUCTOS', 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 4, 'N° ' . ($pedido->NumeroPedido ?? '000000'), 0, 1, 'C');
            $y += 6;

            $pdf->SetFont('helvetica', '', 8);
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 4, 'Fecha Pedido:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 4, Carbon::parse($pedido->FechaPedido)->format('d/m/Y H:i'), 0, 1, 'R');
            $y += 4;
            
            if ($pedido->FechaEntrega) {
                $pdf->SetXY(5, $y);
                $pdf->Cell(40, 4, 'Fecha Entrega:', 0, 0, 'L');
                $pdf->SetXY(45, $y);
                $pdf->Cell(30, 4, Carbon::parse($pedido->FechaEntrega)->format('d/m/Y'), 0, 1, 'R');
                $y += 4;
            }
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 4, 'Operador:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 4, $operador->nombre ?? 'Sin operador', 0, 1, 'R');
            $y += 4;
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 4, 'Estado:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 4, $pedido->EstadoPedido ?? 'Pendiente', 0, 1, 'R');
            $y += 6;

            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY(5, $y);
            $pdf->Cell(5, 4, '#', 0, 0, 'C');
            $pdf->Cell(40, 4, 'PRODUCTO', 0, 0, 'L');
            $pdf->Cell(25, 4, 'CANTIDAD', 0, 1, 'R');
            
            $pdf->SetXY(5, $y + 1);
            $pdf->Cell(70, 1, '', 'T', 1);
            $y += 4;

            $pdf->SetFont('helvetica', '', 7);
            $contador = 0;
            
            foreach ($detallesAgrupados as $item) {
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetXY(5, $y);
                $pdf->Cell(70, 3, '#' . $item['Orden'] . ' ' . $item['Codigo'] . ' (Cap: ' . $item['CapacidadTotal'] . ' und)', 0, 1, 'L');
                $y += 3;
                
                $pdf->SetFont('helvetica', '', 7);
                
                foreach ($item['productos'] as $producto) {
                    $contador++;
                    $pdf->SetXY(5, $y);
                    $pdf->Cell(5, 4, $contador . '.', 0, 0, 'C');
                    
                    $nombreProducto = $producto['Descripcion'] ?? '-';
                    if (strlen($nombreProducto) > 25) {
                        $nombreProducto = substr($nombreProducto, 0, 23) . '...';
                    }
                    
                    $pdf->SetXY(10, $y);
                    $pdf->Cell(35, 4, $nombreProducto, 0, 0, 'L');
                    
                    $pdf->SetXY(45, $y);
                    $pdf->Cell(30, 4, number_format($producto['Cantidad'], 0, ',', '.'), 0, 1, 'R');
                    
                    $y += 4;
                }
            }

            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 1, '', 'T', 1);
            $y += 3;

            $pdf->SetFont('helvetica', 'B', 8);
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 5, 'Total Contenedores:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 5, number_format($totalContenedores, 0, ',', '.'), 0, 1, 'R');
            $y += 5;
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 5, 'Total Unidades:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 5, number_format($totalUnidades, 0, ',', '.'), 0, 1, 'R');
            $y += 6;

            if ($pedido->Observaciones) {
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetXY(5, $y);
                $pdf->Cell(70, 4, 'OBSERVACIONES:', 0, 1, 'L');
                $y += 4;
                
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetXY(5, $y);
                $pdf->MultiCell(70, 3, $pedido->Observaciones, 0, 'L');
                $y = $pdf->GetY() + 2;
            }

            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 2, '----------------------------------------', 0, 1, 'C');
            $y += 4;
            
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 3, 'Pedido generado el ' . Carbon::now('America/La_Paz')->format('d/m/Y H:i:s'), 0, 1, 'C');
            $y += 3;
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 3, 'Gracias por su pedido', 0, 1, 'C');

            $nombreArchivo = 'Pedido_' . ($pedido->NumeroPedido ?? '000000') . '.pdf';
            $pdf->Output($nombreArchivo, 'I');
            exit;

        } catch (\Exception $e) {
            Log::error('Error generando PDF: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->with('error', 'Error al generar el PDF: ' . $e->getMessage());
        }
    }

    /**
     * ✅ CALCULAR TOTALES
     */
    private function calcularTotales($idPedido)
    {
        $totalUnidades = PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)->sum('Cantidad');
        $totalContenedores = PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)
            ->distinct('OrdenContenedor')
            ->count('OrdenContenedor');

        return [
            'total_unidades' => $totalUnidades,
            'total_contenedores' => $totalContenedores,
        ];
    }
}