<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\InventarioPropiamente;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Inventario\ProductoLinea;
use App\Models\Gestion\Inventario\ProductoGrupo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class InventarioActualController extends Controller
{
    /**
     * Mostrar inventario actual con stock por producto
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // Obtener almacenes disponibles
        $almacenes = Almacen::porContexto()
            ->orderBy('AlmacenPrincipal', 'desc')
            ->orderBy('Almacen')
            ->get(['IdAlmacen as id', 'Almacen as nombre', 'AlmacenPrincipal as principal']);
        
        $almacenId = $request->almacen_id ?? $almacenes->first()?->id;
        
        // Query base para productos
        $query = ProductoDetalle::porContexto()
            ->activos()
            ->with(['unidadMedida', 'linea', 'grupo']);
        
        // Filtrar por búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                  ->orWhere('Descripcion', 'like', "%{$search}%");
            });
        }
        
        // Filtrar por línea
        if ($request->filled('linea_id')) {
            $query->where('IdLineaProducto', $request->linea_id);
        }
        
        // Filtrar por grupo
        if ($request->filled('grupo_id')) {
            $query->where('IdGrupoProducto', $request->grupo_id);
        }
        
        // Obtener productos con stock calculado
        $productos = $query->orderBy('Descripcion')
            ->paginate(20)
            ->withQueryString();
        
        // Calcular stock para cada producto
        foreach ($productos as $producto) {
            $entradas = InventarioPropiamente::porContexto()
                ->where('IdProducto', $producto->IdProducto)
                ->where('D_H', 'D');
            
            $salidas = InventarioPropiamente::porContexto()
                ->where('IdProducto', $producto->IdProducto)
                ->where('D_H', 'H');
            
            if ($almacenId) {
                $entradas->where('IdAlmacen', $almacenId);
                $salidas->where('IdAlmacen', $almacenId);
            }
            
            $producto->stock_actual = $entradas->sum('Unidades') - $salidas->sum('Unidades');
            $producto->stock_entradas = $entradas->sum('Unidades');
            $producto->stock_salidas = $salidas->sum('Unidades');
        }
        
        // Obtener líneas para filtros (solo las que tienen productos)
        $lineas = ProductoLinea::porContexto()
            ->whereHas('productos', function($q) {
                $q->porContexto();
            })
            ->orderBy('Linea')
            ->get(['IdLinea as id', 'Linea as nombre']);
        
        // Obtener grupos para filtros (solo los que tienen productos)
        $grupos = ProductoGrupo::porContexto()
            ->whereHas('productos', function($q) {
                $q->porContexto();
            })
            ->orderBy('Grupo')
            ->get(['IdProductoGrupo as id', 'Grupo as nombre']);
        
        return Inertia::render('Gestion/Inventario/InventarioActual/Index', [
            'productos' => $productos,
            'almacenes' => $almacenes,
            'almacenSeleccionado' => $almacenId,
            'lineas' => $lineas,
            'grupos' => $grupos,
            'filtros' => [
                'search' => $request->search,
                'linea_id' => $request->linea_id,
                'grupo_id' => $request->grupo_id,
            ]
        ]);
    }

    /**
     * API: Obtener stock actual de un producto específico
     */
    public function getStockProducto($idProducto, Request $request)
    {
        $almacenId = $request->almacen_id;
        
        $entradas = InventarioPropiamente::porContexto()
            ->where('IdProducto', $idProducto)
            ->where('D_H', 'D');
        
        $salidas = InventarioPropiamente::porContexto()
            ->where('IdProducto', $idProducto)
            ->where('D_H', 'H');
        
        if ($almacenId) {
            $entradas->where('IdAlmacen', $almacenId);
            $salidas->where('IdAlmacen', $almacenId);
        }
        
        $stock = $entradas->sum('Unidades') - $salidas->sum('Unidades');
        
        return response()->json([
            'success' => true,
            'producto_id' => $idProducto,
            'stock' => (float) $stock,
            'entradas' => (float) $entradas->sum('Unidades'),
            'salidas' => (float) $salidas->sum('Unidades')
        ]);
    }

    /**
     * API: Obtener movimientos de un producto
     */
    public function getMovimientosProducto($idProducto, Request $request)
    {
        $almacenId = $request->almacen_id;
        $limit = $request->limit ?? 50;
        
        $movimientos = InventarioPropiamente::porContexto()
            ->with(['tipoOperacion', 'almacen'])
            ->where('IdProducto', $idProducto)
            ->when($almacenId, function($q) use ($almacenId) {
                $q->where('IdAlmacen', $almacenId);
            })
            ->orderBy('IdInventarioPropiamente', 'desc')
            ->limit($limit)
            ->get();
        
        return response()->json([
            'success' => true,
            'movimientos' => $movimientos
        ]);
    }
}