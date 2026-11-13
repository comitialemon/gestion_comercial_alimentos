<?php

namespace App\Services\Inventario;

use Illuminate\Support\Facades\DB;

class ProductoBaseComboCalculator
{
    protected $connection = 'mysql_gestion_comercial_alimentos';

    public function calcular(
        int $clienteId,
        int $sucursalId,
        string $fechaInicial,
        string $fechaFinal,
        ?string $search = null,
        bool $soloConMovimiento = false
    ): array {
        $vacio = [
            'columnas'          => [],
            'filas'             => [],
            'totalesPorColumna' => [],
            'totalGeneral'      => 0,
            'totalOtrasSalidas' => 0,
        ];

        if (!$sucursalId || $sucursalId <= 0) return $vacio;

        // 1. Tipo de operación Ventas
        $idTipoOperacionVentas = DB::connection($this->connection)
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('Detalle', 'Ventas')
            ->where('ActivoInactivo', 0)
            ->value('IdTipoOperacion');

        if (!$idTipoOperacionVentas) return $vacio;

        // 2. Salidas reales (La verdad absoluta del inventario)
        $salidasDetalladas = DB::connection($this->connection)
            ->table('inventario_propiamente as ip')
            ->join('todos_fecha as tf', 'ip.IdFecha', '=', 'tf.IdFecha')
            ->where('ip.IdCliente', $clienteId)
            ->where('ip.IdSucursal', $sucursalId)
            ->where('ip.IdTipoDeOperacion', $idTipoOperacionVentas)
            ->whereRaw("UPPER(TRIM(ip.D_H)) = 'H'")
            ->whereBetween('tf.Fecha', [$fechaInicial, $fechaFinal])
            ->select('ip.IdProducto', 'ip.Unidades', 'ip.IdDocumento')
            ->get();

        if ($salidasDetalladas->isEmpty()) return $vacio;

        // 3. Identificar COMBOS por categoría
        $categoriasComboPack = DB::connection($this->connection)
            ->table('inventario_menu_categoria')
            ->where('id_cliente', $clienteId)
            ->where(function($q) {
                $q->where('nombre', 'LIKE', '%COMBO%')
                  ->orWhere('nombre', 'LIKE', '%PACK%');
            })
            ->pluck('id_categoria')
            ->toArray();

        $idsCombosPermitidos = [];
        if (!empty($categoriasComboPack)) {
            $productosEnCategorias = DB::connection($this->connection)
                ->table('inventario_producto_categoria')
                ->whereIn('id_categoria', $categoriasComboPack)
                ->where('id_sucursal', $sucursalId)
                ->distinct()
                ->pluck('id_detalle_producto')
                ->toArray();
            
            $idsCombosPermitidos = array_flip($productosEnCategorias);
        }

        // 4. Composición exacta de los combos
        $composicionPorProductoVenta = [];
        if (!empty($idsCombosPermitidos)) {
            $composiciones = DB::connection($this->connection)
                ->table('inventario_relacion_ventainventario_detalle')
                ->whereIn('IdDetalleProducto', array_keys($idsCombosPermitidos))
                ->select('IdDetalleProducto', 'IdProducto', 'Porcion')
                ->get();

            foreach ($composiciones as $c) {
                $composicionPorProductoVenta[$c->IdDetalleProducto][] = [
                    'id_producto_base' => (int) $c->IdProducto,
                    'porcion'          => (float) $c->Porcion,
                ];
            }
        }

        // 5. Facturas + líneas
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

        $salidasPorDocumento = $salidasDetalladas->groupBy('IdDocumento');
        $ventasPorDocumento  = $detallesVenta->groupBy('idventas');

        $matriz            = [];  
        $totalRealPorBase  = [];
        $infoProductoVenta = [];

        foreach ($salidasPorDocumento as $idDocumento => $filasSalida) {
            $lineasVenta = $ventasPorDocumento[$idDocumento] ?? collect();

            $inventarioDisponible = [];
            foreach ($filasSalida as $fila) {
                $idBase = (int) $fila->IdProducto;
                $inventarioDisponible[$idBase] = ($inventarioDisponible[$idBase] ?? 0) + (float) $fila->Unidades;
                $totalRealPorBase[$idBase] = ($totalRealPorBase[$idBase] ?? 0) + (float) $fila->Unidades;
            }

            if ($lineasVenta->isEmpty()) {
                continue;
            }

            // Calcular requerimiento de COMBOS (Según la receta, sin adivinar)
            $requeridoCombos = [];

            foreach ($lineasVenta as $linea) {
                $idProductoVenta  = (int) $linea->id_producto_venta;
                $unidadesVendidas = (float) $linea->unidades;
                if ($unidadesVendidas <= 0) continue;

                if (isset($idsCombosPermitidos[$idProductoVenta])) {
                    $composicion = $composicionPorProductoVenta[$idProductoVenta] ?? [];
                    
                    if (!empty($composicion)) {
                        $personalizacion = null;
                        if ($linea->personalizacion && $linea->personalizacion !== 'null' && $linea->personalizacion !== '[]') {
                            $personalizacion = json_decode($linea->personalizacion, true);
                        }

                        $desglose = $this->desglosarCombo($composicion, $personalizacion, $unidadesVendidas);
                        
                        foreach ($desglose as $idBase => $cantidad) {
                            if ($cantidad <= 0) continue;
                            $requeridoCombos[$idBase][$idProductoVenta] = ($requeridoCombos[$idBase][$idProductoVenta] ?? 0) + $cantidad;
                        }

                        if (!isset($infoProductoVenta[$idProductoVenta])) {
                            $infoProductoVenta[$idProductoVenta] = [
                                'id'     => $idProductoVenta,
                                'nombre' => $linea->nombre_producto_venta ?? "Producto #{$idProductoVenta}",
                                'codigo' => $linea->codigo_producto_venta ?? '',
                            ];
                        }
                    }
                }
            }

            // Asignar Combos al Inventario
            foreach ($requeridoCombos as $idBase => $ventasCombo) {
                foreach ($ventasCombo as $idProductoVenta => $cantCombo) {
                    $disp = $inventarioDisponible[$idBase] ?? 0;
                    if ($disp <= 0) continue;

                    $asignar = min($cantCombo, $disp);
                    if ($asignar > 0) {
                        if (!isset($matriz[$idBase])) $matriz[$idBase] = [];
                        if (!isset($matriz[$idBase][$idProductoVenta])) $matriz[$idBase][$idProductoVenta] = 0;
                        $matriz[$idBase][$idProductoVenta] += $asignar;
                        $inventarioDisponible[$idBase] -= $asignar;
                    }
                }
            }
        }

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

        $idsProductosBase = array_keys($totalRealPorBase);
        if (empty($idsProductosBase)) return $vacio;

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
        $columnasIds = array_map(fn($c) => (int) $c['id'], $columnas);
        $columnasSet = array_flip($columnasIds);

        $filas = [];
        foreach ($productos as $producto) {
            $idBase       = (int) $producto->IdProducto;
            $totalReal    = round($totalRealPorBase[$idBase] ?? 0, 4);
            $valoresCombo = $matriz[$idBase] ?? [];

            $valores = [];
            $sumaCombos = 0;
            foreach ($valoresCombo as $idVenta => $cantidad) {
                $idVenta = (int) $idVenta;
                if ($cantidad <= 0) continue;
                if (!isset($columnasSet[$idVenta])) continue;
                $valores[$idVenta] = round($cantidad, 4);
                $sumaCombos += $cantidad;
            }

            // El sobrante real del inventario SIEMPRE va a Otras Salidas
            $otrasSalidas = round($totalReal - $sumaCombos, 4);
            if ($otrasSalidas < 0) $otrasSalidas = 0.0;

            if ($soloConMovimiento && $totalReal <= 0) continue;

            $filas[] = [
                'id_producto'   => $idBase,
                'codigo'        => $producto->Codigo,
                'descripcion'   => $producto->Descripcion,
                'valores'       => $valores,
                'otras_salidas' => $otrasSalidas,
                'total'         => $totalReal,
            ];
        }

        usort($filas, function ($a, $b) {
            if ($b['total'] !== $a['total']) return $b['total'] <=> $a['total'];
            return strcasecmp($a['descripcion'], $b['descripcion']);
        });

        $idsProductosFraccionados = $this->detectarProductosFraccionados($idsProductosBase);
        $filas = $this->normalizarFilas($filas, $idsProductosFraccionados);

        $totalesPorColumna = [];
        $totalGeneral = 0;
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

    protected function desglosarCombo($composicion, $personalizacion, $unidadesVendidas): array
    {
        $resultado = [];

        if (empty($personalizacion) || !is_array($personalizacion)) {
            foreach ($composicion as $comp) {
                $idBase   = (int) $comp['id_producto_base'];
                $cantidad = (float) $comp['porcion'] * $unidadesVendidas;
                if ($cantidad > 0) {
                    $resultado[$idBase] = ($resultado[$idBase] ?? 0) + $cantidad;
                }
            }
            return $resultado;
        }

        foreach ($personalizacion as $comboData) {
            $sustitutos = $comboData['sustitutos'] ?? [];

            $sustitutosPorOriginal = [];
            foreach ($sustitutos as $sust) {
                $idOriginal  = (int) ($sust['id_producto_original'] ?? 0);
                $idSustituto = (int) ($sust['id_producto_sustituto'] ?? 0);
                $cantidad    = (float) ($sust['cantidad'] ?? 0);
                if ($idOriginal <= 0 || $cantidad <= 0) continue;
                $sustitutosPorOriginal[$idOriginal][] = [
                    'id_sustituto' => $idSustituto,
                    'cantidad'     => $cantidad,
                ];
            }

            foreach ($composicion as $comp) {
                $idOriginal      = (int) $comp['id_producto_base'];
                $porcionOriginal = (float) $comp['porcion'];

                if (!isset($sustitutosPorOriginal[$idOriginal])) {
                    if ($porcionOriginal > 0) {
                        $resultado[$idOriginal] = ($resultado[$idOriginal] ?? 0) + $porcionOriginal;
                    }
                    continue;
                }

                $totalSustituido = 0;
                foreach ($sustitutosPorOriginal[$idOriginal] as $s) {
                    if ($s['cantidad'] > 0) {
                        $resultado[$s['id_sustituto']] = ($resultado[$s['id_sustituto']] ?? 0) + $s['cantidad'];
                    }
                    $totalSustituido += $s['cantidad'];
                }

                $remanente = $porcionOriginal - $totalSustituido;
                if ($remanente > 0) {
                    $resultado[$idOriginal] = ($resultado[$idOriginal] ?? 0) + $remanente;
                }
            }
        }

        return $resultado;
    }

    protected function detectarProductosFraccionados(array $idsProductosBase): array
    {
        if (empty($idsProductosBase)) return [];

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

    protected function normalizarFilas(array $filas, array $idsProductosFraccionados): array
    {
        foreach ($filas as &$fila) {
            $idBase = (int) $fila['id_producto'];
            $esFraccionado = isset($idsProductosFraccionados[$idBase]);

            if ($esFraccionado) {
                foreach ($fila['valores'] as $idVenta => $valor) {
                    $fila['valores'][$idVenta] = round((float) $valor, 4);
                }
                $fila['otras_salidas'] = round((float) $fila['otras_salidas'], 4);
                $fila['total'] = round((float) $fila['total'], 4);
                $fila['es_fraccionado'] = true;
                continue;
            }

            $fila = $this->redondearDiscreto($fila);
            $fila['es_fraccionado'] = false;
        }
        unset($fila);

        return $filas;
    }

    protected function redondearDiscreto(array $fila): array
    {
        $totalReal = (float) $fila['total'];
        $totalEntero = (int) round($totalReal);

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

        $residuo = $totalEntero - $sumaRedondeada;
        uasort($partes, fn($a, $b) => $b['fraccion'] <=> $a['fraccion']);

        foreach ($partes as &$p) {
            if ($residuo <= 0) break;
            $p['valor_redondeado']++;
            $residuo--;
        }
        unset($p);

        $valoresFinales = [];
        $totalSuelto = 0;
        $totalColumnas = 0;

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

        $fila['valores'] = $valoresFinales;
        $fila['otras_salidas'] = (float) $totalSuelto;
        $fila['total'] = (float) ($totalColumnas + $totalSuelto);

        return $fila;
    }
}