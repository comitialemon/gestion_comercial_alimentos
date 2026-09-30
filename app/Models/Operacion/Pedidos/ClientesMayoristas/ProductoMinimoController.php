<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ProductoMinimo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoAnalisisMinimo;
use App\Models\Gestion\Inventario\ProductoDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProductoMinimoController extends Controller
{
    /**
     * Guardar (upsert) el mínimo de UN producto.
     * Se llama después de crear/editar el producto.
     */
    public function guardar(Request $request)
    {
        $request->validate([
            'IdProducto' => 'required|integer|exists:inventario_productodetalle,IdProducto',
            'IdGrupoAnalisis' => 'required|integer',
            'CantidadMinimaProducto' => 'required|numeric|min:0.01',
            'DisponibleParaPedido' => 'nullable|boolean',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            // Verificar que el producto pertenezca al cliente
            $producto = ProductoDetalle::where('IdCliente', $clienteId)
                ->where('IdProducto', $request->IdProducto)
                ->firstOrFail();

            ProductoMinimo::updateOrCreate(
                [
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'IdProducto' => $request->IdProducto,
                ],
                [
                    'IdGrupoAnalisis' => $request->IdGrupoAnalisis,
                    'CantidadMinimaProducto' => $request->CantidadMinimaProducto,
                    'DisponibleParaPedido' => $request->DisponibleParaPedido ?? 1,
                    'ActivoInactivo' => 1,
                    'IdOperadorInserta' => $operadorId,
                    'FechaInserta' => Carbon::now('America/La_Paz'),
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => Carbon::now('America/La_Paz'),
                ]
            );

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            // Invalidar caché
            ProductoMinimo::invalidarCache($clienteId, $sucursalId);

            return response()->json([
                'success' => true,
                'message' => 'Mínimo configurado correctamente',
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al guardar mínimo de producto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar mínimo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar (borrado lógico) el mínimo de UN producto.
     */
    public function eliminar(Request $request)
    {
        $request->validate([
            'IdProducto' => 'required|integer',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            $deleted = ProductoMinimo::porContexto($clienteId, $sucursalId)
                ->where('IdProducto', $request->IdProducto)
                ->update([
                    'ActivoInactivo' => 0,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => Carbon::now('America/La_Paz'),
                ]);

            ProductoMinimo::invalidarCache($clienteId, $sucursalId);

            return response()->json([
                'success' => true,
                'message' => $deleted ? 'Mínimo eliminado' : 'No había mínimo configurado',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar mínimo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle de disponibilidad (pausar/reanudar) de UN producto.
     */
    public function toggleDisponible($idProducto)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            $minimo = ProductoMinimo::porContexto($clienteId, $sucursalId)
                ->where('IdProducto', $idProducto)
                ->where('ActivoInactivo', 1)
                ->firstOrFail();

            $nuevoEstado = $minimo->DisponibleParaPedido == 1 ? 0 : 1;

            $minimo->update([
                'DisponibleParaPedido' => $nuevoEstado,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            ProductoMinimo::invalidarCache($clienteId, $sucursalId);

            return response()->json([
                'success' => true,
                'message' => $nuevoEstado == 1 ? 'Producto disponible' : 'Producto pausado',
                'DisponibleParaPedido' => $nuevoEstado,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en toggleDisponible: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar disponibilidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el mínimo actual de UN producto.
     */
    public function show($idProducto)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $minimo = ProductoMinimo::porContexto($clienteId, $sucursalId)
                ->where('IdProducto', $idProducto)
                ->where('ActivoInactivo', 1)
                ->first(['CantidadMinimaProducto', 'DisponibleParaPedido', 'IdGrupoAnalisis']);

            return response()->json([
                'success' => true,
                'data' => $minimo ? [
                    'CantidadMinimaProducto' => (float) $minimo->CantidadMinimaProducto,
                    'DisponibleParaPedido' => (int) $minimo->DisponibleParaPedido,
                    'IdGrupoAnalisis' => (int) $minimo->IdGrupoAnalisis,
                ] : null,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener mínimo: ' . $e->getMessage()
            ], 500);
        }
    }
}