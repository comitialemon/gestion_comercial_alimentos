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
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoClienteMinimo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClientePago;
use App\Models\Gestion\Banco\BancoCredencial;
use App\Services\Gestion\Banco\BancoFactory;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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

    private function invalidarCacheProgreso($idPedido, $clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        cache()->forget("progreso_pedido_{$idPedido}_{$clienteId}_{$sucursalId}");
    }

    private function invalidarCacheProductosSinMinimo($idPedido, $clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        cache()->forget("productos_sin_minimo_{$idPedido}_{$clienteId}_{$sucursalId}");
    }

    private function validarProductosDelPedidoTienenMinimo($pedido)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $idsProductosEnPedido = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)
            ->pluck('IdProducto')
            ->unique()
            ->toArray();

        if (empty($idsProductosEnPedido)) {
            return [];
        }

        $mapaProductos = ProductoMinimo::obtenerMapa($clienteId, $sucursalId);
        $idsSinMinimo = array_diff($idsProductosEnPedido, array_keys($mapaProductos));

        if (empty($idsSinMinimo)) {
            return [];
        }

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

    private function calcularProgresoGrupos($pedidoBorrador, $idGrupoCliente = null)
    {
        if (!$pedidoBorrador || !$idGrupoCliente) {
            return [];
        }

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('pedidos_clientes_detalle as d')
            ->join('inventario_productodetalle as p', 'd.IdProducto', '=', 'p.IdProducto')
            ->where('d.IdPedidoCliente', $pedidoBorrador->IdPedidoCliente)
            ->select('d.IdProducto', 'd.Cantidad', 'p.IdGrupoAnalisis')
            ->get();

        $progreso = [];

        // 1. MÍNIMOS POR GRUPO DE ANÁLISIS
        $mapaGrupos = GrupoClienteMinimo::obtenerMapaPorGrupoCliente($idGrupoCliente, $clienteId, $sucursalId);

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
                if (!isset($acumuladoPorGrupo[$idGrupo])) {
                    continue;
                }

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

        // 2. MÍNIMOS POR PRODUCTO
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
                if (!isset($acumuladoPorProducto[$idProd])) {
                    continue;
                }

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
            ->with([
                'cliente',
                'sucursal',
                'operador',
                'pagoPendiente',
                'pagoExitoso',
            ])
            ->orderBy('IdPedidoCliente', 'desc')
            ->paginate(20);

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

        $pedidoEsperandoPago = PedidoCliente::porContexto()
            ->where('IdOperador', $operadorId)
            ->where('EstadoPedido', 'Esperando Pago')
            ->with(['pagoPendiente'])
            ->latest('IdPedidoCliente')
            ->first();

        $tienePedidoEsperandoPago = $pedidoEsperandoPago !== null;

        $pedidoBorrador = PedidoCliente::obtenerBorradorActivo();
        $tieneBorradorActivo = $pedidoBorrador !== null;

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/Index', [
            'pedidos' => $pedidos,
            'puedeHacerPedidos' => $puedeHacerPedidos,
            'razonNoPuedePedir' => $razonNoPuedePedir,
            'tieneBorradorActivo' => $tieneBorradorActivo,
            'tienePedidoEsperandoPago' => $tienePedidoEsperandoPago,
            'pedidoEsperandoPago' => $pedidoEsperandoPago ? [
                'IdPedidoCliente' => $pedidoEsperandoPago->IdPedidoCliente,
                'NumeroPedido' => $pedidoEsperandoPago->NumeroPedido,
                'TotalGeneral' => (float) $pedidoEsperandoPago->TotalGeneral,
                'FechaPedido' => $pedidoEsperandoPago->FechaPedido?->format('Y-m-d H:i:s'),
                'TienePagoPendiente' => $pedidoEsperandoPago->pagoPendiente !== null,
                'MontoPagoPendiente' => $pedidoEsperandoPago->pagoPendiente
                    ? (float) $pedidoEsperandoPago->pagoPendiente->Monto
                    : null,
                'SegundosRestantes' => $pedidoEsperandoPago->pagoPendiente
                    ? $pedidoEsperandoPago->pagoPendiente->segundosRestantes()
                    : null,
            ] : null,
        ]);
    }

    // ============================================================
    // NUEVO PEDIDO - MENÚ DE CONTENEDORES
    // ============================================================

    public function create(Request $request)
    {
        Log::info('=== 🔍 DEBUG create() ===');
        Log::info('Session cliente_id: ' . session('cliente_id'));
        Log::info('Session cliente_sucursal_id: ' . session('cliente_sucursal_id'));
        Log::info('Session operador_id: ' . session('operador_id'));

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        if (!$clienteId || !$sucursalId) {
            Log::error('❌ FALTA SESIÓN');
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', 'Tu sesión expiró. Por favor, vuelve a iniciar sesión.');
        }

        $tipoPrecio = $request->get('tipo_precio', 'sin_factura');
        if (!in_array($tipoPrecio, ['sin_factura', 'con_factura'])) {
            $tipoPrecio = 'sin_factura';
        }

        $idIdentificador = $this->getIdIdentificadorOperador();
        if (!$idIdentificador) {
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', 'No se encontró el perfil del operador. Contacte al administrador.');
        }

        $grupoCliente = $this->getGrupoDelOperador();
        if (!$grupoCliente) {
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', "Tu usuario no tiene un Grupo de Clientes asignado. Contacte al administrador.");
        }

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

        if ($contenedores->isEmpty()) {
            return redirect()->route('operacion.pedidos-clientes.pedidos.index')
                ->with('error', "Tu grupo '{$grupoCliente->Nombre}' no tiene contenedores asignados. Contacte al administrador.");
        }

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

        // MAPA DE GRUPOS CON NOMBRE
        $mapaGruposRaw = GrupoClienteMinimo::obtenerMapaPorGrupoCliente($grupoCliente->IdGrupoCliente, $clienteId, $sucursalId);
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

        // MAPA DE PRODUCTOS CON INFO
        $minimosProductos = ProductoMinimo::obtenerMapa($clienteId, $sucursalId);
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
            'minimosGrupos' => $minimosGrupos,
            'minimosProductos' => $minimosProductos,
            'infoProductos' => $infoProductos,
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

        $gruposConMinimoDelCliente = GrupoClienteMinimo::obtenerMapaPorGrupoCliente(
            $grupoCliente->IdGrupoCliente,
            $clienteId,
            $sucursalId
        );

        if (empty($gruposConMinimoDelCliente)) {
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
                    'mensaje' => 'Tu grupo de clientes no tiene mínimos configurados. Contacta al administrador.',
                ]
            ]);
        }

        $idsGruposConMinimoDelCliente = array_map('intval', array_keys($gruposConMinimoDelCliente));

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
                    'mensaje' => 'No hay productos configurados para pedidos. Contacta al administrador.',
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
            ->whereIn('IdGrupoAnalisis', $idsGruposConMinimoDelCliente)
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

            $this->invalidarCacheProgreso($pedido->IdPedidoCliente);
            $this->invalidarCacheProductosSinMinimo($pedido->IdPedidoCliente);

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

            $this->invalidarCacheProgreso($request->IdPedidoCliente);
            $this->invalidarCacheProductosSinMinimo($request->IdPedidoCliente);

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

            $this->invalidarCacheProgreso($pedido->IdPedidoCliente);
            $this->invalidarCacheProductosSinMinimo($pedido->IdPedidoCliente);

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

            $this->invalidarCacheProgreso($idPedido);
            $this->invalidarCacheProductosSinMinimo($idPedido);

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
                ->whereIn('EstadoPedido', ['Borrador', 'Esperando Pago'])
                ->with([
                    'detalles.producto',
                    'detalles.contenedor',
                    'detalles.subClienteOperador.identificador',
                    'pagoPendiente',
                ])
                ->first();

            if (!$pedido) {
                return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                    ->with('error', 'El pedido no existe, ya fue finalizado o ya tiene un pago en proceso.');
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

            $pagoPendiente = null;
            if ($pedido->pagoPendiente) {
                $pagoPendiente = [
                    'IdPagoPedido' => $pedido->pagoPendiente->IdPagoPedido,
                    'QrId' => $pedido->pagoPendiente->QrId,
                    'Monto' => (float) $pedido->pagoPendiente->Monto,
                    'Estado' => $pedido->pagoPendiente->Estado,
                    'SegundosRestantes' => $pedido->pagoPendiente->segundosRestantes(),
                    'FechaCreacion' => $pedido->pagoPendiente->FechaCreacion?->format('Y-m-d H:i:s'),
                ];
            }

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
                'pagoPendiente' => $pagoPendiente,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en review: ' . $e->getMessage());
            return redirect()->route('operacion.pedidos-clientes.pedidos.create')
                ->with('error', 'Error al cargar la revisión: ' . $e->getMessage());
        }
    }

    // ============================================================
    // OBTENER PROGRESO
    // ============================================================
    public function getProgreso($id)
    {
        try {
            $clienteId = session('cliente_id');

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

            $grupoCliente = $this->getGrupoDelOperador();
            if (!$grupoCliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu usuario no tiene un Grupo de Clientes asignado.'
                ], 400);
            }

            $progreso = $this->calcularProgresoGrupos($pedido, $grupoCliente->IdGrupoCliente);
            $productosSinMinimo = $this->validarProductosDelPedidoTienenMinimo($pedido);
            $totales = $this->calcularTotales($pedido->IdPedidoCliente);

            return response()->json([
                'success' => true,
                'data' => [
                    'progresoGrupos' => $progreso,
                    'productosSinMinimo' => $productosSinMinimo,
                    'totales' => $totales,
                    'cumpleMinimos' => collect($progreso)->every(fn($item) => $item['Cumple']) && empty($productosSinMinimo),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getProgreso: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular el progreso: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================================
    // GENERAR QR DE PAGO
    // ============================================================
    public function generarQRPedido(Request $request, $idPedido)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            // ============================================================
            // 1. VALIDAR PEDIDO
            // ============================================================
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdOperador', $operadorId)
                ->where('IdPedidoCliente', $idPedido)
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado o no tienes permiso.'
                ], 404);
            }

            if (!in_array($pedido->EstadoPedido, ['Borrador', 'Esperando Pago'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido no puede generar un QR en su estado actual (' . $pedido->EstadoPedido . ').'
                ], 422);
            }

            // ============================================================
            // 2. VALIDACIONES
            // ============================================================
            $grupoCliente = $this->getGrupoDelOperador();
            if (!$grupoCliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tu usuario no tiene un Grupo de Clientes asignado.'
                ], 400);
            }

            $totalDetalles = PedidoClienteDetalle::where('IdPedidoCliente', $pedido->IdPedidoCliente)->count();
            if ($totalDetalles === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El pedido no tiene productos.'
                ], 422);
            }

            $productosSinMinimo = $this->validarProductosDelPedidoTienenMinimo($pedido);
            if (!empty($productosSinMinimo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Algunos productos ya no tienen mínimo configurado.',
                    'errores' => array_map(fn($p) => "• {$p['Codigo']} - {$p['Descripcion']}", $productosSinMinimo),
                ], 422);
            }

            $progresoGrupos = $this->calcularProgresoGrupos($pedido, $grupoCliente->IdGrupoCliente);
            $gruposQueNoCumplen = collect($progresoGrupos)->filter(fn($item) => !$item['Cumple']);

            if ($gruposQueNoCumplen->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede generar QR. Faltan mínimos.',
                    'errores' => $gruposQueNoCumplen->map(fn($item) => "• {$item['NombreGrupo']}: faltan {$item['Falta']} und")->toArray(),
                ], 422);
            }

            if ($pedido->TotalGeneral <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El total del pedido debe ser mayor a cero.'
                ], 422);
            }

            // ============================================================
            // 3. CANCELAR QR PENDIENTE ANTERIOR (si existe)
            // ============================================================
            $pagoAnterior = PedidoClientePago::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                ->where('Estado', 'PENDIENTE')
                ->first();

            if ($pagoAnterior) {
                try {
                    $credencialAnterior = $pagoAnterior->credencial;
                    if ($credencialAnterior) {
                        $serviceAnterior = BancoFactory::desdeCredencial($credencialAnterior);
                        $serviceAnterior->anularQR($pagoAnterior->QrId);
                    }
                } catch (\Exception $e) {
                    Log::warning('No se pudo anular QR anterior', ['error' => $e->getMessage()]);
                }

                $pagoAnterior->update([
                    'Estado' => 'ANULADO',
                    'FechaAnulacion' => now(),
                    'FechaUltimaActualizacion' => now(),
                ]);
            }

            // ============================================================
            // 4. OBTENER CREDENCIAL ACTIVA
            // ============================================================
            $codigoBanco = $request->input('CodigoBanco');

            $queryCredencial = BancoCredencial::porCliente($clienteId)
                ->where('ActivoInactivo', 1);

            if ($codigoBanco) {
                $queryCredencial->porBanco($codigoBanco);
            }

            $credencial = $queryCredencial->first();

            if (!$credencial) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay credencial activa del banco. Contacta al administrador.'
                ], 400);
            }

            // ============================================================
            // 5. CONSTRUIR GLOSA DESCRIPTIVA
            // ✅ Formato: "Pedido 06/10/26 - Juan Pérez"
            // ============================================================
            $nombreOperador = $this->getNombreOperador();

            // ✅ Usar FechaPedido (cuando se creó el pedido), fallback a now()
            $fechaPedido = $pedido->FechaPedido
                ? Carbon::parse($pedido->FechaPedido)
                : now();

            $fechaFormateada = $fechaPedido->format('d/m/y'); // 06/10/26

            // Prefijo base
            $prefijo = "Pedido {$fechaFormateada}";

            // Agregar operador si tiene nombre válido
            if (!empty($nombreOperador) && $nombreOperador !== 'Sin nombre') {
                $glosa = "{$prefijo} - {$nombreOperador}";
            } else {
                $glosa = $prefijo;
            }

            // ✅ Truncar si excede 60 chars (límite BGAN)
            // Dejamos 3 chars para "..." si es necesario
            if (mb_strlen($glosa) > 60) {
                $glosa = mb_substr($glosa, 0, 57) . '...';
            }

            Log::info('📝 Glosa construida para QR', [
                'IdPedidoCliente' => $pedido->IdPedidoCliente,
                'FechaPedido' => $fechaPedido->format('Y-m-d H:i:s'),
                'Operador' => $nombreOperador,
                'Glosa' => $glosa,
                'Longitud' => mb_strlen($glosa),
            ]);

            // ============================================================
            // 6. GENERAR QR EN EL BANCO (FACTORY)
            // ============================================================
            $service = BancoFactory::desdeCredencial($credencial);

            $transactionId = sprintf(
                'PED-%d-%d-%s',
                $pedido->IdPedidoCliente,
                $operadorId,
                strtoupper(substr(uniqid(), -6))
            );

            $qr = $service->generarQR(
                $transactionId,
                (float) $pedido->TotalGeneral,
                $glosa,
                'BOB',
                null,
                $credencial->BranchCode,
                true,   // singleUse
                false   // modifyAmount
            );

            // ============================================================
            // 7. GUARDAR EN pedidos_clientes_pagos
            // ============================================================
            $pago = PedidoClientePago::create([
                'IdPedidoCliente' => $pedido->IdPedidoCliente,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperador' => $operadorId,
                'IdCredencial' => $credencial->IdCredencial,
                'QrId' => $qr->qrId,
                'TransactionId' => $transactionId,
                'BranchCode' => $credencial->BranchCode,
                'CodigoBanco' => $credencial->CodigoBanco,
                'Moneda' => 'BOB',
                'Monto' => $pedido->TotalGeneral,
                'Descripcion' => $glosa,
                'FechaVencimiento' => date('Y-m-d'),
                'Estado' => 'PENDIENTE',
                'FechaCreacion' => now(),
                'DatosGeneracion' => $qr->respuestaCompleta,
            ]);

            // ============================================================
            // 8. ACTUALIZAR ESTADO DEL PEDIDO
            // ============================================================
            $pedido->update([
                'EstadoPedido' => 'Esperando Pago',
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            Log::info('✅ QR generado para pedido', [
                'IdPedidoCliente' => $pedido->IdPedidoCliente,
                'QrId' => $qr->qrId,
                'Monto' => $pedido->TotalGeneral,
                'Banco' => $credencial->CodigoBanco,
                'Glosa' => $glosa,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'QR generado. Esperando pago.',
                'qr' => [
                    'IdPagoPedido' => $pago->IdPagoPedido,
                    'QrId' => $qr->qrId,
                    'QrImage' => $qr->qrImage,
                    'Monto' => (float) $pedido->TotalGeneral,
                    'Descripcion' => $glosa,
                    'ExpiraEnSegundos' => 900,
                    'CodigoBanco' => $credencial->CodigoBanco,
                ],
                'pedido_id' => $pedido->IdPedidoCliente,
            ]);

        } catch (\Exception $e) {
            Log::error('Error generando QR pedido', [
                'IdPedidoCliente' => $idPedido,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar QR: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // VERIFICAR ESTADO DEL PAGO (POLLING)
    // ============================================================
    public function verificarEstadoPago($idPedido)
    {
        $clienteId = session('cliente_id');

        try {
            $pago = PedidoClientePago::where('IdPedidoCliente', $idPedido)
                ->where('IdCliente', $clienteId)
                ->latest('IdPagoPedido')
                ->first();

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay pago asociado a este pedido.',
                    'estado' => 'SIN_PAGO',
                ], 404);
            }

            if ($pago->Estado === 'PAGADO') {
                return response()->json([
                    'success' => true,
                    'estado' => 'PAGADO',
                    'monto_pagado' => (float) $pago->MontoPagado,
                    'fecha_pago' => $pago->FechaPago?->format('Y-m-d H:i:s'),
                    'datos_pago' => $pago->DatosPago,
                ]);
            }

            if ($pago->haExpirado() && $pago->Estado === 'PENDIENTE') {
                $pago->update([
                    'Estado' => 'EXPIRADO',
                    'FechaExpiracion' => now(),
                    'FechaUltimaActualizacion' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'estado' => 'EXPIRADO',
                    'message' => 'El QR ha expirado. Genera uno nuevo.',
                ]);
            }

            // Throttle de 3 segundos
            $cacheKey = "pago_pedido_consulta_{$pago->IdPagoPedido}";
            if (Cache::has($cacheKey)) {
                return response()->json([
                    'success' => true,
                    'estado' => $pago->Estado,
                    'segundos_restantes' => $pago->segundosRestantes(),
                ]);
            }
            Cache::put($cacheKey, true, 3);

            $credencial = $pago->credencial;
            if (!$credencial) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credencial no encontrada.',
                    'estado' => 'ERROR',
                ], 500);
            }

            $service = BancoFactory::desdeCredencial($credencial);
            $estadoBanco = $service->consultarEstadoQR($pago->QrId);

            if ($estadoBanco->estaPagado()) {
                $primerPago = $estadoBanco->getPrimerPago();

                DB::beginTransaction();
                try {
                    $pago->update([
                        'Estado' => 'PAGADO',
                        'StatusQrCodeBanco' => 1,
                        'MontoPagado' => $primerPago?->amount ?? $pago->Monto,
                        'FechaPago' => now(),
                        'DatosPago' => $estadoBanco->respuestaCompleta['payment'] ?? [],
                        'FechaUltimaActualizacion' => now(),
                    ]);

                    $pedido = PedidoCliente::find($pago->IdPedidoCliente);
                    if ($pedido && $pedido->EstadoPedido === 'Esperando Pago') {
                        // Asignar número de pedido
                        $maxNumero = PedidoCliente::where('IdCliente', $pedido->IdCliente)
                            ->where('IdSucursal', $pedido->IdSucursal)
                            ->where('NumeroPedido', '!=', '0')
                            ->whereNotNull('NumeroPedido')
                            ->max(DB::raw('CAST(NumeroPedido AS UNSIGNED)')) ?? 0;

                        $nuevoNumero = $maxNumero + 1;
                        $numeroFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);

                        $pedido->update([
                            'EstadoPedido' => 'Pendiente',
                            'ActivoInactivo' => 1,
                            'NumeroPedido' => $numeroFormateado,
                            // ⚠️ NO tocar FechaPedido (se conserva la original)
                            'IdOperadorActualiza' => session('operador_id'),
                            'FechaActualiza' => now(),
                        ]);
                    }

                    DB::commit();

                    Log::info('✅ Pago confirmado', [
                        'IdPedidoCliente' => $pago->IdPedidoCliente,
                        'IdPagoPedido' => $pago->IdPagoPedido,
                        'Banco' => $credencial->CodigoBanco,
                    ]);

                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

                return response()->json([
                    'success' => true,
                    'estado' => 'PAGADO',
                    'monto_pagado' => (float) $pago->MontoPagado,
                    'fecha_pago' => $pago->FechaPago?->format('Y-m-d H:i:s'),
                    'datos_pago' => $pago->DatosPago,
                ]);
            }

            if ($estadoBanco->estaAnulado()) {
                $pago->update([
                    'Estado' => 'ANULADO',
                    'StatusQrCodeBanco' => 9,
                    'FechaAnulacion' => now(),
                    'FechaUltimaActualizacion' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'estado' => 'ANULADO',
                ]);
            }

            return response()->json([
                'success' => true,
                'estado' => 'PENDIENTE',
                'segundos_restantes' => $pago->segundosRestantes(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error verificando estado pago', [
                'IdPedidoCliente' => $idPedido,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar: ' . $e->getMessage(),
                'estado' => 'ERROR',
            ], 500);
        }
    }

    // ============================================================
    // CANCELAR QR
    // ============================================================
    public function cancelarQRPedido($idPedido)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        try {
            $pedido = PedidoCliente::where('IdCliente', $clienteId)
                ->where('IdPedidoCliente', $idPedido)
                ->first();

            if (!$pedido) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pedido no encontrado.'
                ], 404);
            }

            $pago = PedidoClientePago::where('IdPedidoCliente', $pedido->IdPedidoCliente)
                ->where('Estado', 'PENDIENTE')
                ->latest('IdPagoPedido')
                ->first();

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay QR pendiente para cancelar.'
                ], 422);
            }

            try {
                $credencial = $pago->credencial;
                if ($credencial) {
                    $service = BancoFactory::desdeCredencial($credencial);
                    $service->anularQR($pago->QrId);
                }
            } catch (\Exception $e) {
                Log::warning('No se pudo anular QR en el banco', ['error' => $e->getMessage()]);
            }

            $pago->update([
                'Estado' => 'ANULADO',
                'FechaAnulacion' => now(),
                'FechaUltimaActualizacion' => now(),
            ]);

            $pedido->update([
                'EstadoPedido' => 'Borrador',
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'QR cancelado correctamente.',
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelando QR', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar: ' . $e->getMessage(),
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
                ['key' => 'Esperando Pago', 'label' => 'Esperando Pago', 'icon' => 'fa-qrcode', 'color' => 'purple'],
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
            Log::error('Error en getDetalles: ' . $e->getMessage());
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

            // ✅ Verificar espacio antes de "RESUMEN POR TIPO DE CONTENEDOR"
            if ((270 - $y) < 30) {
                $pdf->AddPage();
                $y = 15;
            }

            // ============================================================
            // RESUMEN POR TIPO DE CONTENEDOR
            // ============================================================
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

            // ============================================================
            // ✅ RESUMEN DE PRODUCTOS DEL PEDIDO (LISTA VERTICAL)
            // ============================================================
            $y += 5;

            // Agrupar productos del pedido por IdProducto
            $productosPedido = [];
            foreach ($pedido->detalles as $det) {
                $idProd = $det->IdProducto;
                if (!isset($productosPedido[$idProd])) {
                    $productosPedido[$idProd] = [
                        'nombre' => $det->producto->Descripcion ?? 'Sin nombre',
                        'cantidad' => 0,
                        'orden' => $det->producto->OrdenInformes ?? 0,
                    ];
                }
                $productosPedido[$idProd]['cantidad'] += floatval($det->Cantidad);
            }

            // Ordenar por OrdenInformes
            uasort($productosPedido, function ($a, $b) {
                return $a['orden'] <=> $b['orden'];
            });

            $productosPedido = array_values($productosPedido);
            $cantidadProductos = count($productosPedido);

            if ($cantidadProductos > 0) {
                // ✅ Espacio mínimo: título (7) + cabecera (6) + 1 fila (5) + total (6) = 24mm
                $alturaMinima = 24;

                if ((270 - $y) < $alturaMinima) {
                    $pdf->AddPage();
                    $y = 15;
                }

                // ===== TÍTULO =====
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetTextColor(30, 60, 120);
                $pdf->SetXY(10, $y);
                $pdf->Cell(196, 6, 'RESUMEN DE PRODUCTOS DEL PEDIDO', 0, 1, 'C');
                $y += 7;

                // ===== ANCHOS =====
                $anchoNum = 10;
                $anchoProducto = 156;
                $anchoCantidad = 30;

                // ===== CABECERA DE LA TABLA =====
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(217, 225, 242);
                $pdf->SetTextColor(26, 35, 126);
                $pdf->SetDrawColor(120, 120, 120);
                $pdf->SetLineWidth(0.2);

                $pdf->SetXY(10, $y);
                $pdf->Cell($anchoNum,      6, '#',        1, 0, 'C', 1);
                $pdf->Cell($anchoProducto, 6, 'PRODUCTO', 1, 0, 'L', 1);
                $pdf->Cell($anchoCantidad, 6, 'CANTIDAD', 1, 1, 'C', 1);
                $y += 6;

                // ===== FILAS =====
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetTextColor(40, 40, 40);

                $sumaTotal = 0;
                $contadorProd = 0;
                $alturaFila = 5;

                foreach ($productosPedido as $prod) {
                    // ✅ Verificar espacio para la fila
                    if ((270 - $y) < ($alturaFila + 8)) {
                        $pdf->AddPage();
                        $y = 15;

                        // Redibujar título y cabecera
                        $pdf->SetFont('helvetica', 'B', 9);
                        $pdf->SetTextColor(30, 60, 120);
                        $pdf->SetXY(10, $y);
                        $pdf->Cell(196, 6, 'RESUMEN DE PRODUCTOS DEL PEDIDO (continuación)', 0, 1, 'C');
                        $y += 7;

                        $pdf->SetFont('helvetica', 'B', 8);
                        $pdf->SetFillColor(217, 225, 242);
                        $pdf->SetTextColor(26, 35, 126);
                        $pdf->SetDrawColor(120, 120, 120);
                        $pdf->SetXY(10, $y);
                        $pdf->Cell($anchoNum,      6, '#',        1, 0, 'C', 1);
                        $pdf->Cell($anchoProducto, 6, 'PRODUCTO', 1, 0, 'L', 1);
                        $pdf->Cell($anchoCantidad, 6, 'CANTIDAD', 1, 1, 'C', 1);
                        $y += 6;

                        $pdf->SetFont('helvetica', '', 7.5);
                        $pdf->SetTextColor(40, 40, 40);
                    }

                    $contadorProd++;

                    // Truncar nombre si es muy largo
                    $nombre = $prod['nombre'];
                    if (mb_strlen($nombre, 'UTF-8') > 90) {
                        $nombre = mb_substr($nombre, 0, 87, 'UTF-8') . '...';
                    }

                    $pdf->SetXY(10, $y);
                    $pdf->Cell($anchoNum,      $alturaFila, $contadorProd,                                1, 0, 'C', 0);
                    $pdf->Cell($anchoProducto, $alturaFila, ' ' . $nombre,                                1, 0, 'L', 0);
                    $pdf->Cell($anchoCantidad, $alturaFila, number_format($prod['cantidad'], 2, ',', '.'), 1, 1, 'C', 0);

                    $sumaTotal += $prod['cantidad'];
                    $y += $alturaFila;
                }

                // ===== FILA TOTAL =====
                if ((270 - $y) < 8) {
                    $pdf->AddPage();
                    $y = 15;
                }

                $pdf->SetFont('helvetica', 'B', 8.5);
                $pdf->SetFillColor(255, 243, 224);
                $pdf->SetTextColor(230, 81, 0);
                $pdf->SetDrawColor(120, 120, 120);

                $pdf->SetXY(10, $y);
                $pdf->Cell($anchoNum + $anchoProducto, 6, 'TOTAL GENERAL DE PRODUCTOS', 1, 0, 'R', 1);
                $pdf->Cell($anchoCantidad, 6, number_format($sumaTotal, 2, ',', '.'), 1, 1, 'C', 1);
                $y += 6;

                // Resetear colores
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetDrawColor(0, 0, 0);
            }

            // ============================================================
            // FIRMAS
            // ============================================================
            $y += 15;

            // ✅ Verificar espacio para firmas
            if ((270 - $y) < 12) {
                $pdf->AddPage();
                $y = 20;
            }

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

            $this->invalidarCacheProgreso($pedido->IdPedidoCliente);
            $this->invalidarCacheProductosSinMinimo($pedido->IdPedidoCliente);

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
    // API: VALIDAR HORA LÍMITE
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