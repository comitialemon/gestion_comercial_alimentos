<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Gestion\Impuestos\VentaLiquidacionConcepto;

class OficialController extends Controller
{
    public function index()
    {
        $hasFact = session()->has('empresa_id') && session()->has('sucursal_id');

        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // =============================================
        // 🔥 VERIFICAR TIPO DE OPERADOR
        // =============================================
        $tipoOperadorId = null;
        $esVentaMostrador = false;

        if ($operadorId) {
            $tipoOperadorId = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $operadorId)
                ->value('IdOperadorTipo');

            // 🔥 SOLO VentaMostrador (ID 6)
            $esVentaMostrador = ($tipoOperadorId == 6);
        }

        // =============================================
        // 🔥 NOTIFICACIONES PARA EL INICIO
        // =============================================
        $notificacionesInicio = [];
        if ($operadorId && $clienteId) {
            $roles = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operador_notificaciones as on')
                ->join('notificaciones_tipos as nt', 'on.IdNotificacionTipo', '=', 'nt.IdNotificacionTipo')
                ->where('on.IdOperador', $operadorId)
                ->where('on.IdCliente', $clienteId)
                ->where('on.ActivoInactivo', 1)
                ->pluck('nt.Nombre')
                ->toArray();

            if (in_array('CALIDAD', $roles)) {
                $cantidad = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('pedidos_clientes_reclamos')
                    ->where('IdCliente', $clienteId)
                    ->whereIn('Estado', ['Pendiente de Revisión', 'En Revisión'])
                    ->count();
                if ($cantidad > 0) {
                    $notificacionesInicio[] = [
                        'tipo' => 'CALIDAD',
                        'titulo' => 'Reclamos en Calidad',
                        'cantidad' => $cantidad,
                        'url' => '/operacion/pedidos/clientes-mayoristas/calidad',
                        'icono' => 'fa-clipboard-check',
                        'color_bg' => 'bg-rose-50',
                        'color_border' => 'border-rose-200',
                        'color_text' => 'text-rose-600',
                        'color_icon' => 'text-rose-500 bg-rose-100'
                    ];
                }
            }

            if (in_array('TESORERIA', $roles)) {
                $cantidad = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('pedidos_clientes_reclamos')
                    ->where('IdCliente', $clienteId)
                    ->where('Estado', 'Aprobado por Calidad')
                    ->count();
                if ($cantidad > 0) {
                    $notificacionesInicio[] = [
                        'tipo' => 'TESORERIA',
                        'titulo' => 'Devoluciones por Reembolsar',
                        'cantidad' => $cantidad,
                        'url' => '/operacion/pedidos/clientes-mayoristas/tesoreria',
                        'icono' => 'fa-wallet',
                        'color_bg' => 'bg-emerald-50',
                        'color_border' => 'border-emerald-200',
                        'color_text' => 'text-emerald-600',
                        'color_icon' => 'text-emerald-500 bg-emerald-100'
                    ];
                }
            }

            if (in_array('DISTRIBUCION', $roles)) {
                $cantidad = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('pedidos_clientes')
                    ->where('IdCliente', $clienteId)
                    ->where('EstadoPedido', 'Pendiente')
                    ->count();
                if ($cantidad > 0) {
                    $notificacionesInicio[] = [
                        'tipo' => 'DISTRIBUCION',
                        'titulo' => 'Pedidos por Armar',
                        'cantidad' => $cantidad,
                        'url' => '/operacion/pedidos/clientes-mayoristas/distribucion',
                        'icono' => 'fa-truck',
                        'color_bg' => 'bg-orange-50',
                        'color_border' => 'border-orange-200',
                        'color_text' => 'text-orange-600',
                        'color_icon' => 'text-orange-500 bg-orange-100'
                    ];
                }
            }
        }

        // Si NO es VentaMostrador, devolvemos la vista sin datos de ventas, pero CON las notificaciones
        if (!$esVentaMostrador) {
            return Inertia::render('Oficial/Index', [
                'gestion' => [
                    'cliente_id'          => session('cliente_id'),
                    'cliente_sucursal_id' => session('cliente_sucursal_id'),
                    'empresa_nombre'      => session('global_empresa_nombre'),
                    'sucursal_nombre'     => session('global_sucursal_nombre'),
                    'sucursal_numero'     => session('global_sucursal_numero'),
                ],
                'facturacion' => [
                    'empresa_id'  => session('empresa_id'),
                    'sucursal_id' => session('sucursal_id'),
                    'completo'    => $hasFact,
                ],
                'esVentaMostrador' => false,
                'diaActual'        => null,
                'fechasPendientes' => [],
                'notificacionesInicio' => $notificacionesInicio, 
                'flash' => [
                    'ok'   => session('ok'),
                    'warn' => session('warn'),
                ],
            ]);
        }

