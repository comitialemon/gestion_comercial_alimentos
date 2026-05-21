<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\CategoriaProducto;
use App\Models\Gestion\Inventario\ProductoVenta;
use App\Models\Gestion\Inventario\ProductoCategoria;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AsignarProductoCategoriaController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        
        // ✅ Categorías por cliente (sin sucursal)
        $categorias = CategoriaProducto::where('id_cliente', $clienteId)
            ->with('padre')
            ->orderBy('orden')
            ->get();
        
        $categoriasArbol = $this->buildTree($categorias);
        
        // ✅ TODOS los productos del cliente (activos), sin filtrar por sucursal
        $productos = ProductoVenta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto as id', 'Detalle as nombre', 'PrecioVenta']);
        
        // ✅ Asignaciones por sucursal actual
        $sucursalId = session('cliente_sucursal_id');
        $asignaciones = [];
        
        if ($categorias->isNotEmpty()) {
            $asignaciones = ProductoCategoria::where('id_sucursal', $sucursalId)
                ->whereIn('id_categoria', $categorias->pluck('id_categoria'))
                ->get()
                ->groupBy('id_categoria')
                ->map(function ($items) {
                    return $items->pluck('id_detalle_producto')->toArray();
                });
        }
        
        // ✅ También pasar la lista de categorías completa para mostrar nombres
        return Inertia::render('Gestion/Inventario/AsignarProductoCategoria/Index', [
            'categorias' => $categoriasArbol,
            'productos' => $productos,
            'asignaciones' => $asignaciones,
            'categoriasLista' => $categorias,
            'sucursalId' => $sucursalId,
            'sucursalNombre' => session('cliente_sucursal_nombre'),
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'id_categoria' => 'required|exists:inventario_menu_categoria,id_categoria',
            'productos_ids' => 'array',
            'productos_ids.*' => 'exists:inventario_relacion_ventainventario,IdDetalleProducto'
        ]);
        
        $sucursalId = session('cliente_sucursal_id');
        
        // ✅ Eliminar asignaciones actuales de esta categoría para esta sucursal
        ProductoCategoria::where('id_categoria', $request->id_categoria)
            ->where('id_sucursal', $sucursalId)
            ->delete();
        
        // ✅ Crear nuevas asignaciones
        foreach ($request->productos_ids as $idProducto) {
            ProductoCategoria::create([
                'id_detalle_producto' => $idProducto,
                'id_categoria' => $request->id_categoria,
                'id_sucursal' => $sucursalId,
            ]);
        }
        
        $categoria = CategoriaProducto::find($request->id_categoria);
        $sucursalNombre = session('cliente_sucursal_nombre');
        
        return redirect()->back()->with('success', 
            count($request->productos_ids) . " productos asignados a '{$categoria->nombre}' para la sucursal '{$sucursalNombre}'");
    }
    
    private function buildTree($categorias, $parentId = null, $level = 0)
    {
        $result = [];
        
        foreach ($categorias as $cat) {
            if ($cat->id_padre == $parentId) {
                $prefix = str_repeat('— ', $level);
                $result[] = [
                    'id' => $cat->id_categoria,
                    'nombre' => $prefix . $cat->nombre,
                    'nivel' => $level,
                    'raw_nombre' => $cat->nombre
                ];
                $result = array_merge($result, $this->buildTree($categorias, $cat->id_categoria, $level + 1));
            }
        }
        
        return $result;
    }
}