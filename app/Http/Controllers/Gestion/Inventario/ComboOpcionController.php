<?php
// app/Http/Controllers/Gestion/Inventario/ComboOpcionController.php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ComboOpcion;
use App\Models\Gestion\Inventario\ProductoVenta;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\RelacionVentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComboOpcionController extends Controller
{
    /**
     * Obtener la composición fija del combo (de inventario_relacion_ventainventario_detalle)
     */
    public function getComposicion($idProductoCombo)
    {
        $clienteId = session('cliente_id');
        
        // Verificar que el producto existe y pertenece al cliente
        $combo = ProductoVenta::where('IdCliente', $clienteId)
            ->find($idProductoCombo);
        
        if (!$combo) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }
        
        // Obtener los productos de la composición fija
        $detalles = RelacionVentaDetalle::where('IdDetalleProducto', $idProductoCombo)
            ->with('producto')
            ->get();
        
        $composicion = [];
        foreach ($detalles as $detalle) {
            $composicion[] = [
                'id_producto' => $detalle->IdProducto,
                'nombre' => $detalle->producto?->Descripcion ?? 'Producto no encontrado',
                'codigo' => $detalle->producto?->Codigo ?? '',
                'porcion' => (float) $detalle->Porcion
            ];
        }
        
        return response()->json([
            'success' => true,
            'composicion' => $composicion
        ]);
    }
    
    /**
     * Obtener todas las opciones de un combo
     */
    public function index($idProductoCombo)
    {
        $clienteId = session('cliente_id');
        
        $combo = ProductoVenta::where('IdCliente', $clienteId)
            ->find($idProductoCombo);
        
        if (!$combo) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }
        
        $opciones = ComboOpcion::where('id_producto_combo', $idProductoCombo)
            ->where('activo', 1)
            ->with(['productoOriginal', 'productoSustituto'])
            ->orderBy('id_producto_original')
            ->orderBy('orden')
            ->get();
        
        $resultado = [];
        foreach ($opciones as $opcion) {
            $resultado[] = [
                'id_combo_opcion' => $opcion->id_combo_opcion,
                'id_producto_original' => $opcion->id_producto_original,
                'nombre_original' => $opcion->productoOriginal?->Descripcion ?? 'Producto',
                'id_producto_sustituto' => $opcion->id_producto_sustituto,
                'nombre_sustituto' => $opcion->productoSustituto?->Descripcion ?? 'Producto',
                'codigo_sustituto' => $opcion->productoSustituto?->Codigo ?? '',
                'orden' => $opcion->orden
            ];
        }
        
        return response()->json([
            'success' => true,
            'opciones' => $resultado
        ]);
    }
    
    /**
     * Buscar productos disponibles para ser opciones
     */
    public function getProductosDisponibles(Request $request)
    {
        $request->validate([
            'id_producto_original' => 'required|exists:inventario_productodetalle,IdProducto',
            'search' => 'nullable|string'
        ]);
        
        $clienteId = session('cliente_id');
        
        // Obtener el producto original para saber su grupo
        $productoOriginal = ProductoDetalle::where('IdCliente', $clienteId)
            ->find($request->id_producto_original);
        
        if (!$productoOriginal) {
            return response()->json([]);
        }
        
        $query = ProductoDetalle::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->where('IdProducto', '!=', $request->id_producto_original);
        
        // Filtrar por el mismo grupo de producto (opcional, ajusta según tu lógica)
        if ($productoOriginal->IdGrupoProducto) {
            $query->where('IdGrupoProducto', $productoOriginal->IdGrupoProducto);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Codigo', 'like', "%{$search}%")
                  ->orWhere('Descripcion', 'like', "%{$search}%");
            });
        }
        
        $productos = $query->orderBy('Descripcion')
            ->limit(20)
            ->get(['IdProducto as id', 'Codigo', 'Descripcion as nombre']);
        
        return response()->json($productos);
    }
    
    /**
     * Guardar nueva opción
     */
    public function store(Request $request)
    {
        $request->validate([
            'id_producto_combo' => 'required|exists:inventario_relacion_ventainventario,IdDetalleProducto',
            'id_producto_original' => 'required|exists:inventario_productodetalle,IdProducto',
            'id_producto_sustituto' => 'required|exists:inventario_productodetalle,IdProducto',
            'orden' => 'nullable|integer|min:0',
            'es_default' => 'nullable|boolean',
        ]);
        
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        // Verificar que el combo pertenece al cliente
        $combo = ProductoVenta::where('IdCliente', $clienteId)
            ->find($request->id_producto_combo);
        
        if (!$combo) {
            return response()->json([
                'success' => false,
                'message' => 'El combo no existe o no pertenece a su empresa'
            ], 404);
        }
        
        // Verificar que el producto original está en la composición del combo
        $existeEnComposicion = RelacionVentaDetalle::where('IdDetalleProducto', $request->id_producto_combo)
            ->where('IdProducto', $request->id_producto_original)
            ->exists();
        
        if (!$existeEnComposicion) {
            return response()->json([
                'success' => false,
                'message' => 'El producto original no está en la composición del combo'
            ], 422);
        }
        
        // Verificar que no exista duplicado (mismo combo, mismo original, mismo sustituto)
        $existe = ComboOpcion::where('id_producto_combo', $request->id_producto_combo)
            ->where('id_producto_original', $request->id_producto_original)
            ->where('id_producto_sustituto', $request->id_producto_sustituto)
            ->exists();
        
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Esta opción ya existe para este combo'
            ], 422);
        }
        
        try {
            $opcion = ComboOpcion::create([
                'id_producto_combo' => $request->id_producto_combo,
                'id_producto_original' => $request->id_producto_original,
                'id_producto_sustituto' => $request->id_producto_sustituto,
                'orden' => $request->orden ?? 0,
                'es_default' => $request->es_default ?? 0,
                'activo' => 1,
                'id_operador_inserta' => $operadorId,
                'fecha_inserta' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Opción agregada correctamente',
                'opcion' => $opcion
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al guardar opción de combo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar opción (orden, activo)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'orden' => 'nullable|integer|min:0',
            'es_default' => 'nullable|boolean',
            'activo' => 'nullable|boolean',
        ]);
        
        $opcion = ComboOpcion::find($id);
        
        if (!$opcion) {
            return response()->json([
                'success' => false,
                'message' => 'Opción no encontrada'
            ], 404);
        }
        
        try {
            $updateData = [];
            if ($request->has('orden')) $updateData['orden'] = $request->orden;
            if ($request->has('es_default')) $updateData['es_default'] = $request->es_default;
            if ($request->has('activo')) $updateData['activo'] = $request->activo;
            
            $updateData['id_operador_actualiza'] = session('operador_id');
            $updateData['fecha_actualiza'] = now();
            
            $opcion->update($updateData);
            
            return response()->json([
                'success' => true,
                'message' => 'Opción actualizada correctamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al actualizar opción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar opción
     */
    public function destroy($id)
    {
        $opcion = ComboOpcion::find($id);
        
        if (!$opcion) {
            return response()->json([
                'success' => false,
                'message' => 'Opción no encontrada'
            ], 404);
        }
        
        try {
            $opcion->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Opción eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al eliminar opción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}