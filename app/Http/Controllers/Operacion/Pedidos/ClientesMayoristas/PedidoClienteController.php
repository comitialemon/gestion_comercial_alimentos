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
        $pedidos = PedidoCliente::porContexto()
            ->orderBy('IdPedidoCliente', 'desc')
            ->get();
            
        // ✅ CORREGIDO: Ruta correcta
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

        // ✅ Obtener contenedores ACTIVOS (ActivoInactivo = 1)
        $contenedores = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with(['detalles.producto'])
            ->orderBy('Nombre')
            ->get()
            ->map(function($contenedor) {
                return [
                    'IdContenedor' => $contenedor->IdContenedor,
                    'Codigo' => $contenedor->Codigo,
                    'Nombre' => $contenedor->Nombre,
                    'CapacidadTotal' => $contenedor->CapacidadTotal,
                    'CapacidadTotalFormateada' => number_format($contenedor->CapacidadTotal, 0, ',', '.'),
                    'cantidad_productos' => $contenedor->detalles->count(),
                    'productos' => $contenedor->detalles->map(function($detalle) {
                        return [
                            'IdProducto' => $detalle->IdProducto,
                            'Codigo' => $detalle->producto ? $detalle->producto->Codigo : '-',
                            'Descripcion' => $detalle->producto ? $detalle->producto->Descripcion : '-',
                            'Cantidad' => $detalle->Cantidad,
                            'CantidadFormateada' => number_format($detalle->Cantidad, 0, ',', '.'),
                        ];
                    }),
                ];
            });

        // ✅ Obtener clientes para el selector
        $clientes = Cliente::where('IdCliente', $clienteId)
            ->get(['IdCliente as id', 'Nombre']);

        // ✅ Obtener sucursales para el selector
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre']);

        // ✅ Buscar si existe un pedido BORRADOR para este operador
        $pedidoBorrador = PedidoCliente::where('IdCliente', $clienteId)
            ->where('IdOperador', $operadorId)
            ->where('ActivoInactivo', 0)
            ->with(['detalles.producto', 'detalles.contenedor'])
            ->first();

        // ✅ Si hay borrador, cargar sus detalles al carrito
        $carrito = [];
        if ($pedidoBorrador) {
            $carrito = $pedidoBorrador->detalles->groupBy('IdContenedor')->map(function($items, $contenedorId) {
                $contenedor = $items->first()->contenedor;
                return [
                    'IdContenedor' => $contenedorId,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Nombre' => $contenedor ? $contenedor->Nombre : '-',
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
        }

        // ✅ CORREGIDO: Ruta correcta
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
     * ✅ OBTENER PRODUCTOS DE UN CONTENEDOR (para el modal)
     */
    public function getProductosContenedor($id)
    {
        $contenedor = Contenedor::where('IdContenedor', $id)
            ->where('ActivoInactivo', 1)
            ->with(['detalles.producto'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'Nombre' => $contenedor->Nombre,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'productos' => $contenedor->detalles->map(function($detalle) {
                    return [
                        'IdProducto' => $detalle->IdProducto,
                        'Codigo' => $detalle->producto ? $detalle->producto->Codigo : '-',
                        'Descripcion' => $detalle->producto ? $detalle->producto->Descripcion : '-',
                        'CantidadMaxima' => $detalle->Cantidad,
                    ];
                }),
            ]
        ]);
    }

    /**
     * ✅ AGREGAR PRODUCTOS AL CARRITO (desde el modal)
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

        // ✅ Verificar que el contenedor existe y está activo
        $contenedor = Contenedor::where('IdContenedor', $request->IdContenedor)
            ->where('ActivoInactivo', 1)
            ->firstOrFail();

        // ✅ Calcular total de unidades seleccionadas
        $totalUnidades = array_sum(array_column($request->productos, 'Cantidad'));

        // ✅ Validar que no exceda la capacidad del contenedor
        if ($totalUnidades > $contenedor->CapacidadTotal) {
            return response()->json([
                'success' => false,
                'message' => "La suma de productos ({$totalUnidades}) excede la capacidad del contenedor ({$contenedor->CapacidadTotal})"
            ], 400);
        }

        DB::beginTransaction();

        try {
            // ✅ Buscar o crear pedido BORRADOR
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdOperador', $operadorId)
                ->where('ActivoInactivo', 0)
                ->first();

            if (!$pedido) {
                // ✅ Generar número de pedido
                $maxNumero = PedidoCliente::where('IdCliente', $clienteId)
                    ->max('NumeroPedido');
                $numeroPedido = $maxNumero ? intval($maxNumero) + 1 : 1;
                $numeroPedidoFormateado = str_pad($numeroPedido, 6, '0', STR_PAD_LEFT);

                $pedido = PedidoCliente::create([
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'IdOperador' => $operadorId,
                    'NumeroPedido' => $numeroPedidoFormateado,
                    'FechaPedido' => Carbon::now('America/La_Paz'),
                    'FechaEntrega' => null,
                    'TotalUnidades' => 0,
                    'TotalContenedores' => 0,
                    'ActivoInactivo' => 0,
                    'EstadoPedido' => 'Borrador',
                    'Observaciones' => null,
                    'FechaInserta' => Carbon::now('America/La_Paz'),
                ]);
            }

            // ✅ Obtener el máximo OrdenContenedor actual
            $maxOrden = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                ->max('OrdenContenedor') ?? 0;
            $nuevoOrden = $maxOrden + 1;

            // ✅ Agregar productos al detalle
            foreach ($request->productos as $producto) {
                // ✅ Verificar si el producto ya existe para este contenedor en el pedido
                $existe = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                    ->where('IdContenedor', $request->IdContenedor)
                    ->where('IdProducto', $producto['IdProducto'])
                    ->exists();

                if ($existe) {
                    // ✅ Actualizar cantidad si ya existe
                    PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                        ->where('IdContenedor', $request->IdContenedor)
                        ->where('IdProducto', $producto['IdProducto'])
                        ->update([
                            'Cantidad' => $producto['Cantidad']
                        ]);
                } else {
                    // ✅ Crear nuevo detalle
                    PedidoClienteDetalle::create([
                        'IdPedidoCliente' => $pedido->IdPedidoCliente,
                        'IdContenedor' => $request->IdContenedor,
                        'IdProducto' => $producto['IdProducto'],
                        'Cantidad' => $producto['Cantidad'],
                        'OrdenContenedor' => $nuevoOrden,
                    ]);
                }
            }

            // ✅ Actualizar totales del pedido
            $totales = $this->calcularTotales($pedido->IdPedidoCliente);
            $pedido->update([
                'TotalUnidades' => $totales['total_unidades'],
                'TotalContenedores' => $totales['total_contenedores'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Productos agregados al carrito correctamente',
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
     * ✅ ELIMINAR PRODUCTO DEL CARRITO
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

            $detalle->delete();

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
                'message' => 'Producto eliminado del carrito',
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

            // ✅ Eliminar todos los detalles
            PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)->delete();

            // ✅ Eliminar el pedido borrador
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
     * ✅ FINALIZAR PEDIDO (desde Review) - RESPUESTA JSON
     */
    public function finalizarPedido(Request $request, $idPedido)
    {
        $request->validate([
            'IdCliente' => 'required|exists:todos_cliente,IdCliente',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'FechaEntrega' => 'nullable|date',
            'Observaciones' => 'nullable|string|max:500',
        ]);

        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');

            // ✅ Buscar el pedido con filtros de seguridad
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $idPedido)
                ->where('ActivoInactivo', 0) // Solo borradores
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido no existe o ya fue finalizado.'
                ], 404);
            }

            // ✅ Verificar que tenga productos
            $totalDetalles = PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)->count();
            if ($totalDetalles === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido no tiene productos. Agregue productos antes de finalizar.'
                ], 400);
            }

            // ✅ Generar número de pedido si no tiene
            if (!$pedido->NumeroPedido || $pedido->NumeroPedido === '0') {
                $maxNumero = PedidoCliente::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->max('NumeroPedido') ?? 0;
                
                $numeroPedido = intval($maxNumero) + 1;
                $pedido->NumeroPedido = str_pad($numeroPedido, 6, '0', STR_PAD_LEFT);
            }

            // ✅ Actualizar pedido
            $pedido->update([
                'IdCliente' => $request->IdCliente,
                'IdSucursal' => $request->IdSucursal,
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
                'IdSucursal' => $sucursalId
            ]);

            // ✅ DEVOLVER JSON CON LA URL DEL PDF
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
            $sucursalId = session('cliente_sucursal_id');

            // ✅ Buscar con filtros de seguridad
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with(['cliente', 'sucursal', 'operador', 'detalles.producto', 'detalles.contenedor'])
                ->first();

            // ✅ Si no existe, redirigir
            if (!$pedido) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                    ->with('error', 'El pedido no existe o no tiene acceso a él.');
            }

            // ✅ Agrupar detalles por contenedor
            $detallesAgrupados = $pedido->detalles->groupBy('IdContenedor')->map(function($items, $contenedorId) {
                $contenedor = $items->first()->contenedor;
                return [
                    'IdContenedor' => $contenedorId,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Nombre' => $contenedor ? $contenedor->Nombre : '-',
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

            // ✅ Estados para la línea de proceso
            $estados = [
                ['key' => 'Borrador', 'label' => 'Borrador', 'icon' => 'fa-pencil-alt', 'color' => 'yellow'],
                ['key' => 'Pendiente', 'label' => 'Pendiente', 'icon' => 'fa-clock', 'color' => 'blue'],
                ['key' => 'En Proceso', 'label' => 'En Proceso', 'icon' => 'fa-cog', 'color' => 'orange'],
                ['key' => 'Entregado', 'label' => 'Entregado', 'icon' => 'fa-check-circle', 'color' => 'green'],
                ['key' => 'Cancelado', 'label' => 'Cancelado', 'icon' => 'fa-times-circle', 'color' => 'red'],
            ];

            $estadoActual = $pedido->EstadoPedido;

            return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Show', [
                'pedido' => $pedido,
                'detallesAgrupados' => $detallesAgrupados,
                'estados' => $estados,
                'estadoActual' => $estadoActual,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en show de pedido: ' . $e->getMessage());
            
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', 'Error al cargar el pedido: ' . $e->getMessage());
        }
    }

    /**
     * ✅ CALCULAR TOTALES DEL PEDIDO
     */
    private function calcularTotales($idPedido)
    {
        $totalUnidades = PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)->sum('Cantidad');
        $totalContenedores = PedidoClienteDetalle::where('IdPedidoCliente', $idPedido)
            ->distinct('IdContenedor')
            ->count('IdContenedor');

        return [
            'total_unidades' => $totalUnidades,
            'total_contenedores' => $totalContenedores,
        ];
    }

    /**
     * ✅ REVISAR PEDIDO (Vista de revisión antes de finalizar)
     */
    public function review($id)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');
            
            // ✅ Buscar el pedido con filtros de seguridad
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->where('ActivoInactivo', 0) // Solo borradores
                ->with(['detalles.producto', 'detalles.contenedor'])
                ->first();

            // ✅ Si no existe, redirigir al create con mensaje
            if (!$pedido) {
                Log::warning('Pedido no encontrado en review', [
                    'IdPedidoCliente' => $id,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'IdOperador' => $operadorId
                ]);
                
                return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                    ->with('error', 'El pedido no existe, ya fue finalizado o no tiene acceso a él.');
            }

            // ✅ Verificar que tenga productos
            $totalDetalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)->count();
            if ($totalDetalles === 0) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                    ->with('error', 'El pedido no tiene productos. Agregue productos antes de revisar.');
            }

            // ✅ Obtener nombre del cliente (operador logueado)
            $cliente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first(['Nombre']);
            
            // ✅ Obtener nombre de la sucursal
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $sucursalId)
                ->first(['Nombre']);
            
            // ✅ Obtener nombre del operador
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $operadorId)
                ->first(['todos_identificador.Nombre as nombre']);

            // ✅ Agrupar detalles por contenedor
            $detallesAgrupados = $pedido->detalles->groupBy('IdContenedor')->map(function($items, $contenedorId) {
                $contenedor = $items->first()->contenedor;
                return [
                    'IdContenedor' => $contenedorId,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Nombre' => $contenedor ? $contenedor->Nombre : '-',
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

            return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Review', [
                'pedido' => $pedido,
                'detallesAgrupados' => $detallesAgrupados,
                'clienteNombre' => $cliente->Nombre ?? 'Sin cliente',
                'sucursalNombre' => $sucursal->Nombre ?? 'Sin sucursal',
                'operadorNombre' => $operador->nombre ?? 'Sin operador',
            ]);

        } catch (\Exception $e) {
            Log::error('Error en review de pedido: ' . $e->getMessage());
            
            return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                ->with('error', 'Error al cargar la revisión del pedido: ' . $e->getMessage());
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
            $operadorId = session('operador_id');
            
            // ✅ BUSCAR EL PEDIDO CON FILTROS DE SEGURIDAD
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with(['detalles.producto', 'detalles.contenedor'])
                ->first();

            // ✅ SI NO EXISTE, REDIRIGIR CON MENSAJE
            if (!$pedido) {
                Log::warning('Pedido no encontrado para PDF', [
                    'IdPedidoCliente' => $id,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId
                ]);
                
                return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                    ->with('error', 'El pedido no existe o no tiene acceso a él.');
            }

            // ✅ VERIFICAR QUE TENGA PRODUCTOS
            $totalDetalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)->count();
            if ($totalDetalles === 0) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                    ->with('error', 'El pedido no tiene productos para generar el PDF.');
            }

            // ✅ OBTENER DATOS DE LA EMPRESA
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first(['Nombre', 'NIT', 'Direccion']);

            // ✅ OBTENER DATOS DE LA SUCURSAL
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $sucursalId)
                ->first(['Nombre', 'NumeroSucursal', 'Direccion', 'Telefono']);

            // ✅ OBTENER DATOS DEL OPERADOR
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
                ->where('todos_operador.IdOperador', $operadorId)
                ->first(['todos_identificador.Nombre as nombre']);

            // ✅ AGRUPAR DETALLES POR CONTENEDOR
            $detallesAgrupados = $pedido->detalles->groupBy('IdContenedor')->map(function($items, $contenedorId) {
                $contenedor = $items->first()->contenedor;
                return [
                    'IdContenedor' => $contenedorId,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Nombre' => $contenedor ? $contenedor->Nombre : '-',
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

            // ✅ CALCULAR TOTALES
            $totalUnidades = $pedido->detalles->sum('Cantidad');
            $totalContenedores = $pedido->detalles->groupBy('IdContenedor')->count();

            // =============================================
            // GENERAR PDF
            // =============================================
            
            // Configurar PDF (80mm de ancho - ticket térmico)
            $pdf = new \TCPDF('P', 'mm', array(80, 300), true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(5, 5, 5);
            $pdf->SetAutoPageBreak(true, 10);
            $pdf->AddPage();

            // =============================================
            // CABECERA
            // =============================================
            $y = 10;
            
            // Empresa
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 6, $empresa->Nombre ?? 'EMPRESA', 0, 1, 'C');
            $y += 6;
            
            // Sucursal
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
            
            // Línea separadora
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 2, '----------------------------------------', 0, 1, 'C');
            $y += 4;

            // Título
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 5, 'PEDIDO DE PRODUCTOS', 0, 1, 'C');
            $y += 5;
            
            // Número de pedido
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 4, 'N° ' . ($pedido->NumeroPedido ?? '000000'), 0, 1, 'C');
            $y += 6;

            // =============================================
            // INFORMACIÓN DEL PEDIDO
            // =============================================
            $pdf->SetFont('helvetica', '', 8);
            
            // Fecha
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 4, 'Fecha:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 4, Carbon::parse($pedido->FechaPedido)->format('d/m/Y H:i'), 0, 1, 'R');
            $y += 4;
            
            // Operador
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 4, 'Operador:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 4, $operador->nombre ?? 'Sin operador', 0, 1, 'R');
            $y += 4;
            
            // Sucursal
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 4, 'Sucursal:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 4, $sucursal->Nombre ?? '', 0, 1, 'R');
            $y += 4;
            
            // Estado
            $pdf->SetXY(5, $y);
            $pdf->Cell(40, 4, 'Estado:', 0, 0, 'L');
            $pdf->SetXY(45, $y);
            $pdf->Cell(30, 4, $pedido->EstadoPedido ?? 'Pendiente', 0, 1, 'R');
            $y += 6;

            // =============================================
            // TABLA DE PRODUCTOS
            // =============================================
            
            // Encabezados
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
                // Mostrar contenedor
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetXY(5, $y);
                $pdf->Cell(70, 3, $item['Codigo'] . ' - ' . $item['Nombre'], 0, 1, 'L');
                $y += 3;
                
                $pdf->SetFont('helvetica', '', 7);
                
                foreach ($item['productos'] as $producto) {
                    $contador++;
                    $pdf->SetXY(5, $y);
                    $pdf->Cell(5, 4, $contador . '.', 0, 0, 'C');
                    
                    $nombreProducto = $producto['Descripcion'] ?? '-';
                    // ✅ TRUNCAR NOMBRE PARA QUE NO SE SALGA
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

            // Línea separadora
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 1, '', 'T', 1);
            $y += 3;

            // =============================================
            // TOTALES
            // =============================================
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

            // =============================================
            // OBSERVACIONES
            // =============================================
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

            // =============================================
            // PIE DE PÁGINA
            // =============================================
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 2, '----------------------------------------', 0, 1, 'C');
            $y += 4;
            
            $pdf->SetFont('helvetica', '', 6);
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 3, 'Pedido generado el ' . Carbon::now('America/La_Paz')->format('d/m/Y H:i:s'), 0, 1, 'C');
            $y += 3;
            
            $pdf->SetXY(5, $y);
            $pdf->Cell(70, 3, 'Gracias por su pedido', 0, 1, 'C');

            // =============================================
            // SALIDA DEL PDF
            // =============================================
            $nombreArchivo = 'Pedido_' . ($pedido->NumeroPedido ?? '000000') . '.pdf';
            $pdf->Output($nombreArchivo, 'I');
            exit;

        } catch (\Exception $e) {
            Log::error('Error generando PDF del pedido: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()->back()->with('error', 'Error al generar el PDF: ' . $e->getMessage());
        }
    }


}