<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\HoraLimite;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClienteDetalle;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClienteSubCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\OperadorPedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoClienteProducto;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoAnalisisMinimo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ProductoMinimo;
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
    // ============================================================
    // HELPERS PRIVADOS
    // ============================================================

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

        $idIdentificador = $operador && $operador->IdIdentificador !== null
            ? (int) $operador->IdIdentificador
            : null;

        cache()->put($cacheKey, $idIdentificador, 3600);

        return $idIdentificador;
    }

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

    private function getGrupoDelOperador()
    {
        $idIdentificador = $this->getIdIdentificadorOperador();
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        if (!$idIdentificador) return null;

        $cacheKey = "grupo_operador_{$idIdentificador}_{$clienteId}_{$sucursalId}";

        return cache()->remember($cacheKey, 1800, function () use ($idIdentificador, $clienteId, $sucursalId) {
            return GrupoCliente::obtenerGrupoDeIdentificador($idIdentificador, $clienteId, $sucursalId);
        });
    }

    /**
     * Calcular progreso de mínimos GLOBALES
     */
    private function calcularProgresoGrupos($pedidoBorrador, $idGrupoCliente = null)
    {
        if (!$pedidoBorrador) return [];

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('pedidos_clientes_detalle as d')
            ->join('inventario_productodetalle as p', 'd.IdProducto', '=', 'p.IdProducto')
            ->where('d.IdPedidoCliente', $pedidoBorrador->IdPedidoCliente)
            ->select('d.IdProducto', 'd.Cantidad', 'p.IdGrupoAnalisis')
            ->get();

        $progreso = [];

        // 1. Mínimos por grupo
        $mapaGrupos = GrupoAnalisisMinimo::obtenerMapa($clienteId, $sucursalId);

        if (!empty($mapaGrupos)) {
            $acumuladoPorGrupo = [];
            foreach ($detalles as $d) {
                $idG = $d->IdGrupoAnalisis;
                $acumuladoPorGrupo[$idG] = ($acumuladoPorGrupo[$idG] ?? 0) + (float) $d->Cantidad;
            }

            $idsGrupos = array_keys($mapaGrupos);
            $nombresGrupos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productogrupoanalisis')
                ->whereIn('IdGrupoAnalisis', $idsGrupos)
                ->pluck('Grupo', 'IdGrupoAnalisis');

            foreach ($mapaGrupos as $idGrupo => $minimo) {
                if (!isset($acumuladoPorGrupo[$idGrupo])) continue;

                $pedida = $acumuladoPorGrupo[$idGrupo];
                $min = (float) $minimo;

                $progreso[] = [
                    'Tipo' => 'grupo',
                    'IdGrupoAnalisis' => $idGrupo,
                    'NombreGrupo' => $nombresGrupos[$idGrupo] ?? 'Sin grupo',
                    'CantidadPedida' => $pedida,
                    'CantidadMinima' => $min,
                    'Cumple' => $pedida >= $min,
                    'Falta' => max(0, $min - $pedida),
                ];
            }
        }

        // 2. Mínimos por producto
        $mapaProductos = ProductoMinimo::obtenerMapa($clienteId, $sucursalId);

        if (!empty($mapaProductos)) {
            $acumuladoPorProducto = [];
            foreach ($detalles as $d) {
                $acumuladoPorProducto[$d->IdProducto] = ($acumuladoPorProducto[$d->IdProducto] ?? 0) + (float) $d->Cantidad;
            }

            $idsProductos = array_keys($mapaProductos);
            $infoProductos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->whereIn('IdProducto', $idsProductos)
                ->get(['IdProducto', 'Codigo', 'Descripcion'])
                ->keyBy('IdProducto');

            foreach ($mapaProductos as $idProd => $minimo) {
                if (!isset($acumuladoPorProducto[$idProd])) continue;

                $pedida = $acumuladoPorProducto[$idProd];
                $min = (float) $minimo;
                $info = $infoProductos[$idProd] ?? null;

                $progreso[] = [
                    'Tipo' => 'producto',
                    'IdProducto' => $idProd,
                    'NombreGrupo' => $info ? ($info->Codigo . ' - ' . $info->Descripcion) : 'Producto #' . $idProd,
                    'CantidadPedida' => $pedida,
                    'CantidadMinima' => $min,
                    'Cumple' => $pedida >= $min,
                    'Falta' => max(0, $min - $pedida),
                ];
            }
        }

        return $progreso;
    }

    /**
     * Validar que TODOS los productos del pedido tengan mínimo configurado.
     */
    private function validarProductosDelPedidoTienenMinimo($pedido)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $idsProductosEnPedido = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
            ->pluck('IdProducto')
            ->unique()
            ->toArray();

        if (empty($idsProductosEnPedido)) return [];

        $mapaProductos = ProductoMinimo::obtenerMapa($clienteId, $sucursalId);
        $idsSinMinimo = array_diff($idsProductosEnPedido, array_keys($mapaProductos));

        if (empty($idsSinMinimo)) return [];

        $productosSinMinimo = ProductoDetalle::whereIn('IdProducto', $idsSinMinimo)
            ->get(['IdProducto', 'Codigo', 'Descripcion']);

        return $productosSinMinimo->map(function ($p) {
            return [
                'IdProducto' => $p->IdProducto,
                'Codigo' => $p->Codigo,
                'Descripcion' => $p->Descripcion,
            ];
        })->toArray();
    }

    // ============================================================
    // LISTA DE PEDIDOS
    // ============================================================

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

        // ✅ VALIDAR SI PUEDE HACER PEDIDOS
        $grupoCliente = $this->getGrupoDelOperador();
        $puedeHacerPedidos = true;
        $razonNoPuedePedir = null;

        if (!$grupoCliente) {
            $puedeHacerPedidos = false;
            $razonNoPuedePedir = "Tu usuario no tiene un Grupo de Clientes asignado.\n\nContacta al administrador del sistema para que te asigne un grupo.";
        } else {
            $contenedoresCount = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor as c')
                ->join('operacion_pedidos_clientes_contenedor_grupo_cliente as cgc', 'cgc.IdContenedor', '=', 'c.IdContenedor')
                ->where('c.IdCliente', $clienteId)
                ->where('c.ActivoInactivo', 1)
                ->where('cgc.IdGrupoCliente', $grupoCliente->IdGrupoCliente)
                ->where('cgc.ActivoInactivo', 1)
                ->count();

            if ($contenedoresCount === 0) {
                $puedeHacerPedidos = false;
                $razonNoPuedePedir = "Tu grupo '{$grupoCliente->Nombre}' no tiene contenedores asignados.\n\n📞 Contacta al administrador del sistema para que te asigne al menos un contenedor a tu grupo.";
            }
        }

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Index', [
            'pedidos' => $pedidos,
            'puedeHacerPedidos' => $puedeHacerPedidos,
            'razonNoPuedePedir' => $razonNoPuedePedir,
        ]);
    }

    // ============================================================
    // NUEVO PEDIDO - MENÚ DE CONTENEDORES
    // ============================================================

    public function create(Request $request)
    {
        // ✅ LOGS DE DEBUG
        Log::info('=== 🔍 DEBUG create() ===');
        Log::info('Session cliente_id: ' . session('cliente_id'));
        Log::info('Session cliente_sucursal_id: ' . session('cliente_sucursal_id'));
        Log::info('Session operador_id: ' . session('operador_id'));

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        if (!$clienteId || !$sucursalId) {
            Log::error('❌ FALTA SESIÓN: cliente_id o cliente_sucursal_id');
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', 'Tu sesión expiró. Por favor, vuelve a iniciar sesión.');
        }

        $tipoPrecio = $request->get('tipo_precio', 'sin_factura');
        if (!in_array($tipoPrecio, ['sin_factura', 'con_factura'])) {
            $tipoPrecio = 'sin_factura';
        }

        // VALIDACIÓN 1
        $idIdentificador = $this->getIdIdentificadorOperador();
        Log::info('VALIDACIÓN 1 - idIdentificador: ' . ($idIdentificador ?? 'NULL'));

        if (!$idIdentificador) {
            Log::error('❌ FALLA VALIDACIÓN 1: no hay idIdentificador');
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', 'No se encontró el perfil del operador (IdOperador: ' . session('operador_id') . '). Contacte al administrador.');
        }

        // VALIDACIÓN 2
        $grupoCliente = $this->getGrupoDelOperador();
        Log::info('VALIDACIÓN 2 - grupoCliente: ' . ($grupoCliente ? $grupoCliente->Nombre . ' (Id: ' . $grupoCliente->IdGrupoCliente . ')' : 'NULL'));

        if (!$grupoCliente) {
            Log::error('❌ FALLA VALIDACIÓN 2: operador no tiene grupo asignado');
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', "Tu usuario (IdIdentificador: {$idIdentificador}) no tiene un Grupo de Clientes asignado en este cliente/sucursal. Contacte al administrador.");
        }

        // VALIDACIÓN 3
        $contenedores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor as c')
            ->join('operacion_pedidos_clientes_contenedor_grupo_cliente as cgc', 'cgc.IdContenedor', '=', 'c.IdContenedor')
            ->leftJoin('operacion_pedidos_clientes_contenedor_tipo as t', 't.IdTipoContenedor', '=', 'c.IdTipoContenedor')
            ->where('c.IdCliente', $clienteId)
            ->where('c.ActivoInactivo', 1)
            ->where('cgc.IdGrupoCliente', $grupoCliente->IdGrupoCliente)
            ->where('cgc.ActivoInactivo', 1)
            ->select('c.IdContenedor', 'c.Codigo', 'c.CapacidadTotal', 'c.IdTipoContenedor', 't.Nombre as TipoContenedor')
            ->orderBy('c.Codigo')
            ->get();

        Log::info('VALIDACIÓN 3 - contenedores count: ' . $contenedores->count());

        if ($contenedores->isEmpty()) {
            Log::error('❌ FALLA VALIDACIÓN 3: grupo sin contenedores. Grupo: ' . $grupoCliente->Nombre);
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', "Tu grupo '{$grupoCliente->Nombre}' no tiene contenedores asignados. Contacte al administrador.");
        }

        Log::info('✅ TODAS LAS VALIDACIONES OK. Renderizando vista Create.');

        $totalProductosDelGrupo = GrupoClienteProducto::where('IdGrupoCliente', $grupoCliente->IdGrupoCliente)
            ->where('ActivoInactivo', 1)
            ->where(function ($q) {
                $q->where('PrecioSinFactura', '>', 0)
                  ->orWhere('PrecioConFactura', '>', 0);
            })
            ->count();

        $contenedoresFormateados = $contenedores->map(function ($c) use ($totalProductosDelGrupo, $grupoCliente) {
            return [
                'IdContenedor' => $c->IdContenedor,
                'Codigo' => $c->Codigo,
                'TipoContenedor' => $c->TipoContenedor ?: '-',
                'CapacidadTotal' => $c->CapacidadTotal,
                'CapacidadTotalFormateada' => number_format($c->CapacidadTotal, 0, ',', '.'),
                'total_productos' => $totalProductosDelGrupo,
                'grupos' => $grupoCliente->Nombre,
            ];
        });

        $clientes = Cliente::where('IdCliente', $clienteId)->get(['IdCliente as id', 'Nombre']);

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre']);

        $pedidoBorrador = PedidoCliente::obtenerBorradorActivo();

        $carrito = [];
        if ($pedidoBorrador) {
            if ($pedidoBorrador->TipoPrecio !== $tipoPrecio) {
                $pedidoBorrador->update(['TipoPrecio' => $tipoPrecio]);
            }

            $detalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedidoBorrador->IdPedidoCliente)
                ->with(['producto', 'contenedor', 'subClienteOperador.identificador'])
                ->orderBy('OrdenContenedor')
                ->orderBy('IdProducto')
                ->get();

            $carrito = $detalles->groupBy('OrdenContenedor')->map(function ($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                $total = $items->sum('Cantidad');

                $subCliente = $primerItem->subClienteOperador;
                $subClienteNombre = $subCliente
                    ? ($subCliente->Alias ?: ($subCliente->identificador->Nombre ?? null))
                    : null;

                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'IdSubClienteOperador' => $primerItem->IdSubClienteOperador,
                    'SubClienteNombre' => $subClienteNombre,
                    'productos' => $items->map(function ($item) {
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

        // ✅ MAPA DE GRUPOS CON NOMBRE (ARRAY)
        $mapaGruposRaw = GrupoAnalisisMinimo::obtenerMapa();
        $minimosGrupos = [];

        if (!empty($mapaGruposRaw)) {
            $idsGrupos = array_keys($mapaGruposRaw);
            $nombresGrupos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productogrupoanalisis')
                ->whereIn('IdGrupoAnalisis', $idsGrupos)
                ->pluck('Grupo', 'IdGrupoAnalisis');

            foreach ($mapaGruposRaw as $idGrupo => $cantidad) {
                $minimosGrupos[] = [
                    'IdGrupoAnalisis' => (int) $idGrupo,
                    'NombreGrupo' => $nombresGrupos[$idGrupo] ?? 'Sin nombre',
                    'CantidadMinimaGrupo' => (float) $cantidad,
                ];
            }
        }

        // ✅ MAPA DE PRODUCTOS CON INFO
        $minimosProductos = ProductoMinimo::obtenerMapa();
        $infoProductos = [];

        if (!empty($minimosProductos)) {
            $idsProductos = array_keys($minimosProductos);
            $infoProd = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->whereIn('IdProducto', $idsProductos)
                ->get(['IdProducto', 'Codigo', 'Descripcion', 'IdGrupoAnalisis']);

            foreach ($infoProd as $p) {
                $infoProductos[$p->IdProducto] = [
                    'IdProducto' => $p->IdProducto,
                    'Codigo' => $p->Codigo,
                    'Descripcion' => $p->Descripcion,
                    'IdGrupoAnalisis' => $p->IdGrupoAnalisis,
                    'CantidadMinimaProducto' => (float) ($minimosProductos[$p->IdProducto] ?? 0),
                ];
            }
        }

        $progresoInicial = $this->calcularProgresoGrupos($pedidoBorrador, $grupoCliente->IdGrupoCliente);

        PedidoClienteSubCliente::asegurarSubClientePropio();

        $subclientes = PedidoClienteSubCliente::obtenerSubClientesDelOperador();
        $idSubClienteDefault = PedidoClienteSubCliente::obtenerSubClientePorDefecto();

        $subclientes = array_map(function ($sub) {
            return [
                'IdSubClienteOperador' => (int) $sub['IdSubClienteOperador'],
                'IdIdentificador' => (int) $sub['IdIdentificador'],
                'Nombre' => $sub['Nombre'],
                'CI_NIT' => $sub['CI_NIT'],
            ];
        }, $subclientes);

        $idSubClienteDefault = $idSubClienteDefault ? (int) $idSubClienteDefault : null;

        $horaLimite = HoraLimite::obtenerHoraActiva(
            HoraLimite::TIPO_PEDIDO_CLIENTE_MAYORISTA
        );

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Create', [
            'contenedores' => $contenedoresFormateados,
            'clientes' => $clientes,
            'sucursales' => $sucursales,
            'pedidoBorrador' => $pedidoBorrador,
            'carrito' => $carrito,
            'sucursalDefault' => (int) $sucursalId,
            'idIdentificador' => (int) $idIdentificador,
            'nombreOperador' => $this->getNombreOperador(),
            'grupoCliente' => [
                'IdGrupoCliente' => $grupoCliente->IdGrupoCliente,
                'Nombre' => $grupoCliente->Nombre,
            ],
            'minimosGrupos' => $minimosGrupos,         // ✅ ARRAY con nombre
            'minimosProductos' => $minimosProductos,   // ✅ Mapa {IdProducto: Cantidad}
            'infoProductos' => $infoProductos,          // ✅ NUEVO
            'progresoInicial' => $progresoInicial,
            'tipoPrecio' => $tipoPrecio,
            'subclientes' => $subclientes,
            'idSubClienteDefault' => $idSubClienteDefault,
            'horaLimite' => $horaLimite ? $horaLimite->Hora : null,
            'horaLimiteFormateada' => $horaLimite ? $horaLimite->HoraFormateada : null,
        ]);
    }

    // ============================================================
    // PRODUCTOS DEL CONTENEDOR CON PRECIOS DEL GRUPO
    // ============================================================
    public function getProductosContenedorConPrecios(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $tipoPrecio = $request->get('tipo_precio', 'sin_factura');
        if (!in_array($tipoPrecio, ['sin_factura', 'con_factura'])) {
            $tipoPrecio = 'sin_factura';
        }

        $idIdentificador = $this->getIdIdentificadorOperador();
        if (!$idIdentificador) {
            return response()->json(['success' => false, 'message' => 'No se encontró el perfil del operador.'], 400);
        }

        $grupoCliente = $this->getGrupoDelOperador();
        if (!$grupoCliente) {
            return response()->json(['success' => false, 'message' => 'Tu usuario no tiene un Grupo de Clientes asignado.'], 400);
        }

        $contenedor = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->where('IdContenedor', $id)
            ->whereHas('gruposClientesActivos', function ($q) use ($grupoCliente) {
                $q->where('IdGrupoCliente', $grupoCliente->IdGrupoCliente);
            })
            ->with(['tipoContenedor'])
            ->first();

        if (!$contenedor) {
            return response()->json(['success' => false, 'message' => 'Este contenedor no está asignado a tu grupo.'], 400);
        }

        $productosDisponibles = ProductoMinimo::obtenerIdsDisponibles($clienteId, $sucursalId);

        if (empty($productosDisponibles)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'IdContenedor' => $contenedor->IdContenedor,
                    'Codigo' => $contenedor->Codigo,
                    'CapacidadTotal' => $contenedor->CapacidadTotal,
                    'TipoContenedor' => $contenedor->tipoContenedor ? $contenedor->tipoContenedor->Nombre : '-',
                    'productos_agrupados' => [],
                    'total_productos' => 0,
                    'tipoPrecio' => $tipoPrecio,
                    'grupoCliente' => $grupoCliente->Nombre,
                    'mensaje' => 'No hay productos configurados para pedidos. Contacte al administrador.',
                ]
            ]);
        }

        $mapaProductos = ProductoMinimo::obtenerMapa($clienteId, $sucursalId);

        $productosConPrecio = GrupoClienteProducto::where('IdGrupoCliente', $grupoCliente->IdGrupoCliente)
            ->where('ActivoInactivo', 1)
            ->get()
            ->keyBy('IdProducto');

        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdProducto', $productosDisponibles)
            ->whereIn('IdProducto', $productosConPrecio->keys())
            ->where('ActivoInactivo', 0)
            ->orderBy('IdGrupoAnalisis')
            ->orderBy('Descripcion')
            ->get(['IdProducto', 'Codigo', 'Descripcion', 'IdGrupoAnalisis']);

        $productosAgrupados = $productos->groupBy('IdGrupoAnalisis')->map(function ($items, $grupoId) use ($productosConPrecio, $contenedor, $tipoPrecio, $mapaProductos) {
            $grupo = \App\Models\Gestion\Inventario\ProductoGrupoAnalisis::find($grupoId);

            return [
                'grupo_id' => $grupoId,
                'grupo_nombre' => $grupo ? $grupo->Grupo : 'Sin grupo',
                'productos' => $items->map(function ($producto) use ($productosConPrecio, $contenedor, $tipoPrecio, $mapaProductos) {
                    $precioGrupo = $productosConPrecio[$producto->IdProducto] ?? null;

                    $precioFinal = null;
                    if ($precioGrupo) {
                        $precioFinal = $tipoPrecio === 'con_factura'
                            ? $precioGrupo->PrecioConFactura
                            : $precioGrupo->PrecioSinFactura;
                    }

                    $tienePrecio = $precioFinal !== null && $precioFinal > 0;
                    $minimoProducto = $mapaProductos[$producto->IdProducto] ?? null;

                    return [
                        'IdProducto' => $producto->IdProducto,
                        'Codigo' => $producto->Codigo,
                        'Descripcion' => $producto->Descripcion,
                        'PrecioFinal' => $precioFinal,
                        'tiene_precio' => $tienePrecio,
                        'IdGrupoAnalisis' => $producto->IdGrupoAnalisis,
                        'CapacidadTotal' => $contenedor->CapacidadTotal,
                        'MinimoProducto' => $minimoProducto !== null ? (float) $minimoProducto : null,
                    ];
                })->values(),
            ];
        })->values();

        $totalConPrecio = $productos->filter(function ($producto) use ($productosConPrecio, $tipoPrecio) {
            $precioGrupo = $productosConPrecio[$producto->IdProducto] ?? null;
            if (!$precioGrupo) return false;
            $precioFinal = $tipoPrecio === 'con_factura' ? $precioGrupo->PrecioConFactura : $precioGrupo->PrecioSinFactura;
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
                'tipoPrecio' => $tipoPrecio,
                'grupoCliente' => $grupoCliente->Nombre,
            ]
        ]);
    }

    // ============================================================
    // CARRITO
    // ============================================================

    public function agregarAlCarrito(Request $request)
    {
        $request->validate([
            'IdContenedor' => 'required|exists:operacion_pedidos_clientes_contenedor,IdContenedor',
            'productos' => 'required|array|min:1',
            'productos.*.IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'productos.*.Cantidad' => 'required|numeric|min:0.01',
            'productos.*.Precio' => 'required|numeric|min:0',
            'TipoPrecio' => 'nullable|in:sin_factura,con_factura',
            'IdSubClienteOperador' => 'nullable|exists:pedidos_clientes_subclientes,IdSubClienteOperador',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $contenedor = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->findOrFail($request->IdContenedor);

        $totalUnidades = array_sum(array_column($request->productos, 'Cantidad'));

        if ((float) $totalUnidades > (float) $contenedor->CapacidadTotal) {
            return response()->json([
                'success' => false,
                'message' => "La suma de productos ({$totalUnidades}) excede la capacidad del contenedor ({$contenedor->CapacidadTotal})"
            ], 400);
        }

        DB::beginTransaction();

        try {
            $pedido = PedidoCliente::obtenerOCrearBorrador([
                'IdSucursal' => $sucursalId,
                'TipoPrecio' => $request->TipoPrecio ?? 'sin_factura',
            ]);

            $maxOrden = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                ->max('OrdenContenedor') ?? 0;
            $nuevoOrden = $maxOrden + 1;

            foreach ($request->productos as $producto) {
                PedidoClienteDetalle::create([
                    'IdPedidoCliente' => $pedido->IdPedidoCliente,
                    'IdContenedor' => $request->IdContenedor,
                    'IdProducto' => $producto['IdProducto'],
                    'IdSubClienteOperador' => $request->IdSubClienteOperador,
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
            'IdSubClienteOperador' => 'nullable|exists:pedidos_clientes_subclientes,IdSubClienteOperador',
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
                        'IdSubClienteOperador' => $request->IdSubClienteOperador,
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

    // ============================================================
    // REVISAR PEDIDO
    // ============================================================

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
                ->with([
                    'detalles.producto',
                    'detalles.contenedor',
                    'detalles.subClienteOperador.identificador'
                ])
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

            $grupoCliente = $this->getGrupoDelOperador();

            if (!$grupoCliente) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                    ->with('error', 'Tu usuario no tiene un Grupo de Clientes asignado.');
            }

            $detallesAgrupados = $pedido->detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                $total = $items->sum('Cantidad');
                $subtotal = $items->sum(function($item) {
                    return $item->Cantidad * $item->Precio;
                });

                $subCliente = $primerItem->subClienteOperador;
                $subClienteNombre = $subCliente
                    ? ($subCliente->Alias ?: ($subCliente->identificador->Nombre ?? null))
                    : null;

                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'IdSubClienteOperador' => $primerItem->IdSubClienteOperador,
                    'SubClienteNombre' => $subClienteNombre,
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

            $progresoGrupos = $this->calcularProgresoGrupos($pedido, $grupoCliente->IdGrupoCliente);

            $cumpleMinimos = collect($progresoGrupos)->every(function($item) {
                return $item['Cumple'];
            });

            $productosSinMinimo = $this->validarProductosDelPedidoTienenMinimo($pedido);
            $tieneProductosSinMinimo = !empty($productosSinMinimo);

            if ($tieneProductosSinMinimo) {
                $cumpleMinimos = false;
            }

            $subclientes = PedidoClienteSubCliente::obtenerSubClientesDelOperador();
            $subclientes = array_map(function ($sub) {
                return [
                    'IdSubClienteOperador' => (int) $sub['IdSubClienteOperador'],
                    'IdIdentificador' => (int) $sub['IdIdentificador'],
                    'Nombre' => $sub['Nombre'],
                    'CI_NIT' => $sub['CI_NIT'],
                ];
            }, $subclientes);

            $horaLimite = HoraLimite::obtenerHoraActiva(
                HoraLimite::TIPO_PEDIDO_CLIENTE_MAYORISTA
            );

            return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Review', [
                'pedido' => $pedido,
                'detallesAgrupados' => $detallesAgrupados,
                'clienteNombre' => $cliente->Nombre ?? 'Sin cliente',
                'sucursalNombre' => $sucursal->Nombre ?? 'Sin sucursal',
                'operadorNombre' => $operador->nombre ?? 'Sin operador',
                'totalGeneral' => $totalGeneral,
                'idIdentificador' => (int) $idIdentificador,
                'grupoCliente' => [
                    'IdGrupoCliente' => $grupoCliente->IdGrupoCliente,
                    'Nombre' => $grupoCliente->Nombre,
                ],
                'progresoGrupos' => $progresoGrupos,
                'cumpleMinimos' => $cumpleMinimos,
                'productosSinMinimo' => $productosSinMinimo,
                'tipoPrecio' => $pedido->TipoPrecio,
                'subclientes' => $subclientes,
                'horaLimite' => $horaLimite ? $horaLimite->Hora : null,
                'horaLimiteFormateada' => $horaLimite ? $horaLimite->HoraFormateada : null,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en review: ' . $e->getMessage());
            return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                ->with('error', 'Error al cargar la revisión: ' . $e->getMessage());
        }
    }

    // ============================================================
    // FINALIZAR PEDIDO
    // ============================================================

    public function finalizarPedido(Request $request, $idPedido)
    {
        \Log::info('=== 🚀 FINALIZAR PEDIDO ===');
        \Log::info('📝 Datos recibidos:', $request->all());

        $request->validate([
            'IdCliente' => 'required|exists:todos_cliente,IdCliente',
            'Observaciones' => 'nullable|string|max:500',
            'TipoPrecio' => 'nullable|in:sin_factura,con_factura',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $sucursalValida = ClienteSucursal::where('IdClienteSucursal', $sucursalId)
            ->where('IdCliente', $clienteId)
            ->exists();

        if (!$sucursalValida) {
            return response()->json([
                'success' => false,
                'message' => 'La sucursal de la sesión no es válida. Contacte al administrador.'
            ], 400);
        }

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

        $fechaManana = Carbon::now('America/La_Paz')->addDay()->format('Y-m-d');

        if ($fechaEntregaFormateada === $fechaManana) {
            $horaLimite = HoraLimite::obtenerHoraActiva(
                HoraLimite::TIPO_PEDIDO_CLIENTE_MAYORISTA
            );

            $horaActual = (int) Carbon::now('America/La_Paz')->format('H');

            if ($horaLimite && $horaActual >= $horaLimite->Hora) {
                return response()->json([
                    'success' => false,
                    'message' => "Hora máxima para finalizar pedido es {$horaLimite->Hora}:00!"
                ], 422);
            }
        }

        try {
            $grupoCliente = $this->getGrupoDelOperador();

            if (!$grupoCliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu usuario no tiene un Grupo de Clientes asignado.'
                ], 400);
            }

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

            $productosSinMinimo = $this->validarProductosDelPedidoTienenMinimo($pedido);

            if (!empty($productosSinMinimo)) {
                $errores = array_map(function ($p) {
                    return "• {$p['Codigo']} - {$p['Descripcion']}: ya no tiene mínimo configurado";
                }, $productosSinMinimo);

                return response()->json([
                    'success' => false,
                    'message' => 'Algunos productos del pedido ya no tienen mínimo configurado. Contacte al administrador:',
                    'errores' => $errores,
                ], 400);
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

            $progresoGrupos = $this->calcularProgresoGrupos($pedido, $grupoCliente->IdGrupoCliente);

            $gruposQueNoCumplen = collect($progresoGrupos)->filter(function($item) {
                return !$item['Cumple'];
            })->values();

            if ($gruposQueNoCumplen->isNotEmpty()) {
                $errores = $gruposQueNoCumplen->map(function($item) {
                    $tipo = $item['Tipo'] === 'producto' ? 'Producto' : 'Grupo';
                    return "• [{$tipo}] {$item['NombreGrupo']}: faltan {$item['Falta']} und (tienes {$item['CantidadPedida']}, mínimo {$item['CantidadMinima']})";
                })->toArray();

                return response()->json([
                    'success' => false,
                    'message' => 'No se puede finalizar el pedido. Faltan mínimos:',
                    'errores' => $errores,
                ], 400);
            }

            $totales = $this->calcularTotales($pedido->IdPedidoCliente);

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

            $pedido->update([
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'TipoPrecio' => $request->TipoPrecio ?? $pedido->TipoPrecio,
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

    // ============================================================
    // MOSTRAR PEDIDO
    // ============================================================

    public function show($id)
    {
        try {
            $clienteId = session('cliente_id');

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with([
                    'cliente', 'sucursal', 'operador',
                    'detalles.producto', 'detalles.contenedor',
                    'detalles.subClienteOperador.identificador'
                ])
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

                $subCliente = $primerItem->subClienteOperador;
                $subClienteNombre = $subCliente
                    ? ($subCliente->Alias ?: ($subCliente->identificador->Nombre ?? null))
                    : null;

                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'SubClienteNombre' => $subClienteNombre,
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

    public function getDetalles($id)
    {
        try {
            $clienteId = session('cliente_id');

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with([
                    'detalles.producto',
                    'detalles.contenedor',
                    'detalles.subClienteOperador.identificador'
                ])
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

                $subCliente = $primerItem->subClienteOperador;
                $subClienteNombre = $subCliente
                    ? ($subCliente->Alias ?: ($subCliente->identificador->Nombre ?? null))
                    : null;

                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'SubClienteNombre' => $subClienteNombre,
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

    // ============================================================
    // PDF
    // ============================================================

    public function generarPdf($id)
    {
        try {
            if (ob_get_length()) {
                ob_end_clean();
            }

            $clienteId = session('cliente_id');

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->with([
                    'detalles.producto',
                    'detalles.contenedor.tipoContenedor',
                    'detalles.subClienteOperador.identificador'
                ])
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

            $configOperador = OperadorPedidoCliente::where('IdOperador', $pedido->IdOperador)
                ->where('ActivoInactivo', 1)
                ->first();

            $destino = null;
            $tipoUbicacion = null;

            if ($configOperador) {
                $ubicaciones = [];
                if ($configOperador->Ciudad == 1) $ubicaciones[] = 'Ciudad';
                if ($configOperador->Provincia == 1) $ubicaciones[] = 'Provincia';

                $tipoUbicacion = !empty($ubicaciones) ? implode(' / ', $ubicaciones) : null;

                $destinoRaw = $configOperador->getAttribute('Destino');
                $destino = trim((string) ($destinoRaw ?? ''));
                if ($destino === '') {
                    $destino = null;
                }
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

                    $subCliente = $primerItem->subClienteOperador;
                    $subClienteNombre = $subCliente
                        ? ($subCliente->Alias ?: ($subCliente->identificador->Nombre ?? null))
                        : null;

                    return [
                        'OrdenContenedor' => intval($orden),
                        'IdContenedor' => $primerItem->IdContenedor,
                        'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                        'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                        'SubClienteNombre' => $subClienteNombre,
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

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY($colDer_label, $yInfoDer);
            $pdf->Cell(30, $altoFila, 'Tipo Precio:', 0, 0, 'L');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($colDer_valor, $yInfoDer);
            $pdf->Cell(58, $altoFila, $pedido->TipoPrecioTexto, 0, 0, 'L');
            $yInfoDer += $altoFila;

            if (!empty($tipoUbicacion)) {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($colDer_label, $yInfoDer);
                $pdf->Cell(30, $altoFila, 'Tipo:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($colDer_valor, $yInfoDer);
                $pdf->Cell(58, $altoFila, $tipoUbicacion, 0, 0, 'L');
                $yInfoDer += $altoFila;
            }

            if (!empty($destino)) {
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetXY($colDer_label, $yInfoDer);
                $pdf->Cell(30, $altoFila, 'Destino:', 0, 0, 'L');
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetXY($colDer_valor, $yInfoDer);
                $pdf->Cell(68, $altoFila, $destino, 0, 0, 'L');
                $yInfoDer += $altoFila;
            }

            $y = max($yInfo, $yInfoDer) + 3;

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

                if (!empty($grupo['SubClienteNombre'])) {
                    $pdf->SetFont('helvetica', 'I', 7);
                    $pdf->SetTextColor(80, 80, 80);
                    $pdf->SetXY(10, $y);
                    $pdf->Cell(196, 4, '  Subcliente: ' . $grupo['SubClienteNombre'], 'LR', 1, 'L', 1);
                    $y += 4;
                    $pdf->SetFont('helvetica', '', 7);
                    $pdf->SetTextColor(0, 0, 0);
                }

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

    // ============================================================
    // HELPERS FINALES
    // ============================================================

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

    public function recalcularTipoPrecio(Request $request, $id)
    {
        $request->validate([
            'TipoPrecio' => 'required|in:sin_factura,con_factura',
        ]);

        $clienteId = session('cliente_id');

        try {
            $grupoCliente = $this->getGrupoDelOperador();

            if (!$grupoCliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu usuario no tiene un Grupo de Clientes asignado.'
                ], 400);
            }

            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $id)
                ->where('ActivoInactivo', 0)
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado o ya finalizado.'
                ], 404);
            }

            $tipoPrecioNuevo = $request->TipoPrecio;

            if ($pedido->TipoPrecio === $tipoPrecioNuevo) {
                $pedido->load(['detalles.producto', 'detalles.contenedor', 'detalles.subClienteOperador.identificador']);
            } else {
                DB::beginTransaction();

                try {
                    $preciosGrupo = GrupoClienteProducto::where('IdGrupoCliente', $grupoCliente->IdGrupoCliente)
                        ->where('ActivoInactivo', 1)
                        ->get()
                        ->keyBy('IdProducto');

                    $detalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)->get();
                    $totalGeneral = 0;

                    foreach ($detalles as $detalle) {
                        $precioGrupo = $preciosGrupo[$detalle->IdProducto] ?? null;

                        if (!$precioGrupo) continue;

                        $nuevoPrecio = $tipoPrecioNuevo === 'con_factura'
                            ? $precioGrupo->PrecioConFactura
                            : $precioGrupo->PrecioSinFactura;

                        if ($nuevoPrecio === null || $nuevoPrecio <= 0) continue;

                        $detalle->update(['Precio' => $nuevoPrecio]);
                        $totalGeneral += $detalle->Cantidad * $nuevoPrecio;
                    }

                    $pedido->update([
                        'TipoPrecio' => $tipoPrecioNuevo,
                        'TotalGeneral' => $totalGeneral,
                    ]);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            }

            $pedido->load(['detalles.producto', 'detalles.contenedor', 'detalles.subClienteOperador.identificador']);

            $detallesAgrupados = $pedido->detalles->groupBy('OrdenContenedor')->map(function($items, $orden) {
                $primerItem = $items->first();
                $contenedor = $primerItem->contenedor;
                $total = $items->sum('Cantidad');
                $subtotal = $items->sum(function($item) {
                    return $item->Cantidad * $item->Precio;
                });

                $subCliente = $primerItem->subClienteOperador;
                $subClienteNombre = $subCliente
                    ? ($subCliente->Alias ?: ($subCliente->identificador->Nombre ?? null))
                    : null;

                return [
                    'IdContenedor' => $primerItem->IdContenedor,
                    'Codigo' => $contenedor ? $contenedor->Codigo : '-',
                    'Orden' => intval($orden),
                    'CapacidadTotal' => $contenedor ? $contenedor->CapacidadTotal : 0,
                    'IdSubClienteOperador' => $primerItem->IdSubClienteOperador,
                    'SubClienteNombre' => $subClienteNombre,
                    'productos' => $items->map(function($item) {
                        return [
                            'IdProducto' => $item->IdProducto,
                            'Codigo' => $item->producto ? $item->producto->Codigo : '-',
                            'Descripcion' => $item->producto ? $item->producto->Descripcion : '-',
                            'Cantidad' => (float) $item->Cantidad,
                            'Precio' => (float) $item->Precio,
                            'Subtotal' => (float) ($item->Cantidad * $item->Precio),
                            'IdGrupoAnalisis' => $item->producto ? $item->producto->IdGrupoAnalisis : null,
                            'IdPedidoClienteDetalle' => $item->IdPedidoClienteDetalle,
                        ];
                    }),
                    'total_unidades' => (float) $total,
                    'subtotal' => (float) $subtotal,
                ];
            })->values();

            $totalGeneralFinal = $pedido->detalles->sum(function($item) {
                return $item->Cantidad * $item->Precio;
            });

            return response()->json([
                'success' => true,
                'message' => 'Precios recalculados correctamente',
                'tipo_precio' => $pedido->TipoPrecio,
                'detalles_agrupados' => $detallesAgrupados,
                'total_general' => (float) $totalGeneralFinal,
            ]);

        } catch (\Exception $e) {
            Log::error('Error al recalcular tipo precio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al recalcular: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // API: VALIDAR HORA LÍMITE PARA CLIENTES MAYORISTAS
    // ============================================================

    public function apiValidarHoraLimite(Request $request)
    {
        $request->validate([
            'FechaEntrega' => 'required|date',
        ]);

        $fechaManana = Carbon::now('America/La_Paz')->addDay()->format('Y-m-d');

        if ($request->FechaEntrega !== $fechaManana) {
            return response()->json([
                'success' => true,
                'valido' => true,
                'fecha_manana' => false,
            ]);
        }

        $horaLimite = HoraLimite::obtenerHoraActiva(
            HoraLimite::TIPO_PEDIDO_CLIENTE_MAYORISTA
        );

        $horaActual = (int) Carbon::now('America/La_Paz')->format('H');
        $valido = !$horaLimite || $horaActual < $horaLimite->Hora;

        return response()->json([
            'success' => true,
            'valido' => $valido,
            'fecha_manana' => true,
            'hora_actual' => $horaActual,
            'hora_limite' => $horaLimite ? $horaLimite->Hora : null,
            'hora_limite_formateada' => $horaLimite ? $horaLimite->HoraFormateada : null,
            'mensaje' => $valido
                ? null
                : "Hora máxima para finalizar pedido es {$horaLimite->Hora}:00!",
        ]);
    }
}