        // =============================================
        // 🔥 1. OBTENER TODAS LAS FECHAS PENDIENTES
        // =============================================
        $todasFechasPendientes = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->join('todos_fecha', DB::raw('DATE(impuestos_ventas.FechaVenta)'), '=', 'todos_fecha.Fecha')
            ->where('impuestos_ventas.IdCliente', $clienteId)
            ->where('impuestos_ventas.IdClienteSucursal', $sucursalId)
            ->where('impuestos_ventas.IdOperadorIngresa', $operadorId)
            ->where('impuestos_ventas.LiquidadoVendedor', 0)
            ->where('impuestos_ventas.IdEstado', 1)
            ->where('impuestos_ventas.ActivoInactivo', 1)
            ->where('impuestos_ventas.NumeroFactura', '>', 0)
            ->select(
                'todos_fecha.IdFecha as id',
                'todos_fecha.Fecha as fecha_raw',
                DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d/%m/%Y') as fecha"),
                DB::raw('SUM(impuestos_ventas.ImporteVenta) as total_ventas'),
                DB::raw('COUNT(impuestos_ventas.IdVentas) as cantidad_ventas')
            )
            ->groupBy('todos_fecha.IdFecha', 'todos_fecha.Fecha')
            ->orderBy('todos_fecha.Fecha', 'desc')
            ->get();

        // =============================================
        // 🔥 2. LA FECHA MÁS RECIENTE ES LA "ACTUAL"
        // =============================================
        $fechaActual = $todasFechasPendientes->first();
        $otrasFechas = $todasFechasPendientes->slice(1)->values();

        $diaActual = [
            'fecha'       => $fechaActual->fecha ?? null,
            'fechaRaw'    => $fechaActual->fecha_raw ?? null,
            'fechaId'     => $fechaActual->id ?? null,
            'totalVentas' => 0,
            'cantidad'    => $fechaActual->cantidad_ventas ?? 0,
            'conceptos'   => [],
            'diferencia'  => 0,
            'tieneDatos'  => false,
        ];

        // =============================================
        // 🔥 3. TRAER CONCEPTOS Y CALCULAR DIFERENCIA
        // =============================================
        if ($fechaActual) {
            $fechaStr = $fechaActual->fecha_raw;

            $totalVentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $sucursalId)
                ->where('IdOperadorIngresa', $operadorId)
                ->where('LiquidadoVendedor', 0)
                ->where('IdEstado', 1)
                ->where('ActivoInactivo', 1)
                ->where('NumeroFactura', '>', 0)
                ->whereDate('FechaVenta', $fechaStr)
                ->sum('ImporteVenta');

            $totalVentas = round($totalVentas, 2);

            $conceptosBase = VentaLiquidacionConcepto::porContexto()
                ->activos()
                ->get();

            $conceptos = [];
            $sumaMontos = 0;

            foreach ($conceptosBase as $concepto) {
                $monto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas as v')
                    ->join('impuestos_ventas_liquidacion as l', 'v.IdVentas', '=', 'l.IdVentas')
                    ->join('impuestos_ventas_liquidacion_concepto as c', 'l.IdCuenta', '=', 'c.IdCuenta')
                    ->where('v.IdCliente', $clienteId)
                    ->where('v.IdClienteSucursal', $sucursalId)
                    ->where('v.IdOperadorIngresa', $operadorId)
                    ->where('v.LiquidadoVendedor', 0)
                    ->where('v.IdEstado', 1)
                    ->where('v.ActivoInactivo', 1)
                    ->where('v.NumeroFactura', '>', 0)
                    ->whereDate('v.FechaVenta', $fechaStr)
                    ->where('c.Concepto', $concepto->Concepto)
                    ->where('c.IdCliente', $clienteId)
                    ->sum('l.Bolivianos');

                $montoRedondeado = round($monto, 2);
                $sumaMontos += $montoRedondeado;

                $conceptos[] = [
                    'id'     => $concepto->IdConceptoLiquidacion,
                    'nombre' => $concepto->Concepto,
                    'monto'  => $montoRedondeado,
                ];
            }

            $diferencia = round($totalVentas - $sumaMontos, 2);

            $diaActual['totalVentas'] = $totalVentas;
            $diaActual['conceptos']   = $conceptos;
            $diaActual['diferencia']  = $diferencia;
            $diaActual['tieneDatos']  = $totalVentas > 0;
        }

        return Inertia::render('Oficial/Index', [
            'gestion' => [
                'cliente_id'          => session('cliente_id'),
                'cliente_sucursal_id' => session('cliente_sucursal_id'),
                'empresa_nombre'      => session('global_empresa_nombre'),
                'sucursal_nombre'     => session('global_sucursal_nombre'),
                'sucursal_numero'     => session('global_sucursal_numero'),
            ],
            'facturacion' => [
                'empresa_id'  => session('empresa_id'),
                'sucursal_id' => session('sucursal_id'),
                'completo'    => $hasFact,
            ],
            'esVentaMostrador' => true,
            'diaActual'        => $diaActual,
            'fechasPendientes' => $otrasFechas,
            'notificacionesInicio' => $notificacionesInicio, 
            'flash' => [
                'ok'   => session('ok'),
                'warn' => session('warn'),
            ],
        ]);
    }

    public function debug()
    {
        return response()->json([
            'cliente_nit'             => session('cliente_nit'),
            'cliente_nombre'          => session('cliente_nombre'),
            'empresa_id_facturacion'  => session('empresa_id_facturacion'),
            'sucursal_id_facturacion' => session('sucursal_id_facturacion'),
            'punto_venta_id'          => session('punto_venta_id'),
            'tiene_facturacion'       => session('tiene_facturacion'),
        ]);
    }
}   