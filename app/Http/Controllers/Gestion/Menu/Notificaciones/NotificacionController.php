<?php

namespace App\Http\Controllers\Gestion\Menu\Notificaciones;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Menu\Notificaciones\OperadorNotificacion;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Reclamo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $operadorId = $request->session()->get('operador_id');
        $clienteId = $request->session()->get('cliente_id');

        if (!$operadorId || !$clienteId) {
            return response()->json(['total' => 0, 'modulos' => []]);
        }

        $roles = OperadorNotificacion::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with('tipo')
            ->get()
            ->pluck('tipo.Nombre')
            ->toArray();

        $notificaciones = [
            'total' => 0,
            'modulos' => []
        ];

        // 2. Si es de CALIDAD, traemos los reclamos pendientes
        if (in_array('CALIDAD', $roles)) {
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
                    'tipo' => 'CALIDAD',
                    'titulo' => 'Reclamos en Calidad',
                    'cantidad' => $totalCalidad,
                    'url' => '/operacion/pedidos/clientes-mayoristas/calidad',
                    'icono' => 'fa-clipboard-check',
                    'color' => 'text-rose-600 bg-rose-100',
                    'items' => $reclamosPendientes->map(function($r) {
                        return [
                            'id' => $r->IdReclamo,
                            'titulo' => "Ticket #{$r->IdReclamo} - Pedido #{$r->pedido->NumeroPedido}",
                            'subtitulo' => "Cliente: " . ($r->pedido->cliente->Nombre ?? 'Sin nombre'),
                            'tiempo' => $r->FechaCreacion->diffForHumans(),
                            'url' => '/operacion/pedidos/clientes-mayoristas/calidad?ticket=' . $r->IdReclamo // 🔥 URL EXACTA
                        ];
                    })
                ];
                $notificaciones['total'] += $totalCalidad;
            }
        }

        // 3. Si es de TESORERIA, traemos los reembolsos pendientes
        if (in_array('TESORERIA', $roles)) {
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
                    'tipo' => 'TESORERIA',
                    'titulo' => 'Devoluciones por Reembolsar',
                    'cantidad' => $totalTesoreria,
                    'url' => '/operacion/pedidos/clientes-mayoristas/tesoreria',
                    'icono' => 'fa-wallet',
                    'color' => 'text-emerald-600 bg-emerald-100',
                    'items' => $devolucionesPendientes->map(function($r) {
                        return [
                            'id' => $r->IdReclamo,
                            'titulo' => "Ticket #{$r->IdReclamo} - Pedido #{$r->pedido->NumeroPedido}",
                            'subtitulo' => "Cliente: " . ($r->pedido->cliente->Nombre ?? 'Sin nombre'),
                            'tiempo' => $r->FechaActualizacion?->diffForHumans() ?? 'Reciente',
                            'url' => '/operacion/pedidos/clientes-mayoristas/tesoreria?ticket=' . $r->IdReclamo // 🔥 URL EXACTA
                        ];
                    })
                ];
                $notificaciones['total'] += $totalTesoreria;
            }
        }

        // 4. Si es de DISTRIBUCION, traemos los pedidos por despachar
        if (in_array('DISTRIBUCION', $roles)) {
            $pedidosPendientes = PedidoCliente::where('IdCliente', $clienteId)
                ->where('EstadoPedido', 'Pendiente')
                ->with(['cliente'])
                ->orderBy('FechaPedido', 'desc')
                ->limit(5)
                ->get();

            $totalDistribucion = PedidoCliente::where('IdCliente', $clienteId)
                ->where('EstadoPedido', 'Pendiente')
                ->count();

            if ($totalDistribucion > 0) {
                $notificaciones['modulos'][] = [
                    'tipo' => 'DISTRIBUCION',
                    'titulo' => 'Pedidos por Armar',
                    'cantidad' => $totalDistribucion,
                    'url' => '/operacion/pedidos/clientes-mayoristas/distribucion',
                    'icono' => 'fa-truck',
                    'color' => 'text-orange-600 bg-orange-100',
                    'items' => $pedidosPendientes->map(function($p) {
                        return [
                            'id' => $p->IdPedidoCliente,
                            'titulo' => "Pedido #{$p->NumeroPedido}",
                            'subtitulo' => "Cliente: " . ($p->cliente->Nombre ?? 'Sin nombre'),
                            'tiempo' => $p->FechaPedido->diffForHumans(),
                            'url' => '/operacion/pedidos/clientes-mayoristas/distribucion' // Distribucion no tiene ID especifico, va a la general
                        ];
                    })
                ];
                $notificaciones['total'] += $totalDistribucion;
            }
        }

        return response()->json($notificaciones);
    }
}