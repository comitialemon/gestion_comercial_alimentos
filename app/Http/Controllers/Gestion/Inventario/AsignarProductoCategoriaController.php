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
        $categorias = CategoriaProducto::porContexto()
            ->with('padre')
            ->orderBy('orden')
            ->get();
        
        $categoriasArbol = $this->buildTree($categorias);
        
        $productos = ProductoVenta::porContexto()
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto as id', 'Detalle as nombre', 'PrecioVenta']);
        
        $asignaciones = [];
        if ($categorias->isNotEmpty()) {
            $asignaciones = ProductoCategoria::whereIn('id_categoria', $categorias->pluck('id_categoria'))
                ->get()
                ->groupBy('id_categoria')
                ->map(function ($items) {
                    return $items->pluck('id_detalle_producto')->toArray();
                });
        }
        
        return Inertia::render('Gestion/Inventario/AsignarProductoCategoria/Index', [
            'categorias' => $categoriasArbol,
            'productos' => $productos,
            'asignaciones' => $asignaciones,
            'categoriasLista' => $categorias
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'id_categoria' => 'required|exists:inventario_menu_categoria,id_categoria',
            'productos_ids' => 'array',
            'productos_ids.*' => 'exists:inventario_relacion_ventainventario,IdDetalleProducto'
        ]);
        
        ProductoCategoria::where('id_categoria', $request->id_categoria)->delete();
        
        foreach ($request->productos_ids as $idProducto) {
            ProductoCategoria::create([
                'id_detalle_producto' => $idProducto,
                'id_categoria' => $request->id_categoria
            ]);
        }
        
        $categoria = CategoriaProducto::find($request->id_categoria);
        
        return redirect()->back()->with('success', 
            count($request->productos_ids) . " productos asignados a '{$categoria->nombre}'");
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