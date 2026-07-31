<?php

namespace App\Http\Controllers\Operacion; // 🔥 CAMBIAR DE Gestion\Operacion a Operacion

use App\Http\Controllers\Controller;
use App\Models\Gestion\Operacion\FichaTecnica;
use App\Models\Gestion\Operacion\FichaTecnicaDetalle;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FichaTecnicaController extends Controller
{
    /**
     * Obtener la ficha técnica de un producto
     */
    public function getFichaByProducto($idProducto)
    {
        try {
            $clienteId = session('cliente_id');
            
            $ficha = FichaTecnica::where('IdProductoTerminado', $idProducto)
                ->where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 1)
                ->with(['detalles.insumo', 'detalles.unidadMedida', 'unidadMedida'])
                ->first();
            
            if (!$ficha) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este producto no tiene ficha técnica',
                    'existe' => false
                ]);
            }
            
            return response()->json([
                'success' => true,
                'existe' => true,
                'ficha' => $ficha,
                'detalles' => $ficha->detalles
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo ficha técnica: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ficha técnica'
            ], 500);
        }
    }

    /**
     * Obtener insumos disponibles (productos con estado "Insumos")
     */
    public function getInsumos(Request $request)
    {
        try {
            $clienteId = session('cliente_id');
            
            Log::info('🔍 Buscando insumos para cliente: ' . $clienteId);
            
            if (!$clienteId) {
                return response()->json([
                    'success' => true,
                    'insumos' => []
                ]);
            }
            
            // 🔥 Paso 1: Obtener el ID del estado "Insumos" para este cliente
            $estadoInsumos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_producto_estado')
                ->where('IdCliente', $clienteId)
                ->where('Estado', 'Insumos')
                ->first();
            
            Log::info('📊 Estado Insumos encontrado:', [
                'IdEstado' => $estadoInsumos->IdEstado ?? 'NO ENCONTRADO',
                'Estado' => $estadoInsumos->Estado ?? 'NO ENCONTRADO'
            ]);
            
            // Si no hay estado "Insumos" para este cliente, devolver vacío
            if (!$estadoInsumos) {
                Log::warning('⚠️ No se encontró el estado "Insumos" para el cliente ' . $clienteId);
                return response()->json([
                    'success' => true,
                    'insumos' => []
                ]);
            }
            
            // 🔥 Paso 2: Buscar productos con ese IdEstadoProducto
            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle as p')
                ->where('p.IdCliente', $clienteId)
                ->where('p.ActivoInactivo', 0)
                ->where('p.IdEstadoProducto', $estadoInsumos->IdEstado) // 🔥 FILTRO POR IDEstadoProducto
                ->select('p.IdProducto', 'p.Codigo', 'p.Descripcion');
            
            // 🔥 Paso 3: Aplicar búsqueda si existe
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('p.Codigo', 'like', "%{$search}%")
                        ->orWhere('p.Descripcion', 'like', "%{$search}%");
                });
            }
            
            $insumos = $query->orderBy('p.Codigo')
                ->limit(50)
                ->get();
            
            Log::info('✅ Insumos encontrados: ' . $insumos->count());
            
            return response()->json([
                'success' => true,
                'insumos' => $insumos
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Error obteniendo insumos: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener insumos: ' . $e->getMessage(),
                'insumos' => []
            ]);
        }
    }

    /**
     * Guardar o actualizar ficha técnica
     */
    public function guardar(Request $request)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');
            
            $request->validate([
                'IdProductoTerminado' => 'required|exists:inventario_productodetalle,IdProducto',
                'CantidadProduccion' => 'required|numeric|min:0.01',
                'IdUnidadMedidaProducto' => 'required|exists:inventario_unidadmedida,IdUnidadMedida',
                'detalles' => 'required|array|min:1',
                'detalles.*.IdProductoInsumo' => 'required|exists:inventario_productodetalle,IdProducto',
                'detalles.*.IdUnidadMedida' => 'required|exists:inventario_unidadmedida,IdUnidadMedida',
                'detalles.*.Unidades' => 'required|numeric|min:0.0001',
                'detalles.*.Orden' => 'nullable|integer|min:0',
            ]);
            
            DB::beginTransaction();
            
            // Verificar si ya existe una ficha activa para este producto
            $fichaExistente = FichaTecnica::where('IdProductoTerminado', $request->IdProductoTerminado)
                ->where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 1)
                ->first();
            
            // Si existe, la desactivamos (para mantener historial)
            if ($fichaExistente) {
                $fichaExistente->update([
                    'ActivoInactivo' => 0,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaEdita' => now(),
                ]);
            }
            
            // Crear nueva ficha
            $numeroCorrelativo = FichaTecnica::where('IdCliente', $clienteId)
                ->max('NumeroCorrelativo') ?? 0;
            
            $ficha = FichaTecnica::create([
                'NumeroCorrelativo' => $numeroCorrelativo + 1,
                'IdLineaProducto' => $request->IdLineaProducto ?? 1,
                'IdProductoTerminado' => $request->IdProductoTerminado,
                'CantidadProduccion' => $request->CantidadProduccion,
                'IdUnidadMedidaProducto' => $request->IdUnidadMedidaProducto,
                'FechaVigencia' => $request->FechaVigencia ?? null,
                'ActivoInactivo' => 1,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperadorIngresa' => $operadorId,
                'FechaIngreso' => now(),
                'IdOperadorActualiza' => $operadorId,
                'FechaEdita' => now(),
            ]);
            
            // Guardar detalles
            foreach ($request->detalles as $detalle) {
                FichaTecnicaDetalle::create([
                    'IdFicha' => $ficha->IdFicha,
                    'IdProductoInsumo' => $detalle['IdProductoInsumo'],
                    'IdUnidadMedida' => $detalle['IdUnidadMedida'],
                    'Orden' => $detalle['Orden'] ?? 0,
                    'Unidades' => $detalle['Unidades'],
                    'IdOperadorIngresa' => $operadorId,
                    'FechaIngreso' => now(),
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => now(),
                ]);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Ficha técnica guardada correctamente',
                'ficha' => $ficha->load(['detalles.insumo', 'detalles.unidadMedida', 'unidadMedida'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error guardando ficha técnica: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar ficha técnica (desactivar lógicamente)
     */
    public function destroy($id)
    {
        try {
            $clienteId = session('cliente_id');
            $operadorId = session('operador_id');
            
            $ficha = FichaTecnica::where('IdFicha', $id)
                ->where('IdCliente', $clienteId)
                ->firstOrFail();
            
            $ficha->update([
                'ActivoInactivo' => 0,
                'IdOperadorActualiza' => $operadorId,
                'FechaEdita' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Ficha técnica desactivada correctamente'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error eliminando ficha técnica: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si un producto tiene ficha técnica
     */
    public function verificar($idProducto)
    {
        try {
            $clienteId = session('cliente_id');
            
            $existe = FichaTecnica::where('IdProductoTerminado', $idProducto)
                ->where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 1)
                ->exists();
            
            return response()->json([
                'success' => true,
                'existe' => $existe
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'existe' => false
            ], 500);
        }
    }
}