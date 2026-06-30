<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;
use App\Models\Gestion\Inventario\ProductoLinea;
use App\Models\Gestion\Inventario\ProductoEstado;
use App\Models\Gestion\Inventario\UnidadMedida;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductoDetalleController extends Controller
{
    /**
     * Listado de productos (GRID) - Usando JOIN para asegurar datos
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // 🔥 Usando JOIN para obtener los datos directamente
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle as p')
            ->leftJoin('inventario_productogrupoanalisis as g', 'p.IdGrupoAnalisis', '=', 'g.IdGrupoAnalisis')
            ->leftJoin('inventario_producto_linea as l', 'p.IdLineaProducto', '=', 'l.IdLinea')
            ->leftJoin('inventario_producto_estado as e', 'p.IdEstadoProducto', '=', 'e.IdEstado')
            ->leftJoin('inventario_unidadmedida as u', 'p.IdUnidadMedida', '=', 'u.IdUnidadMedida')
            ->where('p.IdCliente', $clienteId)
            ->select(
                'p.IdProducto',
                'p.Codigo',
                'p.Descripcion',
                'p.ActivoInactivo',
                'p.IdGrupoAnalisis',
                'p.IdLineaProducto',
                'p.IdEstadoProducto',
                'p.IdUnidadMedida',
                'g.Grupo as grupo_nombre',
                'l.Linea as linea_nombre',
                'e.Estado as estado_nombre',
                'u.UnidadMedida as unidad_nombre'
            );
        
        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('p.Codigo', 'like', "%{$search}%")
                    ->orWhere('p.Descripcion', 'like', "%{$search}%");
            });
        }
        
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('p.ActivoInactivo', $request->estado);
        }
        
        if ($request->filled('linea')) {
            $query->where('p.IdLineaProducto', $request->linea);
        }
        
        if ($request->filled('grupo')) {
            $query->where('p.IdGrupoAnalisis', $request->grupo);
        }
        
        // 🔥 Obtener datos con paginación
        $productosData = $query->orderBy('p.Codigo')->paginate(20);
        
        // 🔥 Convertir a formato estándar para el frontend
        $productos = new \stdClass();
        $productos->data = [];
        
        foreach ($productosData as $item) {
            $productos->data[] = (object) [
                'IdProducto' => $item->IdProducto,
                'Codigo' => $item->Codigo,
                'Descripcion' => $item->Descripcion,
                'ActivoInactivo' => $item->ActivoInactivo,
                'grupoAnalisis' => (object) [
                    'Grupo' => $item->grupo_nombre ?? '-'
                ],
                'linea' => (object) [
                    'Linea' => $item->linea_nombre ?? '-'
                ],
                'estado' => (object) [
                    'Estado' => $item->estado_nombre ?? '-'
                ],
                'unidadMedida' => (object) [
                    'UnidadMedida' => $item->unidad_nombre ?? '-'
                ]
            ];
        }
        
        // 🔥 Mantener la paginación
        $productos->links = $productosData->links();
        $productos->currentPage = $productosData->currentPage();
        $productos->lastPage = $productosData->lastPage();
        $productos->from = $productosData->firstItem();
        $productos->to = $productosData->lastItem();
        $productos->total = $productosData->total();
        $productos->perPage = $productosData->perPage();
        $productos->path = $productosData->path();
        
        // Estadísticas
        $totalActivos = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->count();
        
        $totalInactivos = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->count();
        
        // Catálogos para SELECT
        $grupos = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
            ->orderBy('IdGrupoAnalisis')
            ->get(['IdGrupoAnalisis as id', 'Grupo as nombre']);
        
        $lineas = ProductoLinea::where('IdCliente', $clienteId)
            ->orderBy('Linea')
            ->get(['IdLinea as id', 'Linea as nombre']);
        
        $estados = ProductoEstado::where('IdCliente', $clienteId)
            ->orderBy('Estado')
            ->get(['IdEstado as id', 'Estado as nombre']);
        
        $unidades = UnidadMedida::orderBy('IdUnidadMedida')
            ->get(['IdUnidadMedida as id', 'UnidadMedida as nombre']);
        
        // 🔥 Buscar el ID de la unidad "Unidad"
        $unidadId = null;
        $unidad = $unidades->firstWhere('nombre', 'Unidad');
        if ($unidad) {
            $unidadId = $unidad->id;
        }
        
        return Inertia::render('Gestion/Inventario/ProductoDetalle/Index', [
            'productos' => $productos,
            'totalActivos' => $totalActivos,
            'totalInactivos' => $totalInactivos,
            'grupos' => $grupos,
            'lineas' => $lineas,
            'estados' => $estados,
            'unidades' => $unidades,
            'unidadId' => $unidadId,
            'filtros' => [
                'search' => $request->search,
                'estado' => $request->estado,
                'linea' => $request->linea,
                'grupo' => $request->grupo,
            ],
        ]);
    }

    /**
     * Store - Crear producto
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $request->validate([
            'IdGrupoAnalisis' => 'required|exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
            'IdLineaProducto' => 'required|exists:inventario_producto_linea,IdLinea',
            'IdEstadoProducto' => 'required|exists:inventario_producto_estado,IdEstado',
            'IdUnidadMedida' => 'required|exists:inventario_unidadmedida,IdUnidadMedida',
            'Codigo' => 'required|string|max:200|unique:inventario_productodetalle,Codigo,NULL,IdProducto,IdCliente,' . $clienteId,
            'Descripcion' => 'required|string|max:200|unique:inventario_productodetalle,Descripcion,NULL,IdProducto,IdCliente,' . $clienteId,
            'OrdenInformes' => 'nullable|integer|min:0',
            'ActivoInactivo' => 'nullable|boolean',
        ]);

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $producto = ProductoDetalle::create([
                'IdGrupoAnalisis' => $request->IdGrupoAnalisis,
                'IdLineaProducto' => $request->IdLineaProducto,
                'IdEstadoProducto' => $request->IdEstadoProducto,
                'IdUnidadMedida' => $request->IdUnidadMedida,
                'OrdenInformes' => $request->OrdenInformes ?? 0,
                'Codigo' => $request->Codigo,
                'Descripcion' => $request->Descripcion,
                'Precio' => 0,
                'ActivoInactivo' => $request->ActivoInactivo ?? 0,
                'CkeckListRuta' => 0,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
                'CierrePermanente' => 0,
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto creado correctamente',
                'producto' => $producto
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al crear producto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update - Actualizar producto
     */
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $producto = ProductoDetalle::where('IdCliente', $clienteId)->findOrFail($id);

        $request->validate([
            'IdGrupoAnalisis' => 'required|exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
            'IdLineaProducto' => 'required|exists:inventario_producto_linea,IdLinea',
            'IdEstadoProducto' => 'required|exists:inventario_producto_estado,IdEstado',
            'IdUnidadMedida' => 'required|exists:inventario_unidadmedida,IdUnidadMedida',
            'Codigo' => 'required|string|max:200|unique:inventario_productodetalle,Codigo,' . $id . ',IdProducto,IdCliente,' . $clienteId,
            'Descripcion' => 'required|string|max:200|unique:inventario_productodetalle,Descripcion,' . $id . ',IdProducto,IdCliente,' . $clienteId,
            'OrdenInformes' => 'nullable|integer|min:0',
            'ActivoInactivo' => 'nullable|boolean',
        ]);

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $producto->update([
                'IdGrupoAnalisis' => $request->IdGrupoAnalisis,
                'IdLineaProducto' => $request->IdLineaProducto,
                'IdEstadoProducto' => $request->IdEstadoProducto,
                'IdUnidadMedida' => $request->IdUnidadMedida,
                'OrdenInformes' => $request->OrdenInformes ?? 0,
                'Codigo' => $request->Codigo,
                'Descripcion' => $request->Descripcion,
                'ActivoInactivo' => $request->ActivoInactivo ?? 0,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado correctamente',
                'producto' => $producto
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al actualizar producto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Destroy - Eliminar producto
     */
    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoDetalle::where('IdCliente', $clienteId)->findOrFail($id);

        try {
            $producto->delete();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX: Validar si el código ya existe
     */
    public function validarCodigo(Request $request)
    {
        $clienteId = session('cliente_id');
        $codigo = $request->codigo;
        $id = $request->id;
        
        $query = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('Codigo', $codigo);
        
        if ($id) {
            $query->where('IdProducto', '!=', $id);
        }
        
        $existe = $query->exists();
        
        return response()->json([
            'existe' => $existe,
            'message' => $existe ? '¡El código ya existe para este cliente!' : null
        ]);
    }

    /**
     * AJAX: Validar si la descripción ya existe
     */
    public function validarDescripcion(Request $request)
    {
        $clienteId = session('cliente_id');
        $descripcion = $request->descripcion;
        $id = $request->id;
        
        $query = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('Descripcion', $descripcion);
        
        if ($id) {
            $query->where('IdProducto', '!=', $id);
        }
        
        $existe = $query->exists();
        
        return response()->json([
            'existe' => $existe,
            'message' => $existe ? '¡La descripción ya existe para este cliente!' : null
        ]);
    }

    /**
     * Cambiar estado (Activar/Desactivar)
     */
    public function toggleEstado($id)
    {
        $clienteId = session('cliente_id');
        $producto = ProductoDetalle::where('IdCliente', $clienteId)->findOrFail($id);

        try {
            $nuevoEstado = $producto->ActivoInactivo == 0 ? 1 : 0;
            $producto->update([
                'ActivoInactivo' => $nuevoEstado,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => $nuevoEstado == 0 ? 'Producto activado' : 'Producto desactivado',
                'nuevo_estado' => $nuevoEstado
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API para obtener productos (selectores)
     */
    public function getProductos(Request $request)
    {
        $clienteId = session('cliente_id');
        
        $query = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0);
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                    ->orWhere('Descripcion', 'like', "%{$search}%");
            });
        }
        
        $productos = $query->orderBy('Codigo')
            ->limit(50)
            ->get(['IdProducto as id', 'Codigo', 'Descripcion']);
        
        return response()->json($productos);
    }
}