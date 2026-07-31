<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReporteInventarioController extends Controller
{
    /**
     * Mostrar reporte de inventario por rango de fechas (CON selector de sucursal)
     */
    public function index(Request $request)
    {
        // Obtener sucursales del usuario
        $sucursales = ClienteSucursal::where('IdCliente', session('cliente_id'))
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        // VALOR POR DEFECTO: usar la sucursal de la sesión (contexto)
        $sucursalDefault = session('cliente_sucursal_id');
        
        // Verificar que la sucursal por defecto existe en las sucursales del usuario
        $sucursalExiste = $sucursales->contains('id', $sucursalDefault);
        
        // Si la sucursal de sesión es válida, usarla; si no, null
        $sucursalId = $request->sucursal_id ?? ($sucursalExiste ? $sucursalDefault : null);
        
        $fechaInicial = $request->fecha_inicial ?? date('Y-m-01');
        $fechaFinal = $request->fecha_final ?? date('Y-m-d');
        $soloConMovimiento = $request->boolean('solo_con_movimiento', false);
        $search = $request->search;

        $productos = collect();

        // Solo consultar si hay una sucursal válida
        if ($sucursalId && $sucursalId > 0) {
            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle as p')
                ->select(
                    'p.IdProducto',
                    'p.Codigo',
                    'p.Descripcion'
                )
                ->addSelect(DB::raw("(
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN UPPER(ip.D_H) = 'D' THEN ip.Unidades 
                            WHEN UPPER(ip.D_H) = 'H' THEN -ip.Unidades 
                            ELSE 0 
                        END
                    ), 0)
                    FROM inventario_propiamente ip
                    INNER JOIN todos_fecha tf ON ip.IdFecha = tf.IdFecha
                    WHERE ip.IdProducto = p.IdProducto
                        AND ip.IdCliente = p.IdCliente
                        AND ip.IdSucursal = {$sucursalId}
                        AND tf.Fecha < '{$fechaInicial}'
                ) as saldo_anterior"))
                ->addSelect(DB::raw("(
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN UPPER(ip.D_H) = 'D' THEN ip.Unidades 
                            ELSE 0 
                        END
                    ), 0)
                    FROM inventario_propiamente ip
                    INNER JOIN todos_fecha tf ON ip.IdFecha = tf.IdFecha
                    WHERE ip.IdProducto = p.IdProducto
                        AND ip.IdCliente = p.IdCliente
                        AND ip.IdSucursal = {$sucursalId}
                        AND tf.Fecha BETWEEN '{$fechaInicial}' AND '{$fechaFinal}'
                ) as ingresos"))
                ->addSelect(DB::raw("(
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN UPPER(ip.D_H) = 'H' THEN ip.Unidades 
                            ELSE 0 
                        END
                    ), 0)
                    FROM inventario_propiamente ip
                    INNER JOIN todos_fecha tf ON ip.IdFecha = tf.IdFecha
                    WHERE ip.IdProducto = p.IdProducto
                        AND ip.IdCliente = p.IdCliente
                        AND ip.IdSucursal = {$sucursalId}
                        AND tf.Fecha BETWEEN '{$fechaInicial}' AND '{$fechaFinal}'
                ) as salidas"))
                ->where('p.IdCliente', session('cliente_id'))
                ->where('p.ActivoInactivo', 0);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('p.Codigo', 'like', "%{$search}%")
                      ->orWhere('p.Descripcion', 'like', "%{$search}%");
                });
            }

            $todosProductos = $query->orderBy('p.Descripcion')->get();

            foreach ($todosProductos as $producto) {
                $producto->saldo_anterior = (float) $producto->saldo_anterior;
                $producto->ingresos = (float) $producto->ingresos;
                $producto->salidas = (float) $producto->salidas;
                $producto->saldo_actual = $producto->saldo_anterior + $producto->ingresos - $producto->salidas;
            }

            if ($soloConMovimiento) {
                $todosProductos = $todosProductos->filter(function($p) {
                    return $p->saldo_anterior != 0 || $p->ingresos != 0 || $p->salidas != 0;
                });
            }

            $perPage = 50;
            $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
            $currentItems = $todosProductos->slice(($currentPage - 1) * $perPage, $perPage)->values();
            
            $productos = new \Illuminate\Pagination\LengthAwarePaginator(
                $currentItems,
                $todosProductos->count(),
                $perPage,
                $currentPage,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
            );
            
            $productos->appends([
                'sucursal_id' => $sucursalId,
                'fecha_inicial' => $fechaInicial,
                'fecha_final' => $fechaFinal,
                'solo_con_movimiento' => $soloConMovimiento ? 1 : 0,
                'search' => $search
            ]);
        }

        return Inertia::render('Gestion/Inventario/ReporteInventario/Index', [
            'productos' => $productos,
            'sucursales' => $sucursales,
            'fechaInicial' => $fechaInicial,
            'fechaFinal' => $fechaFinal,
            'sucursalSeleccionada' => $sucursalId,
            'soloConMovimiento' => $soloConMovimiento,
            'search' => $search,
        ]);
    }

    /**
     * Mostrar reporte de inventario de la sucursal actual (contexto)
     * No permite cambiar de sucursal
     */
    public function porSucursal(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // Obtener nombre de la sucursal actual
        $sucursalActual = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->first();
        
        $nombreSucursal = $sucursalActual ? $sucursalActual->Nombre : 'Sucursal no encontrada';
        
        $fechaInicial = $request->fecha_inicial ?? date('Y-m-01');
        $fechaFinal = $request->fecha_final ?? date('Y-m-d');
        $soloConMovimiento = $request->boolean('solo_con_movimiento', false);
        $search = $request->search;

        $productos = collect();

        if ($sucursalId && $sucursalId > 0) {
            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle as p')
                ->select(
                    'p.IdProducto',
                    'p.Codigo',
                    'p.Descripcion'
                )
                ->addSelect(DB::raw("(
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN UPPER(ip.D_H) = 'D' THEN ip.Unidades 
                            WHEN UPPER(ip.D_H) = 'H' THEN -ip.Unidades 
                            ELSE 0 
                        END
                    ), 0)
                    FROM inventario_propiamente ip
                    INNER JOIN todos_fecha tf ON ip.IdFecha = tf.IdFecha
                    WHERE ip.IdProducto = p.IdProducto
                        AND ip.IdCliente = p.IdCliente
                        AND ip.IdSucursal = {$sucursalId}
                        AND tf.Fecha < '{$fechaInicial}'
                ) as saldo_anterior"))
                ->addSelect(DB::raw("(
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN UPPER(ip.D_H) = 'D' THEN ip.Unidades 
                            ELSE 0 
                        END
                    ), 0)
                    FROM inventario_propiamente ip
                    INNER JOIN todos_fecha tf ON ip.IdFecha = tf.IdFecha
                    WHERE ip.IdProducto = p.IdProducto
                        AND ip.IdCliente = p.IdCliente
                        AND ip.IdSucursal = {$sucursalId}
                        AND tf.Fecha BETWEEN '{$fechaInicial}' AND '{$fechaFinal}'
                ) as ingresos"))
                ->addSelect(DB::raw("(
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN UPPER(ip.D_H) = 'H' THEN ip.Unidades 
                            ELSE 0 
                        END
                    ), 0)
                    FROM inventario_propiamente ip
                    INNER JOIN todos_fecha tf ON ip.IdFecha = tf.IdFecha
                    WHERE ip.IdProducto = p.IdProducto
                        AND ip.IdCliente = p.IdCliente
                        AND ip.IdSucursal = {$sucursalId}
                        AND tf.Fecha BETWEEN '{$fechaInicial}' AND '{$fechaFinal}'
                ) as salidas"))
                ->where('p.IdCliente', $clienteId)
                ->where('p.ActivoInactivo', 0);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('p.Codigo', 'like', "%{$search}%")
                      ->orWhere('p.Descripcion', 'like', "%{$search}%");
                });
            }

            $todosProductos = $query->orderBy('p.Descripcion')->get();

            foreach ($todosProductos as $producto) {
                $producto->saldo_anterior = (float) $producto->saldo_anterior;
                $producto->ingresos = (float) $producto->ingresos;
                $producto->salidas = (float) $producto->salidas;
                $producto->saldo_actual = $producto->saldo_anterior + $producto->ingresos - $producto->salidas;
            }

            if ($soloConMovimiento) {
                $todosProductos = $todosProductos->filter(function($p) {
                    return $p->saldo_anterior != 0 || $p->ingresos != 0 || $p->salidas != 0;
                });
            }

            $perPage = 50;
            $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
            $currentItems = $todosProductos->slice(($currentPage - 1) * $perPage, $perPage)->values();
            
            $productos = new \Illuminate\Pagination\LengthAwarePaginator(
                $currentItems,
                $todosProductos->count(),
                $perPage,
                $currentPage,
                ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
            );
            
            $productos->appends([
                'fecha_inicial' => $fechaInicial,
                'fecha_final' => $fechaFinal,
                'solo_con_movimiento' => $soloConMovimiento ? 1 : 0,
                'search' => $search
            ]);
        }

        return Inertia::render('Gestion/Inventario/ReporteInventario/PorSucursal', [
            'productos' => $productos,
            'nombreSucursal' => $nombreSucursal,
            'fechaInicial' => $fechaInicial,
            'fechaFinal' => $fechaFinal,
            'soloConMovimiento' => $soloConMovimiento,
            'search' => $search,
            'sucursalId' => $sucursalId,
        ]);
    }

    /**
     * API: Obtener movimientos (glosas) de un producto
     * 🔥 CORREGIDO: 3 decimales para unidades y saldo
     */
    public function getMovimientos(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|integer',
            'sucursal_id' => 'required|integer',
            'fecha_inicial' => 'required|date',
            'fecha_final' => 'required|date',
        ]);

        \Log::info('=== getMovimientos llamado ===');
        \Log::info('Parametros:', $request->all());

        // Obtener movimientos
        $movimientos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_propiamente as ip')
            ->join('todos_fecha as tf', 'ip.IdFecha', '=', 'tf.IdFecha')
            ->leftJoin('inventario_tipooperacion as tio', 'ip.IdTipoDeOperacion', '=', 'tio.IdTipoOperacion')
            ->leftJoin('inventario_almacen as ia', 'ip.IdAlmacen', '=', 'ia.IdAlmacen')
            ->where('ip.IdProducto', $request->producto_id)
            ->where('ip.IdCliente', session('cliente_id'))
            ->where('ip.IdSucursal', $request->sucursal_id)
            ->whereBetween('tf.Fecha', [$request->fecha_inicial, $request->fecha_final])
            ->orderBy('tf.Fecha', 'asc')
            ->orderBy('ip.IdInventarioPropiamente', 'asc')
            ->select(
                'ip.IdInventarioPropiamente as id',
                DB::raw("DATE_FORMAT(tf.Fecha, '%d-%m-%Y') as fecha"),
                'ip.Glosa',
                'tio.Detalle as tipo_operacion',
                'ip.Unidades',
                DB::raw("UPPER(TRIM(ip.D_H)) as tipo"),
                'ia.Almacen as almacen',
                'tf.Fecha as fecha_raw',
                'ip.IdDocumento'
            )
            ->get();

        \Log::info('Movimientos encontrados: ' . $movimientos->count());

        // Calcular saldo anterior (antes de la fecha inicial)
        $saldoAnterior = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_propiamente as ip')
            ->join('todos_fecha as tf', 'ip.IdFecha', '=', 'tf.IdFecha')
            ->where('ip.IdProducto', $request->producto_id)
            ->where('ip.IdCliente', session('cliente_id'))
            ->where('ip.IdSucursal', $request->sucursal_id)
            ->where('tf.Fecha', '<', $request->fecha_inicial)
            ->select(DB::raw("COALESCE(SUM(
                CASE 
                    WHEN UPPER(TRIM(ip.D_H)) = 'D' THEN ip.Unidades 
                    WHEN UPPER(TRIM(ip.D_H)) = 'H' THEN -ip.Unidades 
                    ELSE 0 
                END
            ), 0) as saldo"))
            ->value('saldo');

        $saldo = (float) $saldoAnterior;
        
        foreach ($movimientos as &$mov) {
            $tipo = strtoupper(trim($mov->tipo));
            
            if ($tipo === 'D') {
                // 🔥 CORREGIDO: 3 DECIMALES
                $mov->unidades_signo = '+' . number_format($mov->Unidades, 3);
                $saldo += (float) $mov->Unidades;
                $mov->tipo_texto = 'ENTRADA';
                $mov->tipo_clase = 'text-emerald-600';
                $mov->tipo = 'D';
            } elseif ($tipo === 'H') {
                // 🔥 CORREGIDO: 3 DECIMALES
                $mov->unidades_signo = '-' . number_format($mov->Unidades, 3);
                $saldo -= (float) $mov->Unidades;
                $mov->tipo_texto = 'SALIDA';
                $mov->tipo_clase = 'text-red-600';
                $mov->tipo = 'H';
            } else {
                $mov->unidades_signo = '?' . number_format($mov->Unidades, 3);
                $mov->tipo_texto = 'OTRO';
                $mov->tipo_clase = 'text-gray-600';
                $mov->tipo = $tipo;
            }
            
            // 🔥 CORREGIDO: 3 DECIMALES
            $mov->unidades_formateado = number_format($mov->Unidades, 3);
            $mov->saldo_acumulado = number_format($saldo, 3);
            $mov->saldo_acumulado_raw = $saldo;
        }

        return response()->json([
            'success' => true,
            'movimientos' => $movimientos,
            // 🔥 CORREGIDO: 3 DECIMALES
            'saldo_anterior' => number_format($saldoAnterior, 3),
            'saldo_anterior_raw' => (float) $saldoAnterior,
            'producto_id' => $request->producto_id,
            'fecha_inicial' => $request->fecha_inicial,
            'fecha_final' => $request->fecha_final,
        ]);
    }
    /**
     * 🔥 SHOW - Mostrar detalle de un movimiento específico
     */
    public function showMovimiento($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            // 1. OBTENER EL MOVIMIENTO
            $movimiento = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente as ip')
                ->join('todos_fecha as tf', 'ip.IdFecha', '=', 'tf.IdFecha')
                ->join('inventario_tipooperacion as tio', 'ip.IdTipoDeOperacion', '=', 'tio.IdTipoOperacion')
                ->join('inventario_productodetalle as p', 'ip.IdProducto', '=', 'p.IdProducto')
                ->leftJoin('inventario_almacen as ia', 'ip.IdAlmacen', '=', 'ia.IdAlmacen')
                ->where('ip.IdInventarioPropiamente', $id)
                ->where('ip.IdCliente', $clienteId)
                ->where('ip.IdSucursal', $sucursalId)
                ->select(
                    'ip.*',
                    'tf.Fecha as fecha_movimiento',
                    'tio.Detalle as tipo_operacion',
                    'p.Descripcion as producto_nombre',
                    'p.Codigo as producto_codigo',
                    'ia.Almacen as almacen_nombre'
                )
                ->first();

            if (!$movimiento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Movimiento no encontrado'
                ], 404);
            }

            // 2. VARIABLES PARA DATOS ADICIONALES
            $venta = null;
            $detalles_venta = null;
            $pagos = null;

            // 3. DETECTAR SI ES VENTA (solo para eso necesitamos información extra)
            $tipo = strtolower(trim($movimiento->tipo_operacion));
            $esVenta = (strpos($tipo, 'venta') !== false || strpos($tipo, 'recibo') !== false);

            if ($esVenta) {
                $venta = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas as v')
                    ->leftJoin('todos_identificador as i', 'v.IdNIT', '=', 'i.IdIdentificador')
                    ->leftJoin('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
                    ->leftJoin('todos_identificador as oi', 'o.IdIdentificador', '=', 'oi.IdIdentificador')
                    ->where('v.IdVentas', $movimiento->IdDocumento)
                    ->where('v.IdCliente', $clienteId)
                    ->select(
                        'v.NumeroFactura',
                        'v.FechaVenta',
                        'v.ImporteVenta',
                        'v.IdEstado',
                        'v.Observacion',
                        'v.TicketDia',
                        'i.CI_NIT as nit_cliente',
                        'i.Nombre as nombre_cliente',
                        'oi.Nombre as nombre_operador'
                    )
                    ->first();

                if ($venta) {
                    $detalles_venta = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_detalle as d')
                        ->join('inventario_relacion_ventainventario as p', 'd.idrelacionventainventario', '=', 'p.IdDetalleProducto')
                        ->where('d.idventas', $movimiento->IdDocumento)
                        ->select('p.Detalle as nombre', 'd.unidades', 'd.preciounidades', 'd.totalbolivianos')
                        ->get();

                    $pagos = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion as l')
                        ->join('impuestos_ventas_liquidacion_concepto as c', 'l.IdCuenta', '=', 'c.IdCuenta')
                        ->where('l.IdVentas', $movimiento->IdDocumento)
                        ->select('c.Concepto', 'l.Bolivianos')
                        ->get();
                }
            }

            // 4. SIEMPRE DEVOLVER LA INFORMACIÓN DEL MOVIMIENTO
            return response()->json([
                'success' => true,
                'movimiento' => $movimiento,
                'es_venta' => $esVenta,
                // Datos de venta (si es venta)
                'venta' => $venta,
                'detalles_venta' => $detalles_venta,
                'pagos' => $pagos,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error en showMovimiento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar el movimiento: ' . $e->getMessage()
            ], 500);
        }
    }
}