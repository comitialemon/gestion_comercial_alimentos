<?php

namespace App\Services\Inventario;

use Illuminate\Support\Facades\DB;

/**
 * 🧮 Calculadora unificada de salidas por producto base,
 *    atribuidas a combos/packs o a ventas sueltas.
 *
 * FILOSOFÍA:
 *  - Fuente de verdad de CANTIDADES = inventario_propiamente (lo que realmente salió).
 *  - Fuente de ATRIBUCIÓN (¿combo o suelto?) = impuestos_ventas_detalle.
 *  - Se cruzan por IdDocumento (= IdVentas).
 *
 * GARANTÍA: para cada producto base,
 *    SUM(combo) + SUM(suelto) === SUM(inventario_propiamente)
 *    → siempre cuadra con ReporteInventarioController.
 *
 * TIPOS DE PRODUCTO:
 *  - DISCRETO: se vende en unidades enteras (salteñas, empanadas, gaseosas).
 *              Se redondea a entero usando "mayor residuo" para que la suma
 *              siga cuadrando exactamente con el total real.
 *  - FRACCIONADO: es ingrediente de combos (azúcar, agua de botellón, sal).
 *              Se deja con decimales tal cual.
 *
 *  La detección es AUTOMÁTICA: si el producto aparece en alguna composición
 *  de combo con Porcion decimal (< 1 o no entera), es fraccionado. Si no,
 *  es discreto.
 *
 * CASOS DE ATRIBUCIÓN:
 *  A) Factura con 1 sola línea de venta (caso POS típico):
 *     atribución DIRECTA y EXACTA.
 *  B) Factura con varias líneas (combo + suelto mezclados):
 *     reparto PROPORCIONAL por unidades vendidas, respetando el total real.
 */
class ProductoBaseComboCalculator
{
    protected $connection = 'mysql_gestion_comercial_alimentos';

