<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClienteDetalle;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PrecioProducto;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ClienteGrupo;

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
     * ✅ OBTENER MÍNIMOS POR GRUPO DEL CLIENTE
     */
    private function obtenerMinimosGruposCliente($idIdentificador, $clienteId, $sucursalId)
    {
        return ClienteGrupo::where('IdIdentificador', $idIdentificador)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->with('grupoAnalisis')
            ->get()
            ->map(function($item) {
                return [
                    'IdGrupoAnalisis' => $item->IdGrupoAnalisis,
                    'NombreGrupo' => $item->grupoAnalisis ? $item->grupoAnalisis->Grupo : 'Sin grupo',
                    'CantidadMinimaGrupo' => (float) $item->CantidadMinimaGrupo,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * ✅ CALCULAR PROGRESO DE GRUPOS
     */
    private function calcularProgresoGrupos($pedidoBorrador, $idIdentificador, $clienteId, $sucursalId)
    {
        if (!$pedidoBorrador) {
            return [];
        }

        // 1. Obtener detalles con el grupo del producto
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('pedidos_clientes_detalle as d')
            ->join('inventario_productodetalle as p', 'd.IdProducto', '=', 'p.IdProducto')
            ->where('d.IdPedidoCliente', $pedidoBorrador->IdPedidoCliente)
            ->select('d.Cantidad', 'p.IdGrupoAnalisis')
            ->get();

        // 2. Acumular por grupo
        $acumulado = [];
        foreach ($detalles as $detalle) {
            $grupoId = $detalle->IdGrupoAnalisis;
            if (!isset($acumulado[$grupoId])) {
                $acumulado[$grupoId] = 0;
            }
            $acumulado[$grupoId] += (float) $detalle->Cantidad;
        }

        // 3. Obtener mínimos
        $minimos = ClienteGrupo::where('IdIdentificador', $idIdentificador)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->with('grupoAnalisis')
            ->get();

        // 4. Combinar
        $progreso = [];
        foreach ($minimos as $minimo) {
            $grupoId = $minimo->IdGrupoAnalisis;
            $cantidadPedida = $acumulado[$grupoId] ?? 0;
            $cantidadMinima = (float) $minimo->CantidadMinimaGrupo;

            $progreso[] = [
                'IdGrupoAnalisis' => $grupoId,
                'NombreGrupo' => $minimo->grupoAnalisis ? $minimo->grupoAnalisis->Grupo : 'Sin grupo',
                'CantidadPedida' => $cantidadPedida,
                'CantidadMinima' => $cantidadMinima,
                'Cumple' => $cantidadPedida >= $cantidadMinima,
                'Falta' => max(0, $cantidadMinima - $cantidadPedida),
            ];
        }

        return $progreso;
    }

    /**
     * ✅ OBTENER EL PRECIO SEGÚN EL TIPO
     */
    private function obtenerPrecioSegunTipo($precio, $tipoPrecio)
    {
        if (!$precio) return null;

        return $tipoPrecio === 'con_factura' 
            ? $precio->PrecioConFactura 
            : $precio->PrecioSinFactura;
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
     * ✅ NUEVO PEDIDO - Menú de contenedores
     * 
     * Recibe tipo_precio del query (default: sin_factura)
     */
    public function create(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        // ✅ Leer tipo de precio del query
        $tipoPrecio = $request->get('tipo_precio', 'sin_factura');
        
        // ✅ Validar
        if (!in_array($tipoPrecio, ['sin_factura', 'con_factura'])) {
            $tipoPrecio = 'sin_factura';
        }
        
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
            ->filter(function($contenedor) use ($idIdentificador, $clienteId, $sucursalId) {
                $gruposIds = $contenedor->gruposAnalisis->pluck('IdGrupoAnalisis')->toArray();
                
                if (empty($gruposIds)) return false;

                $tieneMinimo = ClienteGrupo::where('IdIdentificador', $idIdentificador)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->whereIn('IdGrupoAnalisis', $gruposIds)
                    ->where('ActivoInactivo', 1)
                    ->exists();

                return $tieneMinimo;
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
            // ✅ Si el borrador tiene un tipo de precio diferente al solicitado, actualizar
            if ($pedidoBorrador->TipoPrecio !== $tipoPrecio) {
                $pedidoBorrador->update(['TipoPrecio' => $tipoPrecio]);
            }
            
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
                            'Precio' => $item->Precio,
                            'IdGrupoAnalisis' => $item->producto ? $item->producto->IdGrupoAnalisis : null,
                            'IdPedidoClienteDetalle' => $item->IdPedidoClienteDetalle,
                        ];
                    }),
                    'total_unidades' => $total,
                    'esta_completo' => $total == ($contenedor ? $contenedor->CapacidadTotal : 0),
                ];
            })->values();
        }

        // ✅ MÍNIMOS POR GRUPO
        $minimosGrupos = $this->obtenerMinimosGruposCliente($idIdentificador, $clienteId, $sucursalId);

        // ✅ PROGRESO INICIAL
        $progresoInicial = $this->calcularProgresoGrupos($pedidoBorrador, $idIdentificador, $clienteId, $sucursalId);

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Create', [
            'contenedores' => $contenedores,
            'clientes' => $clientes,
            'sucursales' => $sucursales,
            'pedidoBorrador' => $pedidoBorrador,
            'carrito' => $carrito,
            'sucursalDefault' => $sucursalId,
            'idIdentificador' => $idIdentificador,
            'nombreOperador' => $this->getNombreOperador(),
            'minimosGrupos' => $minimosGrupos,
            'progresoInicial' => $progresoInicial,
            'tipoPrecio' => $tipoPrecio,   // ✅ NUEVO
        ]);
    }

    /**
     * ✅ OBTENER PRODUCTOS DE UN CONTENEDOR CON PRECIOS
     * 
     * Recibe tipo_precio del query.
     * Filtra productos que tengan precio del tipo elegido.
     * Devuelve el precio según el tipo.
     */
    public function getProductosContenedorConPrecios(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // ✅ Leer tipo de precio
        $tipoPrecio = $request->get('tipo_precio', 'sin_factura');
        
        if (!in_array($tipoPrecio, ['sin_factura', 'con_factura'])) {
            $tipoPrecio = 'sin_factura';
        }
        
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

        // ✅ 1. OBTENER LOS GRUPOS DEL CONTENEDOR CON MÍNIMO
        $gruposIds = $contenedor->gruposAnalisis->pluck('IdGrupoAnalisis')->toArray();

        $gruposConMinimo = ClienteGrupo::where('IdIdentificador', $idIdentificador)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->whereIn('IdGrupoAnalisis', $gruposIds)
            ->where('ActivoInactivo', 1)
            ->pluck('IdGrupoAnalisis')
            ->toArray();

        if (empty($gruposConMinimo)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'IdContenedor' => $contenedor->IdContenedor,
                    'Codigo' => $contenedor->Codigo,
                    'CapacidadTotal' => $contenedor->CapacidadTotal,
                    'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                    'productos_agrupados' => [],
                    'total_productos' => 0,
                    'idIdentificador' => $idIdentificador,
                    'tipoPrecio' => $tipoPrecio,
                    'mensaje' => 'Este contenedor no tiene grupos con mínimo configurado',
                ]
            ]);
        }

        // ✅ 2. OBTENER TODOS LOS PRODUCTOS DE LOS GRUPOS CON MÍNIMO
        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $gruposConMinimo)
            ->where('ActivoInactivo', 0)
            ->orderBy('IdGrupoAnalisis')
            ->orderBy('Descripcion')
            ->get();

        // ✅ 3. OBTENER PRECIOS
        $precios = PrecioProducto::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdIdentificador', $idIdentificador)
            ->where('ActivoInactivo', 1)
            ->get()
            ->keyBy('IdProducto');

        // ✅ 4. AGRUPAR POR GRUPO (todos los productos, con o sin precio)
        $productosAgrupados = $productos->groupBy('IdGrupoAnalisis')->map(function($items, $grupoId) use ($precios, $contenedor, $tipoPrecio) {
            $grupo = \App\Models\Gestion\Inventario\ProductoGrupoAnalisis::find($grupoId);
            return [
                'grupo_id' => $grupoId,
                'grupo_nombre' => $grupo ? $grupo->Grupo : 'Sin grupo',
                'productos' => $items->map(function($producto) use ($precios, $contenedor, $tipoPrecio) {
                    $precio = $precios[$producto->IdProducto] ?? null;
                    
                    // ✅ Determinar el precio según el tipo
                    $precioFinal = null;
                    $tienePrecio = false;
                    
                    if ($precio) {
                        $precioFinal = $this->obtenerPrecioSegunTipo($precio, $tipoPrecio);
                        $tienePrecio = $precioFinal !== null && $precioFinal > 0;
                    }
                    
                    return [
                        'IdProducto' => $producto->IdProducto,
                        'Codigo' => $producto->Codigo,
                        'Descripcion' => $producto->Descripcion,
                        'PrecioFinal' => $precioFinal,
                        'tiene_precio' => $tienePrecio,
                        'IdGrupoAnalisis' => $producto->IdGrupoAnalisis,
                        'CapacidadTotal' => $contenedor->CapacidadTotal,
                    ];
                })->values(),
            ];
        })->values();

        // Contar cuántos tienen precio
        $totalConPrecio = $productos->filter(function($producto) use ($precios, $tipoPrecio) {
            $precio = $precios[$producto->IdProducto] ?? null;
            if (!$precio) return false;
            $precioFinal = $this->obtenerPrecioSegunTipo($precio, $tipoPrecio);
            return $precioFinal !== null && $precioFinal > 0;
        })->count();

        return response()->json([
            'success' => true,
            'data' => [
                'IdContenedor' => $contenedor->IdContenedor,
                'Codigo' => $contenedor->Codigo,
                'CapacidadTotal' => $contenedor->CapacidadTotal,
                'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                'productos_agrupados' => $productosAgrupados,
                'total_productos' => $totalConPrecio,
                'total_sin_precio' => $productos->count() - $totalConPrecio,
                'idIdentificador' => $idIdentificador,
                'tipoPrecio' => $tipoPrecio,
                'mensaje' => $totalConPrecio === 0 ? 'No hay productos con precio para el tipo seleccionado' : null,
            ]
        ]);
    }

    /**
     * ✅ AGREGAR PRODUCTOS AL CARRITO
     */
    public function agregarAlCarrito(Request $request)
    {
        $request->validate([
            'IdContenedor' => 'required|exists:operacion_pedidos_clientes_contenedor,IdContenedor',
            'productos' => 'required|array|min:1',
            'productos.*.IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'productos.*.Cantidad' => 'required|numeric|min:0.01',
            'productos.*.Precio' => 'required|numeric|min:0',
            'TipoPrecio' => 'nullable|in:sin_factura,con_factura',   // ✅ NUEVO
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
                'TipoPrecio' => $request->TipoPrecio ?? 'sin_factura',   // ✅ NUEVO
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
     * ✅ ACTUALIZAR CONTENEDOR DEL CARRITO
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
                            'IdGrupoAnalisis' => $item->producto ? $item->producto->IdGrupoAnalisis : null,
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

            // ✅ Calcular progreso de grupos
            $progresoGrupos = $this->calcularProgresoGrupos($pedido, $idIdentificador, $clienteId, $sucursalId);

            $cumpleMinimos = collect($progresoGrupos)->every(function($item) {
                return $item['Cumple'];
            });

            return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Review', [
                'pedido' => $pedido,
                'detallesAgrupados' => $detallesAgrupados,
                'clienteNombre' => $cliente->Nombre ?? 'Sin cliente',
                'sucursalNombre' => $sucursal->Nombre ?? 'Sin sucursal',
                'operadorNombre' => $operador->nombre ?? 'Sin operador',
                'totalGeneral' => $totalGeneral,
                'idIdentificador' => $idIdentificador,
                'progresoGrupos' => $progresoGrupos,
                'cumpleMinimos' => $cumpleMinimos,
                'tipoPrecio' => $pedido->TipoPrecio,   // ✅ NUEVO
            ]);

        } catch (\Exception $e) {
            Log::error('Error en review: ' . $e->getMessage());
            return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                ->with('error', 'Error al cargar la revisión: ' . $e->getMessage());
        }
    }

    /**
     * ✅ CAMBIAR TIPO DE PRECIO DEL PEDIDO
     * 
     * Recalcula los precios de todos los productos del carrito
     * según el nuevo tipo.
     */
    public function cambiarTipoPrecio(Request $request)
    {
        $request->validate([
            'IdPedidoCliente' => 'required|exists:pedidos_clientes,IdPedidoCliente',
            'TipoPrecio' => 'required|in:sin_factura,con_factura',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $idIdentificador = $this->getIdIdentificadorOperador();

        $pedido = PedidoCliente::where('IdCliente', $clienteId)
            ->where('IdPedidoCliente', $request->IdPedidoCliente)
            ->where('ActivoInactivo', 0)
            ->first();

        if (!$pedido) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido no encontrado o ya finalizado.'
            ], 404);
        }

        if ($pedido->TipoPrecio === $request->TipoPrecio) {
            return response()->json([
                'success' => true,
                'message' => 'El tipo de precio no cambió',
                'pedido' => $pedido,
            ]);
        }

        DB::beginTransaction();

        try {
            // ✅ Obtener todos los precios
            $precios = PrecioProducto::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdIdentificador', $idIdentificador)
                ->where('ActivoInactivo', 1)
                ->get()
                ->keyBy('IdProducto');

            // ✅ Recalcular precios de todos los detalles
            $detalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)->get();
            $totalGeneral = 0;
            $productosSinPrecio = [];

            foreach ($detalles as $detalle) {
                $precio = $precios[$detalle->IdProducto] ?? null;
                
                if (!$precio) {
                    // No hay precio configurado
                    $productosSinPrecio[] = $detalle->IdProducto;
                    continue;
                }

                $nuevoPrecio = $this->obtenerPrecioSegunTipo($precio, $request->TipoPrecio);
                
                if ($nuevoPrecio === null || $nuevoPrecio <= 0) {
                    $productosSinPrecio[] = $detalle->IdProducto;
                    continue;
                }

                $detalle->update(['Precio' => $nuevoPrecio]);
                $totalGeneral += $detalle->Cantidad * $nuevoPrecio;
            }

            // ✅ Actualizar pedido
            $pedido->update([
                'TipoPrecio' => $request->TipoPrecio,
                'TotalGeneral' => $totalGeneral,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Precios recalculados correctamente',
                'pedido' => $pedido,
                'productos_sin_precio' => $productosSinPrecio,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar tipo de precio: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar tipo de precio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ FINALIZAR PEDIDO
     * 
     * ⚠️ IMPORTANTE: La sucursal es SIEMPRE la de la sesión (session('cliente_sucursal_id')).
     * No se permite cambiarla desde el frontend porque el operador trabaja
     * con una sucursal fija asignada en su login.
     */
    public function finalizarPedido(Request $request, $idPedido)
    {
        \Log::info('=== 🚀 FINALIZAR PEDIDO ===');
        \Log::info('📝 Datos recibidos:', $request->all());

        $request->validate([
            'IdCliente' => 'required|exists:todos_cliente,IdCliente',
            'Observaciones' => 'nullable|string|max:500',
            'TipoPrecio' => 'nullable|in:sin_factura,con_factura',   // ✅ NUEVO
        ]);

        // ✅ La sucursal SIEMPRE viene de la sesión, NO del request
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');  // ✅ FIX: Variable definida desde la sesión
        $operadorId = session('operador_id');

        // ✅ Validar que la sucursal de sesión exista y pertenezca al cliente
        $sucursalValida = ClienteSucursal::where('IdClienteSucursal', $sucursalId)
            ->where('IdCliente', $clienteId)
            ->exists();

        if (!$sucursalValida) {
            return response()->json([
                'success' => false,
                'message' => 'La sucursal de la sesión no es válida. Contacte al administrador.'
            ], 400);
        }

        // ✅ Validar fecha de entrega
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

            if ($diferenciaDias < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'La fecha de entrega debe ser mínimo 1 día después de hoy. Hoy es ' . date('d/m/Y')
                ], 422);
            }

            $fechaEntregaFormateada = date('Y-m-d', $timestampEntrega);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'La fecha de entrega es obligatoria.'
            ], 422);
        }

        try {
            $idIdentificador = $this->getIdIdentificadorOperador();

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

            // ✅ 1. VALIDAR CAPACIDAD POR CONTENEDOR
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

            // ✅ 2. VALIDAR MÍNIMOS POR GRUPO
            // ✅ AHORA $sucursalId ESTÁ DEFINIDO CORRECTAMENTE
            $progresoGrupos = $this->calcularProgresoGrupos($pedido, $idIdentificador, $clienteId, $sucursalId);

            $gruposQueNoCumplen = collect($progresoGrupos)->filter(function($item) {
                return !$item['Cumple'];
            })->values();

            if ($gruposQueNoCumplen->isNotEmpty()) {
                $errores = $gruposQueNoCumplen->map(function($item) {
                    return "• {$item['NombreGrupo']}: faltan {$item['Falta']} und (tienes {$item['CantidadPedida']}, mínimo {$item['CantidadMinima']})";
                })->toArray();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede finalizar el pedido. Faltan mínimos por grupo:',
                    'errores' => $errores,
                ], 400);
            }

            // ✅ 3. CALCULAR TOTALES
            $totales = $this->calcularTotales($pedido->IdPedidoCliente);

            // ✅ 4. GENERAR NÚMERO DE PEDIDO
            $maxNumero = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('NumeroPedido', '!=', '0')
                ->whereNotNull('NumeroPedido')
                ->max(DB::raw('CAST(NumeroPedido AS UNSIGNED)')) ?? 0;

            $nuevoNumero = $maxNumero + 1;
            $numeroPedidoFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);

            $existe = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('NumeroPedido', $numeroPedidoFormateado)
                ->exists();

            if ($existe) {
                $nuevoNumero = $nuevoNumero + 1;
                $numeroPedidoFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);
                
                $existeNuevamente = PedidoCliente::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('NumeroPedido', $numeroPedidoFormateado)
                    ->exists();
                    
                if ($existeNuevamente) {
                    do {
                        $nuevoNumero++;
                        $numeroPedidoFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);
                        $existeNuevamente = PedidoCliente::where('IdCliente', $clienteId)
                            ->where('IdSucursal', $sucursalId)
                            ->where('NumeroPedido', $numeroPedidoFormateado)
                            ->exists();
                    } while ($existeNuevamente);
                }
            }

            // ✅ 5. ACTUALIZAR PEDIDO
            // ⚠️ La sucursal y el cliente son los de la sesión (no se permiten cambios)
            $pedido->update([
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'TipoPrecio' => $request->TipoPrecio ?? $pedido->TipoPrecio,   // ✅ NUEVO
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
                'TotalGeneral' => $totales['total_general'],
                'TipoPrecio' => $pedido->TipoPrecio,
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
                ->with(['detalles.producto', 'detalles.contenedor.tipoContenedor'])
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

            $configOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_operadores_clientes')
                ->where('IdOperador', $pedido->IdOperador)
                ->first();

            $destino = null;
            $tipoUbicacion = null;

            if ($configOperador) {
                if (isset($configOperador->Ciudad) && $configOperador->Ciudad == 1) {
                    $tipoUbicacion = 'Ciudad';
                } elseif (isset($configOperador->Provincia) && $configOperador->Provincia == 1) {
                    $tipoUbicacion = 'Provincia';
                }
                $destino = $configOperador->Destino ?? null;
            }

            $detallesAgrupados = $pedido->detalles
                ->groupBy('OrdenContenedor')
                ->map(function($items, $orden) {
                    $primerItem = $items->first();
                    $contenedor = $primerItem->contenedor;
                    
                    $totalUnidadesContenedor = $items->sum('Cantidad');
                    $subtotal = $items->sum(function($item) {
                        return $item->Cantidad * $item->Precio;
                    });
                    
                    return [
                        'OrdenContenedor' => intval($orden),
                        'IdContenedor' => $primerItem->IdContenedor,
                        'Codigo' => $contenedor ? $contenedor->Codigo : '-',
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
                        })->values()->toArray(),
                        'total_unidades' => $totalUnidadesContenedor,
                        'subtotal' => $subtotal,
                    ];
                })
                ->sortBy('IdContenedor')
                ->values();

            $resumenPorTipo = $detallesAgrupados
                ->groupBy('Codigo')
                ->map(function($items, $codigo) {
                    return [
                        'Codigo' => $codigo,
                        'cantidad_contenedores' => $items->count(),
                        'total_unidades' => $items->sum('total_unidades'),
                        'subtotal' => $items->sum('subtotal'),
                    ];
                })
                ->sortBy('Codigo')
                ->values();

            $totalUnidades = $pedido->detalles->sum('Cantidad');
            $totalContenedores = $detallesAgrupados->count();
            $totalGeneral = $pedido->detalles->sum(function($item) {
                return $item->Cantidad * $item->Precio;
            });

            $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 12);
            $pdf->AddPage();

            $y = 8;

            // HEADER EMPRESA
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 5, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'), 0, 1, 'C');
            $y += 5;

            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 3.5, $sucursal->Nombre ?? '', 0, 1, 'C');
            $y += 3.5;

            if (!empty($sucursal->Direccion)) {
                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 3.5, $sucursal->Direccion, 0, 1, 'C');
                $y += 3.5;
            }

            if (!empty($empresa->NIT)) {
                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 3.5, "NIT: " . $empresa->NIT, 0, 1, 'C');
                $y += 3.5;
            }

            $y += 2;
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Line(10, $y, 206, $y);
            $y += 5;

            // TÍTULO
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetTextColor(30, 60, 120);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 7, 'PEDIDO DE PRODUCTOS', 0, 1, 'C');
            $y += 7;

            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 5, 'N° ' . ($pedido->NumeroPedido ?? '000000'), 0, 1, 'C');
            $y += 8;

            // INFO PEDIDO
            $pdf->SetFont('helvetica', '', 8);
            
            $colIzq_label = 12;
            $colIzq_valor = 45;
            $colDer_label = 108;
            $colDer_valor = 138;
            $yInfo = $y;
            $altoFila = 5;

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colIzq_label, $yInfo);
            $pdf->Cell(33, $altoFila, 'Fecha Pedido:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colIzq_valor, $yInfo);
            $pdf->Cell(60, $altoFila, Carbon::parse($pedido->FechaPedido)->format('d/m/Y H:i'), 0, 0, 'L');
            $yInfo += $altoFila;

            if ($pedido->FechaEntrega) {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($colIzq_label, $yInfo);
                $pdf->Cell(33, $altoFila, 'Fecha Entrega:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($colIzq_valor, $yInfo);
                $pdf->Cell(60, $altoFila, Carbon::parse($pedido->FechaEntrega)->format('d/m/Y'), 0, 0, 'L');
                $yInfo += $altoFila;
            }

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colIzq_label, $yInfo);
            $pdf->Cell(33, $altoFila, 'Operador:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colIzq_valor, $yInfo);
            $pdf->Cell(60, $altoFila, $operador->nombre ?? 'Sin operador', 0, 0, 'L');
            $yInfo += $altoFila;

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colIzq_label, $yInfo);
            $pdf->Cell(33, $altoFila, 'Sucursal:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colIzq_valor, $yInfo);
            $pdf->Cell(60, $altoFila, $sucursal->Nombre ?? 'Sin sucursal', 0, 0, 'L');
            $yInfo += $altoFila;

            $yInfoDer = $y;

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colDer_label, $yInfoDer);
            $pdf->Cell(30, $altoFila, 'Estado:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colDer_valor, $yInfoDer);
            $pdf->Cell(58, $altoFila, $pedido->EstadoPedido ?? 'Pendiente', 0, 0, 'L');
            $yInfoDer += $altoFila;

            // ✅ NUEVO: Mostrar tipo de precio
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colDer_label, $yInfoDer);
            $pdf->Cell(30, $altoFila, 'Tipo Precio:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colDer_valor, $yInfoDer);
            $pdf->Cell(58, $altoFila, $pedido->TipoPrecioTexto, 0, 0, 'L');
            $yInfoDer += $altoFila;

            if ($tipoUbicacion) {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($colDer_label, $yInfoDer);
                $pdf->Cell(30, $altoFila, 'Tipo:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($colDer_valor, $yInfoDer);
                $pdf->Cell(58, $altoFila, $tipoUbicacion, 0, 0, 'L');
                $yInfoDer += $altoFila;
            }

            if ($destino) {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($colDer_label, $yInfoDer);
                $pdf->Cell(30, $altoFila, 'Destino:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($colDer_valor, $yInfoDer);
                $pdf->Cell(58, $altoFila, $destino, 0, 0, 'L');
                $yInfoDer += $altoFila;
            }

            $y = max($yInfo, $yInfoDer) + 3;

            // OBSERVACIONES
            if (!empty($pedido->Observaciones)) {
                $pdf->SetDrawColor(251, 191, 36);
                $pdf->SetFillColor(255, 251, 235);
                
                $pdf->SetFont('helvetica', '', 7.5);
                $alturaTexto = $pdf->getStringHeight(180, $pedido->Observaciones);
                $alturaCaja = max(12, 6 + $alturaTexto + 3);
                
                $pdf->RoundedRect(10, $y, 196, $alturaCaja, 1.5, '1111', 'DF');
                
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetTextColor(146, 64, 14);
                $pdf->SetXY(12, $y + 2);
                $pdf->Cell(100, 4, 'OBSERVACIONES:', 0, 0, 'L');
                
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetTextColor(80, 40, 10);
                $pdf->SetXY(12, $y + 6.5);
                $pdf->MultiCell(192, 3, $pedido->Observaciones, 0, 'L');
                
                $y += $alturaCaja + 4;
                
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetFillColor(255, 255, 255);
            }

            // CABECERA TABLA
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(180, 180, 180);

            $pdf->SetXY(10, $y);
            $pdf->Cell(8, 5, '#', 'TB', 0, 'C', 1);
            $pdf->Cell(72, 5, 'PRODUCTO', 'TB', 0, 'L', 1);
            $pdf->Cell(24, 5, 'CANTIDAD', 'TB', 0, 'C', 1);
            $pdf->Cell(30, 5, 'PRECIO UNIT.', 'TB', 0, 'C', 1);
            $pdf->Cell(62, 5, 'SUBTOTAL', 'TB', 1, 'C', 1);
            $y += 5;

            $pdf->SetFont('helvetica', '', 7);
            $contador = 0;

            foreach ($detallesAgrupados as $index => $grupo) {
                if ($index > 0) {
                    $y += 3;
                }

                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->SetFillColor(225, 238, 255);
                $pdf->SetTextColor(20, 50, 110);

                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 6, '', 'LTR', 1, 'L', 1);

                $pdf->SetXY(10, $y);
                $pdf->Cell(110, 6, '  [' . $grupo['Codigo'] . ']  #' . $grupo['OrdenContenedor'], 'L', 0, 'L', 1);
                $pdf->Cell(86, 6, 'Cap: ' . number_format($grupo['CapacidadTotal'], 0, ',', '.') . ' und   ', 'R', 1, 'R', 1);
                $y += 6;

                $pdf->SetTextColor(0, 0, 0);

                $pdf->SetFont('helvetica', '', 7);
                $fill = false;

                foreach ($grupo['productos'] as $producto) {
                    $contador++;
                    $nombreProducto = $producto['Descripcion'] ?? '-';
                    if (mb_strlen($nombreProducto, 'UTF-8') > 42) {
                        $nombreProducto = mb_substr($nombreProducto, 0, 40, 'UTF-8') . '...';
                    }
                    
                    $pdf->SetXY(10, $y);
                    $pdf->Cell(8, 4, $contador . '.', 'LR', 0, 'C', $fill);
                    $pdf->Cell(72, 4, ' ' . $nombreProducto, 'LR', 0, 'L', $fill);
                    $pdf->Cell(24, 4, number_format($producto['Cantidad'], 0, ',', '.'), 'LR', 0, 'C', $fill);
                    $pdf->Cell(30, 4, number_format($producto['Precio'], 2, ',', '.'), 'LR', 0, 'C', $fill);
                    $pdf->Cell(62, 4, number_format($producto['Subtotal'], 2, ',', '.'), 'LR', 1, 'C', $fill);
                    $y += 4;
                    $fill = !$fill;
                }

                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetFillColor(235, 245, 255);
                $pdf->SetXY(10, $y);
                $pdf->Cell(8, 4.5, '', 'LRB', 0, 'C', 1);
                $pdf->Cell(72, 4.5, 'TOTAL ' . $grupo['Codigo'] . ' #' . $grupo['OrdenContenedor'], 'LRB', 0, 'R', 1);
                $pdf->Cell(24, 4.5, number_format($grupo['total_unidades'], 0, ',', '.'), 'LRB', 0, 'C', 1);
                $pdf->Cell(30, 4.5, '', 'LRB', 0, 'C', 1);
                $pdf->Cell(62, 4.5, 'Bs. ' . number_format($grupo['subtotal'], 2, ',', '.'), 'LRB', 1, 'C', 1);
                $y += 4.5;
            }

            $y += 5;

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->SetXY(10, $y);
            $pdf->Cell(98, 5, 'Total Contenedores: ' . number_format($totalContenedores, 0, ',', '.'), 0, 0, 'L');
            $pdf->Cell(98, 5, 'Total Unidades: ' . number_format($totalUnidades, 0, ',', '.'), 0, 0, 'L');
            $y += 6;

            $pdf->SetDrawColor(180, 180, 180);
            $pdf->Line(10, $y, 206, $y);
            $y += 4;

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetTextColor(20, 50, 110);
            $pdf->SetXY(10, $y);
            $pdf->Cell(136, 7, 'TOTAL GENERAL DEL PEDIDO', 0, 0, 'R');
            $pdf->SetXY(146, $y);
            $pdf->Cell(60, 7, 'Bs. ' . number_format($totalGeneral, 2, ',', '.'), 0, 1, 'R');
            $y += 10;

            // RESUMEN POR TIPO
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(30, 60, 120);
            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 6, 'RESUMEN POR TIPO DE CONTENEDOR', 0, 1, 'C');
            $y += 7;

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(180, 180, 180);

            $pdf->SetXY(10, $y);
            $pdf->Cell(90, 5, 'CONTENEDOR', 'TB', 0, 'L', 1);
            $pdf->Cell(30, 5, 'CANT.', 'TB', 0, 'C', 1);
            $pdf->Cell(30, 5, 'UNIDADES', 'TB', 0, 'C', 1);
            $pdf->Cell(46, 5, 'SUBTOTAL', 'TB', 1, 'C', 1);
            $y += 5;

            $pdf->SetFont('helvetica', '', 8);
            $fill = false;

            foreach ($resumenPorTipo as $item) {
                $pdf->SetXY(10, $y);
                $pdf->Cell(90, 5, '  ' . $item['Codigo'], 'LR', 0, 'L', $fill);
                $pdf->Cell(30, 5, $item['cantidad_contenedores'] . ' cont.', 'LR', 0, 'C', $fill);
                $pdf->Cell(30, 5, number_format($item['total_unidades'], 0, ',', '.') . ' und', 'LR', 0, 'C', $fill);
                $pdf->Cell(46, 5, 'Bs. ' . number_format($item['subtotal'], 2, ',', '.'), 'LR', 1, 'C', $fill);
                $y += 5;
                $fill = !$fill;
            }

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetFillColor(235, 245, 255);
            $pdf->SetXY(10, $y);
            $pdf->Cell(90, 5, '  TOTAL GENERAL', 'LRB', 0, 'R', 1);
            $pdf->Cell(30, 5, $totalContenedores . ' cont.', 'LRB', 0, 'C', 1);
            $pdf->Cell(30, 5, number_format($totalUnidades, 0, ',', '.') . ' und', 'LRB', 0, 'C', 1);
            $pdf->Cell(46, 5, 'Bs. ' . number_format($totalGeneral, 2, ',', '.'), 'LRB', 1, 'C', 1);
            $y += 10;

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetFillColor(255, 255, 255);

            // FIRMAS
            $y += 15;
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 8);

            $pdf->SetXY(25, $y);
            $pdf->Cell(70, 0.3, '', 'T', 0, 'C');
            $pdf->SetXY(115, $y);
            $pdf->Cell(70, 0.3, '', 'T', 1, 'C');

            $pdf->SetXY(25, $y + 1);
            $pdf->Cell(70, 4, 'Firma Operador', 0, 0, 'C');
            $pdf->SetXY(115, $y + 1);
            $pdf->Cell(70, 4, 'Firma Recibido', 0, 1, 'C');

            // SALIDA
            $nombreArchivo = 'Pedido_' . ($pedido->NumeroPedido ?? '000000') . '.pdf';
            
            if (ob_get_length()) {
                ob_end_clean();
            }
            
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