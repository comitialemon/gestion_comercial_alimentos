<?php

namespace App\Http\Controllers\Gestion\Menu\Notificaciones;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Menu\Notificaciones\OperadorNotificacion;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Reclamo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $operadorId = $request->session()->get('operador_id');
        $clienteId  = $request->session()->get('cliente_id');

        if (!$operadorId || !$clienteId) {
            return response()->json(['total' => 0, 'modulos' => []]);
        }

        $roles = OperadorNotificacion::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with('tipo')
            ->get()
            ->pluck('tipo.Nombre')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->toArray();

        $notificaciones = [
            'total'   => 0,
            'modulos' => [],
        ];

        // ============================================================
        // 1. CALIDAD
        // ============================================================
        if (in_array('CALIDAD', $roles, true)) {
            $reclamosPendientes = Reclamo::where('IdCliente', $clienteId)
                ->whereIn('Estado', ['Pendiente de Revisión', 'En Revisión'])
                ->with(['pedido.cliente'])
                ->orderBy('FechaCreacion', 'desc')
                ->limit(5)
                ->get();

            $totalCalidad = Reclamo::where('IdCliente', $clienteId)
                ->whereIn('Estado', ['Pendiente de Revisión', 'En Revisión'])
                ->count();

            if ($totalCalidad > 0) {
                $notificaciones['modulos'][] = [
                    'tipo'     => 'CALIDAD',
                    'titulo'   => 'Reclamos en Calidad',
                    'cantidad' => $totalCalidad,
                    'url'      => '/operacion/pedidos/clientes-mayoristas/calidad',
                    'icono'    => 'fa-clipboard-check',
                    'color'    => 'text-rose-600 bg-rose-100',
                    'items'    => $reclamosPendientes->map(function ($r) {
                        return [
                            'id'        => $r->IdReclamo,
                            'titulo'    => "Ticket #{$r->IdReclamo} - Pedido #{$r->pedido->NumeroPedido}",
                            'subtitulo' => "Cliente: " . ($r->pedido->cliente->Nombre ?? 'Sin nombre'),
                            'tiempo'    => $r->FechaCreacion->diffForHumans(),
                            'url'       => '/operacion/pedidos/clientes-mayoristas/calidad?ticket=' . $r->IdReclamo,
                        ];
                    }),
                ];
                $notificaciones['total'] += $totalCalidad;
            }
        }

        // ============================================================
        // 2. TESORERIA
        // ============================================================
        if (in_array('TESORERIA', $roles, true)) {
            $devolucionesPendientes = Reclamo::where('IdCliente', $clienteId)
                ->where('Estado', 'Aprobado por Calidad')
                ->with(['pedido.cliente'])
                ->orderBy('FechaActualizacion', 'desc')
                ->limit(5)
                ->get();

            $totalTesoreria = Reclamo::where('IdCliente', $clienteId)
                ->where('Estado', 'Aprobado por Calidad')
                ->count();

            if ($totalTesoreria > 0) {
                $notificaciones['modulos'][] = [
                    'tipo'     => 'TESORERIA',
                    'titulo'   => 'Devoluciones por Reembolsar',
                    'cantidad' => $totalTesoreria,
                    'url'      => '/operacion/pedidos/clientes-mayoristas/tesoreria',
                    'icono'    => 'fa-wallet',
                    'color'    => 'text-emerald-600 bg-emerald-100',
                    'items'    => $devolucionesPendientes->map(function ($r) {
                        return [
                            'id'        => $r->IdReclamo,
                            'titulo'    => "Ticket #{$r->IdReclamo} - Pedido #{$r->pedido->NumeroPedido}",
                            'subtitulo' => "Cliente: " . ($r->pedido->cliente->Nombre ?? 'Sin nombre'),
                            'tiempo'    => $r->FechaActualizacion?->diffForHumans() ?? 'Reciente',
                            'url'       => '/operacion/pedidos/clientes-mayoristas/tesoreria?ticket=' . $r->IdReclamo,
                        ];
                    }),
                ];
                $notificaciones['total'] += $totalTesoreria;
            }
        }

        // ============================================================
        // 3. CAMARA → Pedidos Pendientes (por armar)
        // ============================================================
        if (in_array('CAMARA', $roles, true)) {
            $pedidosPendientes = PedidoCliente::where('IdCliente', $clienteId)
                ->whereRaw('TRIM(EstadoPedido) = ?', ['Pendiente'])
                ->with(['cliente', 'sucursal', 'operador'])
                ->orderBy('FechaPedido', 'desc')
                ->limit(5)
                ->get();

            $totalCamara = PedidoCliente::where('IdCliente', $clienteId)
                ->whereRaw('TRIM(EstadoPedido) = ?', ['Pendiente'])
                ->count();

            if ($totalCamara > 0) {
                $notificaciones['modulos'][] = [
                    'tipo'     => 'CAMARA',
                    'titulo'   => 'Pedidos por Armar (Cámara)',
                    'cantidad' => $totalCamara,
                    'url'      => '/operacion/pedidos/clientes-mayoristas/distribucion/reparto',
                    'icono'    => 'fa-boxes-stacked',
                    'color'    => 'text-orange-600 bg-orange-100',
                    'items'    => $pedidosPendientes->map(function ($p) {
                        return [
                            'id'        => $p->IdPedidoCliente,
                            'titulo'    => "Pedido #{$p->numero_pedido_formateado}",
                            'subtitulo' => $this->armarSubtituloPedido($p),
                            'tiempo'    => $this->armarTiempoPedido($p),
                            'url'       => '/operacion/pedidos/clientes-mayoristas/distribucion/reparto',
                        ];
                    }),
                ];
                $notificaciones['total'] += $totalCamara;
            }
        }

        // ============================================================
        // 4. DISTRIBUCION → Pedidos En Distribución (por despachar)
        // ============================================================
        if (in_array('DISTRIBUCION', $roles, true)) {
            $pedidosDespacho = PedidoCliente::where('IdCliente', $clienteId)
                ->whereRaw('TRIM(EstadoPedido) = ?', ['En Distribución'])
                ->with(['cliente', 'sucursal', 'operador'])
                ->orderBy('FechaPedido', 'desc')
                ->limit(5)
                ->get();

            $totalDespacho = PedidoCliente::where('IdCliente', $clienteId)
                ->whereRaw('TRIM(EstadoPedido) = ?', ['En Distribución'])
                ->count();

            if ($totalDespacho > 0) {
                $notificaciones['modulos'][] = [
                    'tipo'     => 'DISTRIBUCION',
                    'titulo'   => 'Pedidos por Despachar',
                    'cantidad' => $totalDespacho,
                    'url'      => '/operacion/pedidos/clientes-mayoristas/distribucion/despacho',
                    'icono'    => 'fa-truck-fast',
                    'color'    => 'text-sky-600 bg-sky-100',
                    'items'    => $pedidosDespacho->map(function ($p) {
                        return [
                            'id'        => $p->IdPedidoCliente,
                            'titulo'    => "Pedido #{$p->numero_pedido_formateado}",
                            'subtitulo' => $this->armarSubtituloPedido($p),
                            'tiempo'    => $this->armarTiempoPedido($p),
                            'url'       => '/operacion/pedidos/clientes-mayoristas/distribucion/despacho',
                        ];
                    }),
                ];
                $notificaciones['total'] += $totalDespacho;
            }
        }

        return response()->json($notificaciones);
    }

    /**
     * Arma el subtítulo del pedido:
     * - Operador: {NombreOperador} · {NombreSucursal}
     * El nombre del operador se obtiene de todos_identificador (mismo patrón que DistribucionController).
     */
    private function armarSubtituloPedido($p): string
    {
        $nombreOperador = $this->obtenerNombreOperador($p->IdOperador);
        $nombreSucursal = $p->sucursal->Nombre ?? null;

        return $nombreSucursal
            ? "Operador: {$nombreOperador} · {$nombreSucursal}"
            : "Operador: {$nombreOperador}";
    }

    /**
     * Obtiene el nombre real del operador desde todos_identificador.
     * Réplica del patrón usado en DistribucionController::decorarPedido().
     */
    private function obtenerNombreOperador(?int $idOperador): string
    {
        if (!$idOperador) {
            return 'Sin operador';
        }

        $operador = \Illuminate\Support\Facades\DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->where('todos_operador.IdOperador', $idOperador)
            ->first(['todos_identificador.Nombre']);

        return $operador->Nombre ?? 'Sin operador';
    }

    /**
     * Arma la línea de tiempo:
     * - Entrega: {fecha_entrega_formateada}   (si tiene FechaEntrega)
     * - Pedido: {fecha_pedido_formateada}     (fallback)
     */
    private function armarTiempoPedido($p): string
    {
        if ($p->FechaEntrega) {
            return 'Entrega: ' . $p->fecha_entrega_formateada;
        }

        if ($p->FechaPedido) {
            return 'Pedido: ' . \Carbon\Carbon::parse($p->FechaPedido)->format('d/m/Y H:i');
        }

        return 'Sin fecha';
    }
}