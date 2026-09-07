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
     * Mostrar reporte de productos bases con desglose de ventas
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // Sucursales
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        $sucursalId = $request->sucursal_id;
        $fechaInicial = $request->fecha_inicial ?? date('Y-m-01');
        $fechaFinal = $request->fecha_final ?? date('Y-m-d');
        $search = $request->search;
        $soloConMovimiento = $request->boolean('solo_con_movimiento', false);
        
        // Agregar horas para incluir todo el día final
        $fechaInicialConHora = $fechaInicial . ' 00:00:00';
        $fechaFinalConHora = $fechaFinal . ' 23:59:59';
        
        $productosBases = collect();
        
        if ($sucursalId && $sucursalId > 0) {
            // =============================================
            // 1. OBTENER TODOS LOS PRODUCTOS BASE
            // =============================================
            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle as p')
                ->where('p.IdCliente', $clienteId)
                ->where('p.ActivoInactivo', 0)
                ->select('p.IdProducto', 'p.Codigo', 'p.Descripcion');
            
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('p.Codigo', 'like', "%{$search}%")
                      ->orWhere('p.Descripcion', 'like', "%{$search}%");
                });
            }
            
            $productos = $query->orderBy('p.Descripcion')->get();
            
            // =============================================
            // 2. MAPEAR PRODUCTOS BASE POR NOMBRE
            // =============================================
            $productosBasePorNombre = [];
            foreach ($productos as $p) {
                $productosBasePorNombre[strtolower(trim($p->Descripcion))] = $p->IdProducto;
            }
            
            // =============================================
            // 3. OBTENER TODAS LAS RELACIONES
            // =============================================
            $relaciones = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle as det')
                ->join('inventario_relacion_ventainventario as r', 'det.IdDetalleProducto', '=', 'r.IdDetalleProducto')
                ->where('r.IdCliente', $clienteId)
                ->where('r.ActivoInactivo', 0)
                ->select(
                    'det.IdProducto as id_producto_base',
                    'r.IdDetalleProducto as id_producto_venta',
                    'r.Detalle as nombre_producto_venta',
                    'r.Codigo as codigo_producto_venta',
                    'det.Porcion'
                )
                ->get();
            
            // Mapa de productos de venta -> sus productos base
            $productoVentaToBase = [];
            foreach ($relaciones as $rel) {
                $productoVentaToBase[$rel->id_producto_venta][] = [
                    'id_producto_base' => $rel->id_producto_base,
                    'porcion' => (float) $rel->Porcion,
                    'nombre_producto_venta' => $rel->nombre_producto_venta
                ];
            }
            
            // =============================================
            // 4. OBTENER VENTAS DEL PERÍODO (AGRUPADAS POR PRODUCTO DE VENTA)
            // =============================================
            $ventasPorProductoBase = [];
            $detallesVentaPorProducto = [];
            
            if ($sucursalId > 0) {
                // 🔥 CONSULTA AGRUPADA POR PRODUCTO DE VENTA
                $ventas = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas as v')
                    ->join('impuestos_ventas_detalle as d', 'v.IdVentas', '=', 'd.idventas')
                    ->where('v.IdCliente', $clienteId)
                    ->where('v.IdClienteSucursal', $sucursalId)
                    ->where('v.ActivoInactivo', 1)
                    ->whereBetween('v.FechaVenta', [$fechaInicialConHora, $fechaFinalConHora])
                    ->whereNotNull('d.idrelacionventainventario')
                    ->where('d.idrelacionventainventario', '>', 0)
                    ->select(
                        'd.idrelacionventainventario as id_producto_venta',
                        DB::raw('SUM(d.unidades) as total_unidades'),
                        DB::raw('GROUP_CONCAT(d.personalizacion SEPARATOR "|||") as personalizaciones')
                    )
                    ->groupBy('d.idrelacionventainventario')
                    ->get();
                
                foreach ($ventas as $venta) {
                    $idProductoVenta = $venta->id_producto_venta;
                    $unidadesVendidas = (float) $venta->total_unidades;
                    
                    // Obtener el nombre del producto de venta
                    $nombreProductoVenta = $this->getNombreProductoVenta($idProductoVenta);
                    $nombreProductoVentaLower = strtolower(trim($nombreProductoVenta));
                    
                    // 🔥 DETECTAR SI ES COMBO POR NOMBRE
                    $palabrasCombo = ['pack', 'combo', 'kit', 'promo', 'especial', 'mix', 'variado', 'duo', 'triple', 'completo', 'familia', 'oferta'];
                    $esComboPorNombre = false;
                    foreach ($palabrasCombo as $palabra) {
                        if (strpos($nombreProductoVentaLower, $palabra) !== false) {
                            $esComboPorNombre = true;
                            break;
                        }
                    }
                    
                    // 🔥 VERIFICAR SI TIENE COMPOSICIÓN
                    $composicion = $productoVentaToBase[$idProductoVenta] ?? [];
                    $tieneComposicion = count($composicion) > 0;
                    
                    // 🔥 VERIFICAR SI ES SUELTO
                    $esVentaSuelta = isset($productosBasePorNombre[$nombreProductoVentaLower]);
                    
                    // 🔥 CLASIFICACIÓN FINAL
                    $esSuelto = false;
                    
                    if ($esVentaSuelta && !$esComboPorNombre) {
                        $esSuelto = true;
                    } elseif (!$tieneComposicion && !$esComboPorNombre) {
                        $esSuelto = true;
                    } elseif ($tieneComposicion && count($composicion) == 1 && $composicion[0]['porcion'] == 1 && !$esComboPorNombre) {
                        $esSuelto = true;
                    }
                    
                    if ($esSuelto) {
                        // 🔥 VENTA SUELTA
                        $idProductoBase = $esVentaSuelta 
                            ? $productosBasePorNombre[$nombreProductoVentaLower] 
                            : ($composicion[0]['id_producto_base'] ?? null);
                        
                        if ($idProductoBase) {
                            $cantidadBase = $unidadesVendidas;
                            
                            if (!isset($ventasPorProductoBase[$idProductoBase])) {
                                $ventasPorProductoBase[$idProductoBase] = 0;
                            }
                            $ventasPorProductoBase[$idProductoBase] += $cantidadBase;
                            
                            $detallesVentaPorProducto[$idProductoBase][] = [
                                'id_producto_venta' => $idProductoVenta,
                                'nombre' => $nombreProductoVenta,
                                'unidades_vendidas' => $unidadesVendidas,
                                'cantidad_base' => $cantidadBase,
                                'es_suelto' => true,
                                'tiene_personalizacion' => false,
                                'porcion' => 1,
                                'tipo' => 'Suelto'
                            ];
                        }
                    } else {
                        // 🔥 VENTA COMPUESTA (COMBO/PACK)
                        if (empty($composicion)) {
                            continue;
                        }
                        
                        // 🔥 PROCESAR PERSONALIZACIÓN - Usar la primera que tenga datos
                        $personalizacion = null;
                        if ($venta->personalizaciones) {
                            $personalizacionesArray = explode('|||', $venta->personalizaciones);
                            foreach ($personalizacionesArray as $p) {
                                if ($p && $p != 'null' && $p != '[]') {
                                    $personalizacion = json_decode($p, true);
                                    if ($personalizacion && is_array($personalizacion) && count($personalizacion) > 0) {
                                        break;
                                    }
                                }
                            }
                        }
                        
                        $productosReales = [];
                        
                        if ($personalizacion && is_array($personalizacion)) {
                            // Con personalización
                            foreach ($personalizacion as $comboData) {
                                $sustitutos = $comboData['sustitutos'] ?? [];
                                
                                foreach ($composicion as $comp) {
                                    $idOriginal = $comp['id_producto_base'];
                                    $cantidadPorPack = $comp['porcion'];
                                    
                                    $totalReemplazado = 0;
                                    foreach ($sustitutos as $sust) {
                                        if ($sust['id_producto_original'] == $idOriginal) {
                                            $totalReemplazado += (float) ($sust['cantidad'] ?? 0);
                                        }
                                    }
                                    
                                    $quedanOriginales = $cantidadPorPack - $totalReemplazado;
                                    
                                    if ($quedanOriginales > 0) {
                                        if (!isset($productosReales[$idOriginal])) {
                                            $productosReales[$idOriginal] = 0;
                                        }
                                        $productosReales[$idOriginal] += $quedanOriginales * $unidadesVendidas;
                                    }
                                    
                                    foreach ($sustitutos as $sust) {
                                        $idSustituto = $sust['id_producto_sustituto'];
                                        $cantidadSustituto = (float) ($sust['cantidad'] ?? 0);
                                        
                                        if ($cantidadSustituto > 0 && $idSustituto != $idOriginal) {
                                            if (!isset($productosReales[$idSustituto])) {
                                                $productosReales[$idSustituto] = 0;
                                            }
                                            $productosReales[$idSustituto] += $cantidadSustituto * $unidadesVendidas;
                                        }
                                    }
                                }
                            }
                        } else {
                            // Sin personalización
                            foreach ($composicion as $comp) {
                                $idProductoBase = $comp['id_producto_base'];
                                $cantidadBase = $comp['porcion'] * $unidadesVendidas;
                                
                                if (!isset($productosReales[$idProductoBase])) {
                                    $productosReales[$idProductoBase] = 0;
                                }
                                $productosReales[$idProductoBase] += $cantidadBase;
                            }
                        }
                        
                        // Acumular productos reales
                        foreach ($productosReales as $idProductoBase => $cantidadTotal) {
                            if ($cantidadTotal <= 0) continue;
                            
                            if (!isset($ventasPorProductoBase[$idProductoBase])) {
                                $ventasPorProductoBase[$idProductoBase] = 0;
                            }
                            $ventasPorProductoBase[$idProductoBase] += $cantidadTotal;
                            
                            $detallesVentaPorProducto[$idProductoBase][] = [
                                'id_producto_venta' => $idProductoVenta,
                                'nombre' => $nombreProductoVenta,
                                'unidades_vendidas' => $unidadesVendidas,
                                'cantidad_base' => $cantidadTotal,
                                'es_suelto' => false,
                                'tiene_personalizacion' => $personalizacion !== null,
                                'porcion' => $cantidadTotal / $unidadesVendidas,
                                'tipo' => 'Combo/Pack'
                            ];
                        }
                    }
                }
            }
            
            // =============================================
            // 5. PROCESAR CADA PRODUCTO BASE
            // =============================================
            foreach ($productos as $producto) {
                $totalVendido = $ventasPorProductoBase[$producto->IdProducto] ?? 0;
                $tieneVentas = $totalVendido > 0;
                $detallesVenta = $detallesVentaPorProducto[$producto->IdProducto] ?? [];
                
                // Clasificar suelto/compuesto
                $ventaSuelto = 0;
                $ventaCompuesta = 0;
                
                foreach ($detallesVenta as $detalle) {
                    if ($detalle['es_suelto']) {
                        $ventaSuelto += $detalle['cantidad_base'];
                    } else {
                        $ventaCompuesta += $detalle['cantidad_base'];
                    }
                }
                
                // Mostrar todos los productos
                if (!$soloConMovimiento || $tieneVentas) {
                    $producto->total_vendido = $totalVendido;
                    $producto->venta_suelto = $ventaSuelto;
                    $producto->venta_compuesta = $ventaCompuesta;
                    $producto->detalles_venta = $detallesVenta;
                    $producto->total_productos_venta = count($detallesVenta);
                    $producto->tiene_movimiento = $tieneVentas;
                    
                    $productosBases->push($producto);
                }
            }
            
            // Ordenar
            $productosBases = $productosBases->sortByDesc(function($item) {
                return $item->tiene_movimiento ? 1 : 0;
            })->values();
        }
        
        return Inertia::render('Gestion/Inventario/ReporteProductosBase/Index', [
            'productos' => $productosBases,
            'sucursales' => $sucursales,
            'fechaInicial' => $fechaInicial,
            'fechaFinal' => $fechaFinal,
            'sucursalSeleccionada' => $sucursalId,
            'search' => $search,
            'soloConMovimiento' => $soloConMovimiento,
        ]);
    }
    
    /**
     * Obtener nombre del producto de venta
     */
    private function getNombreProductoVenta($idProductoVenta)
    {
        $producto = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario')
            ->where('IdDetalleProducto', $idProductoVenta)
            ->first();
        
        return $producto ? $producto->Detalle : 'Producto #' . $idProductoVenta;
    }
}