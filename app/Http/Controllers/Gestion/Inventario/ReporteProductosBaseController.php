<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReporteProductosBaseController extends Controller
{
    /**
     * Reporte de productos base con desglose de ventas.
     *
     * Clasificación de productos de venta:
     *  - SUELTO: el producto de venta NO está en categorías PACK/COMBOS
     *  - COMBO/PACK: el producto de venta SÍ está en categorías PACK/COMBOS
     *                (y tiene composición)
     *
     * - total_vendido: salidas REALES de inventario_propiamente (tipo = Ventas)
     * - venta_compuesta: suma de unidades del producto base que salieron por combos/packs
     * - venta_suelto: diferencia (total real - combos)
     * - detalles_venta: desglose por cada producto de venta (combo o suelto)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        $sucursalId        = $request->sucursal_id;
        $fechaInicial      = $request->fecha_inicial ?? date('Y-m-01');
        $fechaFinal        = $request->fecha_final   ?? date('Y-m-d');
        $search            = $request->search;
        $soloConMovimiento = $request->boolean('solo_con_movimiento', false);

        $productosBases = collect();

        if ($sucursalId && $sucursalId > 0) {
            $productosBases = $this->calcularProductosBase(
                $clienteId,
                $sucursalId,
                $fechaInicial,
                $fechaFinal,
                $search,
                $soloConMovimiento
            );
        }

        return Inertia::render('Gestion/Inventario/ReporteProductosBase/Index', [
            'productos'            => $productosBases,
            'sucursales'           => $sucursales,
            'fechaInicial'         => $fechaInicial,
            'fechaFinal'           => $fechaFinal,
            'sucursalSeleccionada' => $sucursalId,
            'search'               => $search,
            'soloConMovimiento'    => $soloConMovimiento,
        ]);
    }

    /**
     * 🔧 Cálculo principal
     */
    private function calcularProductosBase(
        $clienteId,
        $sucursalId,
        $fechaInicial,
        $fechaFinal,
        $search,
        $soloConMovimiento
    ) {
        // =============================================
        // 1. ID TIPO OPERACIÓN "VENTAS"
        // =============================================
        $idTipoOperacionVentas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('Detalle', 'Ventas')
            ->where('ActivoInactivo', 0)
            ->value('IdTipoOperacion');

        // =============================================
        // 2. SALIDAS REALES POR PRODUCTO BASE (inventario_propiamente)
        // =============================================
        $salidasReales = collect();
        if ($idTipoOperacionVentas) {
            $salidasReales = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente as ip')
                ->join('todos_fecha as tf', 'ip.IdFecha', '=', 'tf.IdFecha')
                ->where('ip.IdCliente', $clienteId)
                ->where('ip.IdSucursal', $sucursalId)
                ->where('ip.IdTipoDeOperacion', $idTipoOperacionVentas)
                ->whereRaw("UPPER(TRIM(ip.D_H)) = 'H'")
                ->whereBetween('tf.Fecha', [$fechaInicial, $fechaFinal])
                ->groupBy('ip.IdProducto')
                ->select('ip.IdProducto', DB::raw('SUM(ip.Unidades) as total_salidas'))
                ->get()
                ->keyBy('IdProducto');
        }

        // =============================================
        // 3. CATEGORÍAS PACK Y COMBOS + SUBCATEGORÍAS
        // =============================================
        $categoriasRaiz = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_menu_categoria')
            ->where('id_cliente', $clienteId)
            ->whereIn(DB::raw('UPPER(nombre)'), ['PACK', 'COMBOS'])
            ->pluck('id_categoria')
            ->toArray();

        $todasLasCategorias = [];
        $porProcesar        = $categoriasRaiz;
        $visitadas          = [];

        while (!empty($porProcesar)) {
            $idActual = array_shift($porProcesar);
            if (isset($visitadas[$idActual])) continue;
            $visitadas[$idActual]          = true;
            $todasLasCategorias[$idActual] = true;

            $hijas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_menu_categoria')
                ->where('id_cliente', $clienteId)
                ->where('id_padre', $idActual)
                ->pluck('id_categoria')
                ->toArray();

            foreach ($hijas as $h) {
                if (!isset($visitadas[$h])) $porProcesar[] = $h;
            }
        }
        $idsCategoriasComboPack = array_keys($todasLasCategorias);

        // =============================================
        // 4. PRODUCTOS DE VENTA EN CATEGORÍAS PACK/COMBOS
        // =============================================
        $idsProductosVentaEnComboPack = [];
        if (!empty($idsCategoriasComboPack)) {
            $idsProductosVentaEnComboPack = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_producto_categoria')
                ->whereIn('id_categoria', $idsCategoriasComboPack)
                ->where('id_sucursal', $sucursalId)
                ->distinct()
                ->pluck('id_detalle_producto')
                ->toArray();
        }
        // Convertir a set para búsqueda rápida
        $setProductosVentaEnComboPack = array_flip($idsProductosVentaEnComboPack);

        // =============================================
        // 5. COMPOSICIÓN DE COMBOS/PACKS
        // =============================================
        $composicionPorProductoVenta = [];
        $composiciones = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario_detalle')
            ->select('IdDetalleProducto', 'IdProducto', 'Porcion')
            ->get();

        foreach ($composiciones as $c) {
            $composicionPorProductoVenta[$c->IdDetalleProducto][] = [
                'id_producto_base' => (int) $c->IdProducto,
                'porcion'          => (float) $c->Porcion,
            ];
        }

        // =============================================
        // 6. INFO DE PRODUCTOS DE VENTA
        // =============================================
        $productosVentaInfo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario')
            ->where('IdCliente', $clienteId)
            ->get(['IdDetalleProducto', 'Codigo', 'Detalle'])
            ->keyBy('IdDetalleProducto');

        // =============================================
        // 7. PRODUCTOS BASE (todos los activos)
        // =============================================
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle as p')
            ->where('p.IdCliente', $clienteId)
            ->where('p.ActivoInactivo', 0)
            ->select('p.IdProducto', 'p.Codigo', 'p.Descripcion');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('p.Codigo', 'like', "%{$search}%")
                  ->orWhere('p.Descripcion', 'like', "%{$search}%");
            });
        }

        $productos = $query->orderBy('p.Descripcion')->get();

        // =============================================
        // 8. FACTURAS DEL PERÍODO (desde inventario_propiamente, tipo = Ventas)
        // =============================================
        $idsFacturas = [];
        if ($idTipoOperacionVentas) {
            $idsFacturas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente as ip')
                ->join('todos_fecha as tf', 'ip.IdFecha', '=', 'tf.IdFecha')
                ->where('ip.IdCliente', $clienteId)
                ->where('ip.IdSucursal', $sucursalId)
                ->where('ip.IdTipoDeOperacion', $idTipoOperacionVentas)
                ->whereRaw("UPPER(TRIM(ip.D_H)) = 'H'")
                ->whereBetween('tf.Fecha', [$fechaInicial, $fechaFinal])
                ->distinct()
                ->pluck('ip.IdDocumento')
                ->toArray();
        }

        // =============================================
        // 9. VENTAS LÍNEA POR LÍNEA
        //    Clasificación:
        //      - SUELTO: producto de venta NO está en categoría PACK/COMBOS
        //      - COMBO/PACK: producto de venta SÍ está en categoría PACK/COMBOS
        // =============================================
        $detallesVentaPorProducto = [];
        $ventasCompuestasPorBase  = [];

        if (!empty($idsFacturas)) {
            $ventas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle as d')
                ->whereIn('d.idventas', $idsFacturas)
                ->whereNotNull('d.idrelacionventainventario')
                ->where('d.idrelacionventainventario', '>', 0)
                ->select(
                    'd.idrelacionventainventario as id_producto_venta',
                    'd.unidades',
                    'd.personalizacion'
                )
                ->get();

            foreach ($ventas as $venta) {
                $idProductoVenta  = (int) $venta->id_producto_venta;
                $unidadesVendidas = (float) $venta->unidades;
                if ($unidadesVendidas <= 0) continue;

                $info = $productosVentaInfo[$idProductoVenta] ?? null;
                $nombreProductoVenta = $info->Detalle ?? "Producto #{$idProductoVenta}";
                $codigoProductoVenta = $info->Codigo ?? '';

                $personalizacion = null;
                if ($venta->personalizacion
                    && $venta->personalizacion !== 'null'
                    && $venta->personalizacion !== '[]'
                ) {
                    $personalizacion = json_decode($venta->personalizacion, true);
                }

                $composicion = $composicionPorProductoVenta[$idProductoVenta] ?? [];

                // 🔥 CLASIFICACIÓN ROBUSTA: ¿está en categoría PACK/COMBOS?
                $esComboPack = isset($setProductosVentaEnComboPack[$idProductoVenta]);

                if (!$esComboPack) {
                    // ==================== SUELTO ====================
                    // El producto de venta es el producto base
                    $idProductoBase = $idProductoVenta;

                    $detallesVentaPorProducto[$idProductoBase][] = [
                        'id_producto_venta'      => $idProductoVenta,
                        'nombre'                 => $nombreProductoVenta,
                        'codigo'                 => $codigoProductoVenta,
                        'unidades_vendidas'      => $unidadesVendidas,
                        'cantidad_base'          => $unidadesVendidas,
                        'es_suelto'              => true,
                        'tiene_personalizacion'  => false,
                        'porcion'                => 1,
                        'tipo'                   => 'Suelto',
                    ];
                } else {
                    // ==================== COMBO / PACK ====================
                    if (empty($composicion)) {
                        // Está en categoría combo pero no tiene composición
                        // → tratar como suelto para no perder el dato
                        $idProductoBase = $idProductoVenta;

                        $detallesVentaPorProducto[$idProductoBase][] = [
                            'id_producto_venta'      => $idProductoVenta,
                            'nombre'                 => $nombreProductoVenta,
                            'codigo'                 => $codigoProductoVenta,
                            'unidades_vendidas'      => $unidadesVendidas,
                            'cantidad_base'          => $unidadesVendidas,
                            'es_suelto'              => true,
                            'tiene_personalizacion'  => false,
                            'porcion'                => 1,
                            'tipo'                   => 'Suelto (sin composición)',
                        ];
                        continue;
                    }

                    // Calcular productos reales (con sustitutos)
                    $productosReales = $this->calcularProductosReales(
                        $composicion,
                        $personalizacion,
                        $unidadesVendidas
                    );

                    foreach ($productosReales as $idProductoBase => $cantidadTotal) {
                        if ($cantidadTotal <= 0) continue;

                        if (!isset($ventasCompuestasPorBase[$idProductoBase])) {
                            $ventasCompuestasPorBase[$idProductoBase] = 0;
                        }
                        $ventasCompuestasPorBase[$idProductoBase] += $cantidadTotal;

                        $detallesVentaPorProducto[$idProductoBase][] = [
                            'id_producto_venta'      => $idProductoVenta,
                            'nombre'                 => $nombreProductoVenta,
                            'codigo'                 => $codigoProductoVenta,
                            'unidades_vendidas'      => $unidadesVendidas,
                            'cantidad_base'          => $cantidadTotal,
                            'es_suelto'              => false,
                            'tiene_personalizacion'  => $personalizacion !== null,
                            'porcion'                => $unidadesVendidas > 0 ? $cantidadTotal / $unidadesVendidas : 0,
                            'tipo'                   => 'Combo/Pack',
                        ];
                    }
                }
            }
        }

        // =============================================
        // 10. CONSOLIDAR POR PRODUCTO BASE
        // =============================================
        $resultado = collect();

        foreach ($productos as $producto) {
            $idProducto = $producto->IdProducto;

            $totalReal      = (float) ($salidasReales[$idProducto]->total_salidas ?? 0);
            $ventaCompuesta = (float) ($ventasCompuestasPorBase[$idProducto] ?? 0);
            $ventaSuelto    = max(0, $totalReal - $ventaCompuesta);

            $detalles = $detallesVentaPorProducto[$idProducto] ?? [];

            // Consolidar detalles por producto de venta
            $detallesConsolidados = $this->consolidarDetalles($detalles);

            $tieneMovimiento = $totalReal > 0;

            if ($soloConMovimiento && !$tieneMovimiento) continue;

            $producto->total_vendido         = round($totalReal, 2);
            $producto->venta_suelto          = round($ventaSuelto, 2);
            $producto->venta_compuesta       = round($ventaCompuesta, 2);
            $producto->detalles_venta        = $detallesConsolidados;
            $producto->total_productos_venta = count($detallesConsolidados);
            $producto->tiene_movimiento      = $tieneMovimiento;

            $resultado->push($producto);
        }

        // Ordenar: con movimiento primero, luego por total descendente
        return $resultado->sortByDesc(function ($item) {
            return ($item->tiene_movimiento ? 1000000 : 0) + $item->total_vendido;
        })->values();
    }

    /**
     * 🔧 Calcular productos reales de un combo considerando personalización
     */
    private function calcularProductosReales($composicion, $personalizacion, $unidadesVendidas)
    {
        $productosReales = [];

        if ($personalizacion && is_array($personalizacion) && count($personalizacion) > 0) {
            // Con personalización: cada elemento = 1 combo vendido
            foreach ($personalizacion as $comboData) {
                $sustitutos = $comboData['sustitutos'] ?? [];

                if (empty($sustitutos)) {
                    // Sin sustitutos en este combo → usar composición completa
                    foreach ($composicion as $comp) {
                        $idBase   = $comp['id_producto_base'];
                        $cantidad = $comp['porcion'];

                        if ($cantidad > 0) {
                            $productosReales[$idBase] = ($productosReales[$idBase] ?? 0) + $cantidad;
                        }
                    }
                    continue;
                }

                // Agrupar sustitutos por producto original
                $sustitutosPorOriginal = [];
                foreach ($sustitutos as $sust) {
                    $idOriginal   = (int) ($sust['id_producto_original'] ?? 0);
                    $idSustituto  = (int) ($sust['id_producto_sustituto'] ?? 0);
                    $cantidad     = (float) ($sust['cantidad'] ?? 0);

                    if ($idOriginal <= 0 || $cantidad <= 0) continue;

                    $sustitutosPorOriginal[$idOriginal][] = [
                        'id_sustituto' => $idSustituto,
                        'cantidad'     => $cantidad,
                    ];
                }

                // Recorrer la composición
                foreach ($composicion as $comp) {
                    $idOriginal      = $comp['id_producto_base'];
                    $porcionOriginal = $comp['porcion'];

                    if (!isset($sustitutosPorOriginal[$idOriginal])) {
                        // No se sustituyó nada de este producto → va completo
                        if ($porcionOriginal > 0) {
                            $productosReales[$idOriginal] =
                                ($productosReales[$idOriginal] ?? 0) + $porcionOriginal;
                        }
                        continue;
                    }

                    // Hay sustituciones de este original
                    $sustitutosDeEste = $sustitutosPorOriginal[$idOriginal];
                    $totalSustituido  = 0;

                    foreach ($sustitutosDeEste as $s) {
                        $idSust   = $s['id_sustituto'];
                        $cantidad = $s['cantidad'];

                        // Cuenta como producto real (sea original o sustituto)
                        $productosReales[$idSust] =
                            ($productosReales[$idSust] ?? 0) + $cantidad;
                        $totalSustituido += $cantidad;
                    }

                    // Si no se sustituyó toda la porción, queda remanente del original
                    $remanente = $porcionOriginal - $totalSustituido;
                    if ($remanente > 0) {
                        $productosReales[$idOriginal] =
                            ($productosReales[$idOriginal] ?? 0) + $remanente;
                    }
                }
            }
        } else {
            // Sin personalización: composición × unidades vendidas
            foreach ($composicion as $comp) {
                $idBase   = $comp['id_producto_base'];
                $cantidad = $comp['porcion'] * $unidadesVendidas;

                if ($cantidad > 0) {
                    $productosReales[$idBase] = ($productosReales[$idBase] ?? 0) + $cantidad;
                }
            }
        }

        return $productosReales;
    }

    /**
     * 🔧 Consolidar detalles: agrupar por id_producto_venta + es_suelto
     */
    private function consolidarDetalles($detalles)
    {
        $agrupados = [];

        foreach ($detalles as $d) {
            $key = $d['id_producto_venta'] . '|' . ($d['es_suelto'] ? 'suelto' : 'combo');

            if (!isset($agrupados[$key])) {
                $agrupados[$key] = [
                    'id_producto_venta'     => $d['id_producto_venta'],
                    'nombre'                => $d['nombre'],
                    'codigo'                => $d['codigo'] ?? '',
                    'unidades_vendidas'     => 0,
                    'cantidad_base'         => 0,
                    'es_suelto'             => $d['es_suelto'],
                    'tiene_personalizacion' => false,
                    'porcion'               => 0,
                    'tipo'                  => $d['tipo'],
                ];
            }

            $agrupados[$key]['unidades_vendidas'] += $d['unidades_vendidas'];
            $agrupados[$key]['cantidad_base']     += $d['cantidad_base'];

            if ($d['tiene_personalizacion']) {
                $agrupados[$key]['tiene_personalizacion'] = true;
            }
        }

        // Recalcular porción promedio
        foreach ($agrupados as &$ag) {
            if ($ag['unidades_vendidas'] > 0) {
                $ag['porcion'] = round($ag['cantidad_base'] / $ag['unidades_vendidas'], 2);
            }
            $ag['cantidad_base']     = round($ag['cantidad_base'], 2);
            $ag['unidades_vendidas'] = round($ag['unidades_vendidas'], 2);
        }
        unset($ag);

        // Ordenar: combos primero, sueltos al final
        usort($agrupados, function ($a, $b) {
            if ($a['es_suelto'] !== $b['es_suelto']) {
                return $a['es_suelto'] ? 1 : -1;
            }
            return $b['cantidad_base'] <=> $a['cantidad_base'];
        });

        return array_values($agrupados);
    }
}