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
     * Vista principal: configuración de qué grupos de análisis aplican para pedidos.
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $idsActivos = GrupoAnalisisMinimo::obtenerIdsActivos($clienteId, $sucursalId);
        $idsActivos = array_map('intval', $idsActivos);

        $grupos = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
            ->orderBy('Grupo')
            ->get(['IdGrupoAnalisis', 'Grupo']);

        $estructura = $grupos->map(function ($grupo) use ($idsActivos, $clienteId) {
            $totalProductos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->where('IdCliente', $clienteId)
                ->where('IdGrupoAnalisis', $grupo->IdGrupoAnalisis)
                ->where('ActivoInactivo', 0)
                ->count();

            return [
                'IdGrupoAnalisis' => $grupo->IdGrupoAnalisis,
                'NombreGrupo' => $grupo->Grupo,
                'Aplica' => in_array((int) $grupo->IdGrupoAnalisis, $idsActivos), // ✅ BOOL
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
            $idsActivos = GrupoAnalisisMinimo::obtenerIdsActivos($clienteId, $sucursalId);
            $idsActivos = array_map('intval', $idsActivos);

            $grupos = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
                ->orderBy('Grupo')
                ->get(['IdGrupoAnalisis', 'Grupo']);

            $estructura = $grupos->map(function ($grupo) use ($idsActivos, $clienteId) {
                $totalProductos = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdCliente', $clienteId)
                    ->where('IdGrupoAnalisis', $grupo->IdGrupoAnalisis)
                    ->where('ActivoInactivo', 0)
                    ->count();

                return [
                    'IdGrupoAnalisis' => $grupo->IdGrupoAnalisis,
                    'NombreGrupo' => $grupo->Grupo,
                    'Aplica' => in_array((int) $grupo->IdGrupoAnalisis, $idsActivos),
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
     * Guardar qué grupos aplican (ON/OFF).
     * Recibe: { grupos: [{ IdGrupoAnalisis, Aplica: true|false }, ...] }
     */
    public function guardar(Request $request)
    {
        $request->validate([
            'grupos' => 'required|array',
            'grupos.*.IdGrupoAnalisis' => 'required|integer|exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
            'grupos.*.Aplica' => 'required|boolean',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $ahora = Carbon::now('America/La_Paz');

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $aActivar = [];  // upsert
            $aDesactivar = []; // delete

            foreach ($request->grupos as $g) {
                if ($g['Aplica']) {
                    $aActivar[] = [
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                        'IdGrupoAnalisis' => $g['IdGrupoAnalisis'],
                        'CantidadMinimaGrupo' => 0, // ✅ Ya no se usa
                        'ActivoInactivo' => 1,
                        'IdOperadorInserta' => $operadorId,
                        'FechaInserta' => $ahora,
                        'IdOperadorActualiza' => $operadorId,
                        'FechaActualiza' => $ahora,
                    ];
                } else {
                    $aDesactivar[] = $g['IdGrupoAnalisis'];
                }
            }

            // Upsert de los activos
            if (!empty($aActivar)) {
                GrupoAnalisisMinimo::upsert(
                    $aActivar,
                    ['IdCliente', 'IdSucursal', 'IdGrupoAnalisis'],
                    ['ActivoInactivo', 'IdOperadorActualiza', 'FechaActualiza']
                );
            }

            // Eliminar los que se desactivaron
            if (!empty($aDesactivar)) {
                GrupoAnalisisMinimo::porContexto($clienteId, $sucursalId)
                    ->whereIn('IdGrupoAnalisis', $aDesactivar)
                    ->delete();
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            GrupoAnalisisMinimo::invalidarCache($clienteId, $sucursalId);

            return response()->json([
                'success' => true,
                'message' => 'Configuración guardada correctamente',
                'resumen' => [
                    'activados' => count($aActivar),
                    'desactivados' => count($aDesactivar),
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
     * Limpiar TODOS los grupos activos.
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
                'message' => "Se desactivaron {$eliminados} grupos",
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