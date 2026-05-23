<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoVenta;
use App\Models\Gestion\Inventario\ProductoCategoria;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AsignarProductoCategoriaController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // 🔥 OBTENER TODOS LOS PRODUCTOS ACTIVOS del cliente
        $productos = ProductoVenta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0) // Solo activos
            ->with('categoria') // Cargar la categoría del producto
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto as id', 'Detalle as nombre', 'PrecioVenta', 'id_categoria']);
        
        // 🔥 OBTENER ASIGNACIONES ACTUALES (qué productos están habilitados en esta sucursal)
        $asignaciones = ProductoCategoria::where('id_sucursal', $sucursalId)
            ->pluck('id_detalle_producto')
            ->toArray();
        
        // 🔥 AGRUPAR PRODUCTOS POR CATEGORÍA (para mostrar visualmente)
        $categoriasConProductos = [];
        foreach ($productos as $producto) {
            $catId = $producto->id_categoria;
            $catNombre = $producto->categoria?->nombre ?? 'Sin categoría';
            
            if (!isset($categoriasConProductos[$catId])) {
                $categoriasConProductos[$catId] = [
                    'id' => $catId,
                    'nombre' => $catNombre,
                    'productos' => []
                ];
            }
            $categoriasConProductos[$catId]['productos'][] = $producto;
        }
        
        // Ordenar por nombre de categoría
        $categoriasConProductos = collect($categoriasConProductos)->sortBy('nombre')->values();
        
        return Inertia::render('Gestion/Inventario/AsignarProductoCategoria/Index', [
            'categoriasConProductos' => $categoriasConProductos,
            'productos' => $productos,
            'asignaciones' => $asignaciones,
            'sucursalId' => $sucursalId,
            'sucursalNombre' => session('cliente_sucursal_nombre'),
            'totalProductos' => $productos->count(),
            'totalHabilitados' => count($asignaciones),
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'productos_ids' => 'array',
            'productos_ids.*' => 'exists:inventario_relacion_ventainventario,IdDetalleProducto'
        ]);
        
        $sucursalId = session('cliente_sucursal_id');
        
        // 🔥 Eliminar todas las habilitaciones actuales de esta sucursal
        ProductoCategoria::where('id_sucursal', $sucursalId)->delete();
        
        // 🔥 Crear nuevas habilitaciones usando la categoría del producto
        foreach ($request->productos_ids as $idProducto) {
            $producto = ProductoVenta::find($idProducto);
            
            if ($producto && $producto->id_categoria) {
                ProductoCategoria::create([
                    'id_detalle_producto' => $idProducto,
                    'id_categoria' => $producto->id_categoria, // 🔥 USAR LA CATEGORÍA DEL PRODUCTO
                    'id_sucursal' => $sucursalId,
                ]);
            }
        }
        
        $sucursalNombre = session('cliente_sucursal_nombre');
        $cantidad = count($request->productos_ids);
        
        return redirect()->back()->with('success', 
            "{$cantidad} productos habilitados para la sucursal '{$sucursalNombre}'");
    }
}