    /**
     * Ejecuta todo el cálculo.
     *
     * @param int    $clienteId
     * @param int    $sucursalId
     * @param string $fechaInicial  Y-m-d
     * @param string $fechaFinal    Y-m-d
     * @param string|null $search
     * @param bool   $soloConMovimiento
     *
     * @return array{
     *   columnas: array<int, array{id:int, nombre:string, codigo:string}>,
     *   filas: array<int, array{
     *       id_producto:int, codigo:string, descripcion:string,
     *       valores: array<int,float>,
     *       otras_salidas: float,
     *       total: float,
     *       es_fraccionado: bool
     *   }>,
     *   totalesPorColumna: array<int,float>,
     *   totalGeneral: float,
     *   totalOtrasSalidas: float
     * }
     */
    public function calcular(
        int $clienteId,
        int $sucursalId,
        string $fechaInicial,
        string $fechaFinal,
        ?string $search = null,
        bool $soloConMovimiento = false
    ): array {
        $vacio = [
            'columnas'           => [],
            'filas'              => [],
            'totalesPorColumna'  => [],
            'totalGeneral'       => 0,
            'totalOtrasSalidas'  => 0,
        ];

        if (!$sucursalId || $sucursalId <= 0) {
            return $vacio;
        }

        // =====================================================
        // 1. Tipo de operación "Ventas"
        // =====================================================
        $idTipoOperacionVentas = DB::connection($this->connection)
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('Detalle', 'Ventas')
            ->where('ActivoInactivo', 0)
            ->value('IdTipoOperacion');

        if (!$idTipoOperacionVentas) {
            return $vacio;
        }

        // =====================================================
        // 2. SALIDAS REALES detalladas desde inventario_propiamente
        //    (fuente de verdad, incluye IdDocumento = IdVentas)
        // =====================================================
        $salidasDetalladas = DB::connection($this->connection)
            ->table('inventario_propiamente as ip')
            ->join('todos_fecha as tf', 'ip.IdFecha', '=', 'tf.IdFecha')
            ->where('ip.IdCliente', $clienteId)
            ->where('ip.IdSucursal', $sucursalId)
            ->where('ip.IdTipoDeOperacion', $idTipoOperacionVentas)
            ->whereRaw("UPPER(TRIM(ip.D_H)) = 'H'")
            ->whereBetween('tf.Fecha', [$fechaInicial, $fechaFinal])
            ->select(
                'ip.IdProducto',
                'ip.Unidades',
                'ip.IdDocumento'
            )
            ->get();

        if ($salidasDetalladas->isEmpty()) {
            return $vacio;
        }

        // =====================================================
        // 3. Categorías PACK/COMBOS + descendientes (para clasificar)
        // =====================================================
        $idsCategoriasComboPack = $this->obtenerCategoriasComboPack($clienteId);

        $setProductosVentaEnComboPack = [];
        if (!empty($idsCategoriasComboPack)) {
            $idsProductosVentaCombo = DB::connection($this->connection)
                ->table('inventario_producto_categoria')
                ->whereIn('id_categoria', $idsCategoriasComboPack)
                ->where('id_sucursal', $sucursalId)
                ->distinct()
                ->pluck('id_detalle_producto')
                ->toArray();

            $setProductosVentaEnComboPack = array_flip($idsProductosVentaCombo);
        }

        // =====================================================
        // 4. Facturas involucradas + sus líneas de venta
        // =====================================================
        $idsFacturas = $salidasDetalladas->pluck('IdDocumento')->unique()->filter()->values()->toArray();

        $detallesVenta = collect();
        if (!empty($idsFacturas)) {
            $detallesVenta = DB::connection($this->connection)
                ->table('impuestos_ventas_detalle as d')
                ->join('inventario_relacion_ventainventario as r', 'd.idrelacionventainventario', '=', 'r.IdDetalleProducto')
                ->whereIn('d.idventas', $idsFacturas)
                ->where('d.idrelacionventainventario', '>', 0)
                ->select(
                    'd.idventas',
                    'd.idrelacionventainventario as id_producto_venta',
                    'd.unidades',
                    'd.personalizacion',
                    'r.Detalle as nombre_producto_venta',
                    'r.Codigo as codigo_producto_venta'
                )
                ->get();
        }

        // =====================================================
        // 5. Agrupar por documento
        // =====================================================
        $salidasPorDocumento = $salidasDetalladas->groupBy('IdDocumento');
        $ventasPorDocumento  = $detallesVenta->groupBy('idventas');

        // matriz[id_base][id_producto_venta] = cantidad
        $matriz = [];
        // suelto[id_base] = cantidad (salidas no atribuidas a combo)
        $suelto = [];
        // total real por base (control de cuadre)
        $totalRealPorBase = [];

        // metadata por producto de venta para columnas
        $infoProductoVenta = [];

        foreach ($salidasPorDocumento as $idDocumento => $filasSalida) {
            $lineasVenta = $ventasPorDocumento[$idDocumento] ?? collect();

            // Acumular total real SIEMPRE (fuente de verdad)
            foreach ($filasSalida as $fila) {
                $idBase = (int) $fila->IdProducto;
                $totalRealPorBase[$idBase] =
                    ($totalRealPorBase[$idBase] ?? 0) + (float) $fila->Unidades;
            }

            // Sin líneas de venta → no se puede atribuir, va como "suelto" (raro)
            if ($lineasVenta->isEmpty()) {
                foreach ($filasSalida as $fila) {
                    $idBase = (int) $fila->IdProducto;
                    $suelto[$idBase] = ($suelto[$idBase] ?? 0) + (float) $fila->Unidades;
                }
                continue;
            }

            $cantidadLineas = $lineasVenta->count();

            // ---- CASO A: factura con 1 sola línea → atribución DIRECTA ----
            if ($cantidadLineas === 1) {
                $linea = $lineasVenta->first();
                $idProductoVenta = (int) $linea->id_producto_venta;
                $esCombo = isset($setProductosVentaEnComboPack[$idProductoVenta]);

                foreach ($filasSalida as $fila) {
                    $idBase   = (int) $fila->IdProducto;
                    $cantidad = (float) $fila->Unidades;

                    if ($esCombo) {
                        if (!isset($matriz[$idBase])) $matriz[$idBase] = [];
                        if (!isset($matriz[$idBase][$idProductoVenta])) {
                            $matriz[$idBase][$idProductoVenta] = 0;
                        }
                        $matriz[$idBase][$idProductoVenta] += $cantidad;

                        if (!isset($infoProductoVenta[$idProductoVenta])) {
                            $infoProductoVenta[$idProductoVenta] = [
                                'id'     => $idProductoVenta,
                                'nombre' => $linea->nombre_producto_venta ?? "Producto #{$idProductoVenta}",
                                'codigo' => $linea->codigo_producto_venta ?? '',
                            ];
                        }
                    } else {
                        $suelto[$idBase] = ($suelto[$idBase] ?? 0) + $cantidad;
                    }
                }
                continue;
            }

            // ---- CASO B: factura multi-línea → reparto proporcional ----
            $totalUnidadesVenta = (float) $lineasVenta->sum('unidades');
            if ($totalUnidadesVenta <= 0) {
                // fallback: reparto equitativo
                $totalUnidadesVenta = $cantidadLineas;
                $pesos = array_fill(0, $cantidadLineas, 1 / $cantidadLineas);
            } else {
                $pesos = [];
                foreach ($lineasVenta as $linea) {
                    $pesos[] = (float) $linea->unidades / $totalUnidadesVenta;
                }
            }

            $indexLinea = 0;
            foreach ($lineasVenta as $linea) {
                $peso = $pesos[$indexLinea] ?? 0;
                $indexLinea++;

                if ($peso <= 0) continue;

                $idProductoVenta = (int) $linea->id_producto_venta;
                $esCombo = isset($setProductosVentaEnComboPack[$idProductoVenta]);

                foreach ($filasSalida as $fila) {
                    $idBase     = (int) $fila->IdProducto;
                    $cantidadRe = (float) $fila->Unidades * $peso;
                    if ($cantidadRe <= 0) continue;

                    if ($esCombo) {
                        if (!isset($matriz[$idBase])) $matriz[$idBase] = [];
                        if (!isset($matriz[$idBase][$idProductoVenta])) {
                            $matriz[$idBase][$idProductoVenta] = 0;
                        }
                        $matriz[$idBase][$idProductoVenta] += $cantidadRe;

                        if (!isset($infoProductoVenta[$idProductoVenta])) {
                            $infoProductoVenta[$idProductoVenta] = [
                                'id'     => $idProductoVenta,
                                'nombre' => $linea->nombre_producto_venta ?? "Producto #{$idProductoVenta}",
                                'codigo' => $linea->codigo_producto_venta ?? '',
                            ];
                        }
                    } else {
                        $suelto[$idBase] = ($suelto[$idBase] ?? 0) + $cantidadRe;
                    }
                }
            }
        }

        // =====================================================
        // 6. Columnas (una por cada producto de venta combo/pack)
        // =====================================================
        $columnasTemp = [];
        foreach ($matriz as $idBase => $valores) {
            foreach ($valores as $idVenta => $cantidad) {
                if ($cantidad > 0 && !isset($columnasTemp[$idVenta])) {
                    $info = $infoProductoVenta[$idVenta] ?? null;
                    $columnasTemp[$idVenta] = $info ?: [
                        'id'     => (int) $idVenta,
                        'nombre' => "Producto #{$idVenta}",
                        'codigo' => '',
                    ];
                }
            }
        }
        usort($columnasTemp, fn($a, $b) => strcasecmp($a['nombre'], $b['nombre']));
        $columnas = array_values($columnasTemp);

        // =====================================================
        // 7. Filas (todos los productos base con movimiento)
        // =====================================================
        $idsProductosBase = array_keys($totalRealPorBase);

        if (empty($idsProductosBase)) {
            return $vacio;
        }

        $queryProductos = DB::connection($this->connection)
            ->table('inventario_productodetalle as p')
            ->where('p.IdCliente', $clienteId)
            ->whereIn('p.IdProducto', $idsProductosBase)
            ->select('p.IdProducto', 'p.Codigo', 'p.Descripcion');

        if ($search) {
            $queryProductos->where(function ($q) use ($search) {
                $q->where('p.Codigo', 'like', "%{$search}%")
                  ->orWhere('p.Descripcion', 'like', "%{$search}%");
            });
        }

        $productos = $queryProductos->orderBy('p.Descripcion')->get();

        // columnas indexadas por id para buscar rápido
        $columnasIds = array_map(fn($c) => (int) $c['id'], $columnas);
        $columnasSet = array_flip($columnasIds);

        $filas = [];
        foreach ($productos as $producto) {
            $idBase       = (int) $producto->IdProducto;
            $totalReal    = round($totalRealPorBase[$idBase] ?? 0, 4);
            $valoresCombo = $matriz[$idBase] ?? [];

            // Normalizar valores a las columnas existentes
            $valores = [];
            $sumaCombos = 0;
            foreach ($valoresCombo as $idVenta => $cantidad) {
                $idVenta = (int) $idVenta;
                if ($cantidad <= 0) continue;
                if (!isset($columnasSet[$idVenta])) continue;
                $valores[$idVenta] = round($cantidad, 4);
                $sumaCombos += $cantidad;
            }

            // 🔥 Garantía de cuadre: lo que no se atribuyó a combo es suelto
            $otrasSalidas = round($totalReal - $sumaCombos, 4);
            if ($otrasSalidas < 0) $otrasSalidas = 0.0;

            if ($soloConMovimiento && $totalReal <= 0) {
                continue;
            }

            $filas[] = [
                'id_producto'   => $idBase,
                'codigo'        => $producto->Codigo,
                'descripcion'   => $producto->Descripcion,
                'valores'       => $valores,
                'otras_salidas' => $otrasSalidas,
                'total'         => $totalReal,
            ];
        }

        // Orden: total desc, luego descripción
        usort($filas, function ($a, $b) {
            if ($b['total'] !== $a['total']) return $b['total'] <=> $a['total'];
            return strcasecmp($a['descripcion'], $b['descripcion']);
        });

        // =====================================================
        // 8. Detectar productos FRACCIONADOS vs DISCRETOS
        // =====================================================
        $idsProductosFraccionados = $this->detectarProductosFraccionados(
            $idsProductosBase,
            $clienteId
        );

        // =====================================================
        // 9. Normalizar: discretos a enteros, fraccionados con decimales
        // =====================================================
        $filas = $this->normalizarFilas($filas, $idsProductosFraccionados);

        // =====================================================
        // 10. Totales por columna y total general
        // =====================================================
        $totalesPorColumna = [];
        $totalGeneral      = 0;
        $totalOtrasSalidas = 0;

        foreach ($columnas as $col) {
            $idCol = (int) $col['id'];
            $suma = 0;
            foreach ($filas as $f) {
                $suma += $f['valores'][$idCol] ?? 0;
            }
            $totalesPorColumna[$idCol] = round($suma, 4);
            $totalGeneral += $suma;
        }

        foreach ($filas as $f) {
            $totalOtrasSalidas += $f['otras_salidas'];
        }

        return [
            'columnas'          => $columnas,
            'filas'             => $filas,
            'totalesPorColumna' => $totalesPorColumna,
            'totalGeneral'      => round($totalGeneral, 4),
            'totalOtrasSalidas' => round($totalOtrasSalidas, 4),
        ];
    }

