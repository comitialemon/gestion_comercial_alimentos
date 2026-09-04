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
     * Mostrar reporte de productos bases
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
            
            foreach ($productos as $producto) {
                // =============================================
                // 2. BUSCAR TODOS LOS PRODUCTOS DE VENTA QUE USAN ESTE PRODUCTO BASE
                // =============================================
                $productosVenta = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_detalle as det')
                    ->join('inventario_relacion_ventainventario as r', 'det.IdDetalleProducto', '=', 'r.IdDetalleProducto')
                    ->where('det.IdProducto', $producto->IdProducto)
                    ->where('r.IdCliente', $clienteId)
                    ->where('r.ActivoInactivo', 0)
                    ->select(
                        'r.IdDetalleProducto as id_producto_venta',
                        'r.Detalle as nombre_producto_venta',
                        'r.Codigo as codigo_producto_venta',
                        'det.Porcion'
                    )
                    ->get();
                
                $totalVendido = 0;
                $ventaSuelto = 0;
                $ventaCompuesta = 0;
                $detallesVenta = [];
                
                foreach ($productosVenta as $productoVenta) {
                    // Calcular ventas de este producto de venta
                    $ventas = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_detalle as d')
                        ->join('impuestos_ventas as v', 'd.idventas', '=', 'v.IdVentas')
                        ->join('todos_fecha as tf', 'v.FechaVenta', '=', 'tf.Fecha')
                        ->where('d.idrelacionventainventario', $productoVenta->id_producto_venta)
                        ->where('v.IdCliente', $clienteId)
                        ->where('v.IdClienteSucursal', $sucursalId)
                        ->where('v.ActivoInactivo', 1)
                        ->whereBetween('tf.Fecha', [$fechaInicial, $fechaFinal])
                        ->sum('d.unidades');
                    
                    if ($ventas > 0) {
                        // Calcular cuántas unidades del producto base se vendieron
                        $cantidadBase = $ventas * (float) $productoVenta->Porcion;
                        $totalVendido += $cantidadBase;
                        
                        // Determinar si es "suelto" o "compuesto"
                        // Un producto es "suelto" si su nombre contiene palabras clave
                        // o si su porción es 1 y no tiene otros productos en su composición
                        $nombre = strtolower($productoVenta->nombre_producto_venta);
                        $esSuelto = false;
                        
                        // Verificar si este producto de venta tiene otros productos en su composición
                        $tieneOtrosProductos = DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('inventario_relacion_ventainventario_detalle')
                            ->where('IdDetalleProducto', $productoVenta->id_producto_venta)
                            ->where('IdProducto', '!=', $producto->IdProducto)
                            ->exists();
                        
                        // Es suelto si:
                        // 1. Porción = 1
                        // 2. No tiene otros productos en su composición
                        // 3. El nombre coincide con el producto base (o es muy similar)
                        if ((float) $productoVenta->Porcion == 1 && !$tieneOtrosProductos) {
                            $esSuelto = true;
                        }
                        
                        if ($esSuelto) {
                            $ventaSuelto += $cantidadBase;
                        } else {
                            $ventaCompuesta += $cantidadBase;
                        }
                        
                        $detallesVenta[] = [
                            'id_producto_venta' => $productoVenta->id_producto_venta,
                            'nombre' => $productoVenta->nombre_producto_venta,
                            'codigo' => $productoVenta->codigo_producto_venta,
                            'porcion' => (float) $productoVenta->Porcion,
                            'unidades_vendidas' => (float) $ventas,
                            'cantidad_base' => $cantidadBase,
                            'es_suelto' => $esSuelto
                        ];
                    }
                }
                
                // Solo mostrar si tiene movimiento
                if ($totalVendido > 0) {
                    $producto->total_vendido = $totalVendido;
                    $producto->venta_suelto = $ventaSuelto;
                    $producto->venta_compuesta = $ventaCompuesta;
                    $producto->detalles_venta = $detallesVenta;
                    $producto->total_productos_venta = count($detallesVenta);
                    $productosBases->push($producto);
                }
            }
            
            // Ordenar por total vendido (descendente)
            $productosBases = $productosBases->sortByDesc('total_vendido')->values();
        }
        
        return Inertia::render('Gestion/Inventario/ReporteProductosBase/Index', [
            'productos' => $productosBases,
            'sucursales' => $sucursales,
            'fechaInicial' => $fechaInicial,
            'fechaFinal' => $fechaFinal,
            'sucursalSeleccionada' => $sucursalId,
            'search' => $search,
        ]);
    }
}