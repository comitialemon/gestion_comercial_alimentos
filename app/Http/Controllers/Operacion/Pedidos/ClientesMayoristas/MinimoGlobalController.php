<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoAnalisisMinimo;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MinimoGlobalController extends Controller
{
    /**
     * Vista principal: configuración de mínimos por GRUPO de análisis.
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        // Mapa de mínimos ya configurados
        $mapaGrupos = GrupoAnalisisMinimo::obtenerMapa($clienteId, $sucursalId);

        // Grupos de análisis del cliente
        $grupos = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
            ->orderBy('Grupo')
            ->get(['IdGrupoAnalisis', 'Grupo']);

        // Estructura simple: grupos + su mínimo actual + total productos
        $estructura = $grupos->map(function ($grupo) use ($mapaGrupos, $clienteId) {
            $totalProductos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->where('IdCliente', $clienteId)
                ->where('IdGrupoAnalisis', $grupo->IdGrupoAnalisis)
                ->where('ActivoInactivo', 0)
                ->count();

            return [
                'IdGrupoAnalisis' => $grupo->IdGrupoAnalisis,
                'NombreGrupo' => $grupo->Grupo,
                'CantidadMinimaGrupo' => $mapaGrupos[$grupo->IdGrupoAnalisis] ?? 0,
                'TotalProductos' => $totalProductos,
            ];
        })->values();

        return Inertia::render('Operacion/ClientesMayoristas/MinimosGlobales/Index', [
            'estructura' => $estructura,
        ]);
    }

    /**
     * API: Obtener estructura actualizada.
     */
    public function getEstructura()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $mapaGrupos = GrupoAnalisisMinimo::obtenerMapa($clienteId, $sucursalId);

            $grupos = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
                ->orderBy('Grupo')
                ->get(['IdGrupoAnalisis', 'Grupo']);

            $estructura = $grupos->map(function ($grupo) use ($mapaGrupos, $clienteId) {
                $totalProductos = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdCliente', $clienteId)
                    ->where('IdGrupoAnalisis', $grupo->IdGrupoAnalisis)
                    ->where('ActivoInactivo', 0)
                    ->count();

                return [
                    'IdGrupoAnalisis' => $grupo->IdGrupoAnalisis,
                    'NombreGrupo' => $grupo->Grupo,
                    'CantidadMinimaGrupo' => $mapaGrupos[$grupo->IdGrupoAnalisis] ?? 0,
                    'TotalProductos' => $totalProductos,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $estructura,
            ]);

        } catch (\Exception $e) {
            Log::error('Error getEstructura: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estructura: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar mínimos de GRUPOS (bulk upsert).
     */
    public function guardar(Request $request)
    {
        $request->validate([
            'grupos' => 'required|array',
            'grupos.*.IdGrupoAnalisis' => 'required|integer|exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
            'grupos.*.CantidadMinima' => 'nullable|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $ahora = Carbon::now('America/La_Paz');

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            // Separar los que se guardan (>0) y los que se eliminan (=0)
            $aGuardar = [];
            $aEliminar = [];

            foreach ($request->grupos as $g) {
                $cantidad = (float) ($g['CantidadMinima'] ?? 0);

                if ($cantidad > 0) {
                    $aGuardar[] = [
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                        'IdGrupoAnalisis' => $g['IdGrupoAnalisis'],
                        'CantidadMinimaGrupo' => $cantidad,
                        'ActivoInactivo' => 1,
                        'IdOperadorInserta' => $operadorId,
                        'FechaInserta' => $ahora,
                        'IdOperadorActualiza' => $operadorId,
                        'FechaActualiza' => $ahora,
                    ];
                } else {
                    $aEliminar[] = $g['IdGrupoAnalisis'];
                }
            }

            // Bulk upsert (1 sola query)
            if (!empty($aGuardar)) {
                GrupoAnalisisMinimo::upsert(
                    $aGuardar,
                    ['IdCliente', 'IdSucursal', 'IdGrupoAnalisis'],
                    ['CantidadMinimaGrupo', 'ActivoInactivo', 'IdOperadorActualiza', 'FechaActualiza']
                );
            }

            // Eliminar (borrado físico) los que quedaron en 0
            if (!empty($aEliminar)) {
                GrupoAnalisisMinimo::porContexto($clienteId, $sucursalId)
                    ->whereIn('IdGrupoAnalisis', $aEliminar)
                    ->delete();
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            // Invalidar caché
            GrupoAnalisisMinimo::invalidarCache($clienteId, $sucursalId);

            return response()->json([
                'success' => true,
                'message' => 'Mínimos de grupos guardados correctamente',
                'resumen' => [
                    'guardados' => count($aGuardar),
                    'eliminados' => count($aEliminar),
                ],
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error guardar mínimos grupos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar TODOS los mínimos de grupos.
     */
    public function limpiarTodo()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $eliminados = GrupoAnalisisMinimo::porContexto($clienteId, $sucursalId)->delete();

            GrupoAnalisisMinimo::invalidarCache($clienteId, $sucursalId);

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$eliminados} grupos",
            ]);

        } catch (\Exception $e) {
            Log::error('Error limpiarTodo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar: ' . $e->getMessage()
            ], 500);
        }
    }
}