    /**
     * Obtiene los IDs de categorías PACK/COMBOS + todos sus descendientes.
     */
    protected function obtenerCategoriasComboPack(int $clienteId): array
    {
        $raices = DB::connection($this->connection)
            ->table('inventario_menu_categoria')
            ->where('id_cliente', $clienteId)
            ->whereIn(DB::raw('UPPER(nombre)'), ['PACK', 'COMBOS'])
            ->pluck('id_categoria')
            ->toArray();

        if (empty($raices)) return [];

        $visitadas   = [];
        $porProcesar = $raices;

        while (!empty($porProcesar)) {
            $idActual = array_shift($porProcesar);
            if (isset($visitadas[$idActual])) continue;
            $visitadas[$idActual] = true;

            $hijas = DB::connection($this->connection)
                ->table('inventario_menu_categoria')
                ->where('id_cliente', $clienteId)
                ->where('id_padre', $idActual)
                ->pluck('id_categoria')
                ->toArray();

            foreach ($hijas as $h) {
                if (!isset($visitadas[$h])) $porProcesar[] = $h;
            }
        }

        return array_keys($visitadas);
    }

    /**
     * Detecta qué productos base son "fraccionados".
     *
     * Un producto es FRACCIONADO si en ALGUNA composición de combo su
     * Porcion tiene decimales. Si todas sus Porcion son enteras (o no
     * aparece en ningún combo), es DISCRETO.
     *
     * @param array $idsProductosBase
     * @param int   $clienteId
     * @return array<int,bool>  [id_producto => true] solo los fraccionados
     */
    protected function detectarProductosFraccionados(array $idsProductosBase, int $clienteId): array
    {
        if (empty($idsProductosBase)) {
            return [];
        }

        // Productos que en alguna composición tienen Porcion decimal → fraccionados
        $fraccionados = DB::connection($this->connection)
            ->table('inventario_relacion_ventainventario_detalle')
            ->whereIn('IdProducto', $idsProductosBase)
            ->whereRaw('Porcion != ROUND(Porcion)')
            ->distinct()
            ->pluck('IdProducto')
            ->map(fn($id) => (int) $id)
            ->all();

        return array_flip($fraccionados);
    }

