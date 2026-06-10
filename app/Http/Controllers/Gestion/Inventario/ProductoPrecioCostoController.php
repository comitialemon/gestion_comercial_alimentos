<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\ProductoLinea;
use App\Models\Gestion\Inventario\ProductoEstado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ProductoPrecioCostoController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // Query base de productos
        $query = ProductoDetalle::where('IdCliente', $clienteId)
            ->with(['linea', 'unidadMedida', 'estado']);
        
        // Filtro por estado (ActivoInactivo)
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }
        
        // Filtro por línea
        if ($request->filled('linea_id')) {
            $query->where('IdLineaProducto', $request->linea_id);
        }
        
        // Búsqueda por código o descripción
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                  ->orWhere('Descripcion', 'like', "%{$search}%");
            });
        }
        
        // Ordenar por descripción
        $productos = $query->orderBy('Descripcion')
            ->paginate(20)
            ->withQueryString();
        
        // 🔥 AGREGAR EL ÚLTIMO PRECIO COSTO A CADA PRODUCTO
        foreach ($productos as $producto) {
            // Obtener el último precio costo
            $ultimoPrecio = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle_precio_costo')
                ->where('IdProducto', $producto->IdProducto)
                ->where('IdCliente', $clienteId)
                ->orderBy('IdPrecioCosto', 'desc')
                ->first();
            
            $producto->ultimo_precio_costo = $ultimoPrecio ? (float) $ultimoPrecio->PrecioCosto : 0;
            $producto->ultima_fecha_precio = $ultimoPrecio ? $ultimoPrecio->IdFecha : null;
            
            // Precio de lista (el Precio del producto)
            $producto->precio_lista = (float) ($producto->Precio ?? 0);
        }
        
        // 🔥 ESTADÍSTICAS
        $totalActivos = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->count();
        
        $totalInactivos = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->count();
        
        // 🔥 DATOS PARA FILTROS (SOLO LÍNEAS, sin grupos)
        $lineas = ProductoLinea::porContexto()
            ->orderBy('Linea')
            ->get(['IdLinea as id', 'Linea as nombre']);
        
        // 🔥 SIN grupos porque ProductoGrupo ya no existe
        $grupos = []; // Array vacío
        
        return Inertia::render('Gestion/Inventario/ProductoPrecioCosto/Index', [
            'productos' => $productos,
            'totalActivos' => $totalActivos,
            'totalInactivos' => $totalInactivos,
            'lineas' => $lineas,
            'grupos' => $grupos,  // Vacío
            'filtros' => [
                'estado' => $request->estado,
                'linea_id' => $request->linea_id,
                'search' => $request->search,
            ],
        ]);
    }
    
    /**
     * Ver historial de precios costo de un producto
     */
    public function historial($id)
    {
        $clienteId = session('cliente_id');
        
        $producto = ProductoDetalle::where('IdCliente', $clienteId)
            ->findOrFail($id);
        
        $historial = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle_precio_costo as pc')
            ->join('todos_fecha as f', 'pc.IdFecha', '=', 'f.IdFecha')
            ->where('pc.IdProducto', $id)
            ->where('pc.IdCliente', $clienteId)
            ->orderBy('pc.IdPrecioCosto', 'desc')
            ->select(
                'pc.IdPrecioCosto',
                'pc.PrecioCosto',
                'f.Fecha',
                'pc.IdOperador'
            )
            ->get();
        
        return Inertia::render('Gestion/Inventario/ProductoPrecioCosto/Historial', [
            'producto' => $producto,
            'historial' => $historial,
        ]);
    }
}