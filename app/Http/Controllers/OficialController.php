<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Gestion\Impuestos\VentaLiquidacionConcepto;
use App\Models\Gestion\Todos\Fecha;
use Carbon\Carbon;

class OficialController extends Controller
{
    public function index()
    {
        $hasFact = session()->has('empresa_id') && session()->has('sucursal_id');

        $clienteId  = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // =============================================
        // 🔥 1. OBTENER TODAS LAS FECHAS PENDIENTES
        // =============================================
        $todasFechasPendientes = collect();

        if ($clienteId && $sucursalId && $operadorId) {
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
        }

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
        //     (misma lógica que getDatos de LiquidacionVendedorController)
        // =============================================
        if ($fechaActual && $clienteId && $sucursalId && $operadorId) {
            $fechaStr = $fechaActual->fecha_raw;

            // 3.1 Total de ventas del día (SOLO ACTIVAS)
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

            // 3.2 Obtener TODOS los conceptos activos del cliente
            $conceptosBase = VentaLiquidacionConcepto::porContexto()
                ->activos()
                ->get();

            // 3.3 Calcular montos del sistema para cada concepto
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

            // 3.4 Calcular diferencia
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
            'diaActual'        => $diaActual,
            'fechasPendientes' => $otrasFechas,
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