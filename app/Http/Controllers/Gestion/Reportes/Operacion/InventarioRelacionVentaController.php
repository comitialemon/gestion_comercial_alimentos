<?php

namespace App\Http\Controllers\Gestion\Reportes\Operacion;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventarioRelacionVentaController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        $search = $request->input('search', '');
        $estado = $request->input('estado', '');
        $categorias = $request->input('categorias', '');
        
        // Convertir categorías a array
        $categoriasArray = [];
        if (!empty($categorias)) {
            $categoriasArray = array_map('intval', explode(',', $categorias));
        }
        
        // =============================================
        // CONSULTA PRINCIPAL (IGUAL AL EJEMPLO)
        // =============================================
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario as irv')
            ->select(
                'irv.IdDetalleProducto',
                'irv.id_categoria',
                'irv.Codigo',
                'irv.Detalle',
                'irv.PrecioVenta',
                'irv.IdCliente',
                'irv.ActivoInactivo',
                'irv.estado_aprobacion'
            )
            ->where('irv.IdCliente', $clienteId);
        
        // =============================================
        // APLICAR FILTROS (IGUAL AL EJEMPLO)
        // =============================================
        
        // Filtro de estado (igual al ejemplo)
        if ($estado !== '' && $estado !== null) {
            $query->where('irv.ActivoInactivo', $estado);
        }
        
        // Filtro de categorías (igual al ejemplo - usando id_categoria directamente)
        if (!empty($categoriasArray)) {
            $query->whereIn('irv.id_categoria', $categoriasArray);
        }
        
        // Filtro de búsqueda (igual al ejemplo)
        if (!empty($search)) {
            $searchTerm = $search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('irv.Codigo', 'like', "%{$searchTerm}%")
                  ->orWhere('irv.Detalle', 'like', "%{$searchTerm}%");
            });
        }
        
        // =============================================
        // OBTENER PRODUCTOS
        // =============================================
        $productos = $query->orderBy('irv.Detalle')
            ->paginate(10)
            ->withQueryString();
        
        // =============================================
        // OBTENER DATOS ADICIONALES
        // =============================================
        $productos->getCollection()->transform(function ($item) use ($clienteId) {
            
            // 1. Categoría
            $categoria = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_menu_categoria')
                ->where('id_categoria', $item->id_categoria)
                ->value('nombre');
            
            $item->categoria_nombre = $categoria ?? 'Sin categoría';
            
            // 2. Composición
            $composicion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle as detalle')
                ->join('inventario_productodetalle as producto', 
                        'detalle.IdProducto', '=', 'producto.IdProducto')
                ->where('detalle.IdDetalleProducto', $item->IdDetalleProducto)
                ->where('producto.IdCliente', $clienteId)
                ->select(
                    'producto.Descripcion as producto_detalle',
                    'producto.Codigo as producto_codigo',
                    'detalle.Porcion'
                )
                ->get();
            
            $item->composicion = $composicion;
            $item->tiene_composicion = $composicion->isNotEmpty();
            
            return $item;
        });
        
        // =============================================
        // CATEGORÍAS PARA FILTROS (IGUAL AL EJEMPLO)
        // =============================================
        $categorias = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_menu_categoria')
            ->select(
                'id_categoria as id',
                'nombre as nombre'
            )
            ->where('id_cliente', $clienteId)
            ->where('activo', 1)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(function($categoria) use ($clienteId, $search, $estado, $categoriasArray) {
                // Contar productos de esta categoría (igual al ejemplo)
                $query = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario')
                    ->where('IdCliente', $clienteId)
                    ->where('id_categoria', $categoria->id);
                
                if (!empty($search)) {
                    $query->where(function($q) use ($search) {
                        $q->where('Codigo', 'like', "%{$search}%")
                          ->orWhere('Detalle', 'like', "%{$search}%");
                    });
                }
                
                if ($estado !== '' && $estado !== null) {
                    $query->where('ActivoInactivo', $estado);
                }
                
                $categoria->productos_count = $query->count();
                return $categoria;
            });
        
        // =============================================
        // CONTADORES DE ESTADO
        // =============================================
        $totalActivos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->count();
        
        $totalInactivos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->count();
        
        // =============================================
        // RETORNAR VISTA
        // =============================================
        return Inertia::render('Gestion/Reportes/Operacion/InventarioRelacionVenta', [
            'productos' => $productos,
            'categorias' => $categorias,
            'totalActivos' => $totalActivos,
            'totalInactivos' => $totalInactivos,
            'filtros' => [
                'search' => $search,
                'estado' => $estado,
                'categorias' => !empty($categorias) ? $categorias : '',
            ],
        ]);
    }
}