    /**
     * Normaliza las filas:
     *  - Discretos: redondea a entero (mayor residuo para que la suma cuadre).
     *  - Fraccionados: deja decimales tal cual.
     */
    protected function normalizarFilas(array $filas, array $idsProductosFraccionados): array
    {
        foreach ($filas as &$fila) {
            $idBase        = (int) $fila['id_producto'];
            $esFraccionado = isset($idsProductosFraccionados[$idBase]);

            if ($esFraccionado) {
                // Dejar tal cual (con 4 decimales máx para no arrastrar ruido)
                foreach ($fila['valores'] as $idVenta => $valor) {
                    $fila['valores'][$idVenta] = round((float) $valor, 4);
                }
                $fila['otras_salidas']  = round((float) $fila['otras_salidas'], 4);
                $fila['total']          = round((float) $fila['total'], 4);
                $fila['es_fraccionado'] = true;
                continue;
            }

            // DISCRETO: redondear con mayor residuo para que SUM(valores) + suelto = total entero
            $fila = $this->redondearDiscreto($fila);
            $fila['es_fraccionado'] = false;
        }
        unset($fila);

        return $filas;
    }

    /**
     * Redondea un producto discreto respetando el total.
     *
     * Ejemplo: valores = [combo1: 1.666, combo2: 3.334, suelto: 2.000], total = 7
     * → suma redondeos = 2 + 3 + 2 = 7 → queda igual.
     * Si la suma no cuadra, ajusta la columna con mayor parte fraccionaria.
     */
    protected function redondearDiscreto(array $fila): array
    {
        $totalReal   = (float) $fila['total'];
        $totalEntero = (int) round($totalReal);

        // 1. Redondear cada valor y guardar parte fraccionaria
        $partes = [];
        $sumaRedondeada = 0;

        foreach ($fila['valores'] as $idVenta => $valor) {
            $piso = (int) floor((float) $valor);
            $partes['col_' . $idVenta] = [
                'valor_redondeado' => $piso,
                'fraccion'         => (float) $valor - $piso,
                'es_suelto'        => false,
            ];
            $sumaRedondeada += $piso;
        }

        $pisoSuelto = (int) floor((float) $fila['otras_salidas']);
        $partes['suelto'] = [
            'valor_redondeado' => $pisoSuelto,
            'fraccion'         => (float) $fila['otras_salidas'] - $pisoSuelto,
            'es_suelto'        => true,
        ];
        $sumaRedondeada += $pisoSuelto;

        // 2. Asignar el residuo a las columnas con mayor fracción
        $residuo = $totalEntero - $sumaRedondeada;

        // Ordenar por fracción descendente
        uasort($partes, fn($a, $b) => $b['fraccion'] <=> $a['fraccion']);

        foreach ($partes as &$p) {
            if ($residuo <= 0) break;
            $p['valor_redondeado']++;
            $residuo--;
        }
        unset($p);

        // 3. Reconstruir fila
        $valoresFinales = [];
        $totalSuelto    = 0;
        $totalColumnas  = 0;

        foreach ($partes as $key => $p) {
            if ($p['es_suelto']) {
                $totalSuelto = $p['valor_redondeado'];
            } else {
                $idVenta = (int) str_replace('col_', '', $key);
                if ($p['valor_redondeado'] > 0) {
                    $valoresFinales[$idVenta] = $p['valor_redondeado'];
                }
                $totalColumnas += $p['valor_redondeado'];
            }
        }

        $fila['valores']       = $valoresFinales;
        $fila['otras_salidas'] = (float) $totalSuelto;
        $fila['total']         = (float) ($totalColumnas + $totalSuelto);

        return $fila;
    }
}