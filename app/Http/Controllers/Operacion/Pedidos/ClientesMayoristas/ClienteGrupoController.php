<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ClienteGrupo;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClienteGrupoController extends Controller
{
    /**
     * ✅ LISTAR GRUPOS CON MÍNIMOS DE UN CLIENTE
     * 
     * Si recibe `id_contenedor`, solo devuelve los grupos de ESE contenedor.
     * Si no, devuelve todos los grupos del cliente.
     */
    public function index(Request $request, $identificadorId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $idContenedor = $request->get('id_contenedor');  // ✅ NUEVO

        try {
            // 1. Obtener los grupos (del contenedor específico o de todos)
            if ($idContenedor) {
                $gruposDelCliente = $this->obtenerGruposDeContenedor($idContenedor, $clienteId, $sucursalId);
            } else {
                $gruposDelCliente = $this->obtenerGruposDelCliente($identificadorId, $clienteId, $sucursalId);
            }

            // 2. Obtener los mínimos ya configurados
            $minimosConfigurados = ClienteGrupo::where('IdIdentificador', $identificadorId)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->get()
                ->keyBy('IdGrupoAnalisis');

            // 3. Combinar: grupos + mínimos
            $grupos = [];
            foreach ($gruposDelCliente as $grupo) {
                $minimo = $minimosConfigurados->get($grupo['IdGrupoAnalisis']);
                
                $grupos[] = [
                    'IdGrupoAnalisis' => $grupo['IdGrupoAnalisis'],
                    'NombreGrupo' => $grupo['NombreGrupo'],
                    'Contenedores' => $grupo['Contenedores'],
                    'CantidadMinimaGrupo' => $minimo ? $minimo->CantidadMinimaGrupo : null,
                    'Configurado' => $minimo ? true : false,
                    'IdClienteGrupo' => $minimo ? $minimo->IdClienteGrupo : null,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $grupos,
                'identificador_id' => $identificadorId,
                'id_contenedor' => $idContenedor,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en ClienteGrupoController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ GUARDAR MÍNIMOS DE UN CLIENTE
     */
    public function store(Request $request, $identificadorId)
    {
        $request->validate([
            'grupos' => 'required|array|min:1',
            'grupos.*.IdGrupoAnalisis' => 'required|exists:inventario_productogrupoanalisis,IdGrupoAnalisis',
            'grupos.*.CantidadMinimaGrupo' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            DB::beginTransaction();

            $guardados = 0;
            $actualizados = 0;

            foreach ($request->grupos as $grupo) {
                $existente = ClienteGrupo::where('IdIdentificador', $identificadorId)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdGrupoAnalisis', $grupo['IdGrupoAnalisis'])
                    ->first();

                if ($existente) {
                    $existente->update([
                        'CantidadMinimaGrupo' => $grupo['CantidadMinimaGrupo'],
                        'ActivoInactivo' => 1,
                        'IdOperadorActualiza' => $operadorId,
                        'FechaActualiza' => Carbon::now('America/La_Paz'),
                    ]);
                    $actualizados++;
                } else {
                    ClienteGrupo::create([
                        'IdIdentificador' => $identificadorId,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                        'IdGrupoAnalisis' => $grupo['IdGrupoAnalisis'],
                        'CantidadMinimaGrupo' => $grupo['CantidadMinimaGrupo'],
                        'ActivoInactivo' => 1,
                        'IdOperadorInserta' => $operadorId,
                        'FechaInserta' => Carbon::now('America/La_Paz'),
                    ]);
                    $guardados++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Mínimos guardados: {$guardados} nuevos, {$actualizados} actualizados",
                'guardados' => $guardados,
                'actualizados' => $actualizados,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en ClienteGrupoController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar mínimos: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ ACTUALIZAR UN MÍNIMO ESPECÍFICO
     */
    public function update(Request $request, $idClienteGrupo)
    {
        $request->validate([
            'CantidadMinimaGrupo' => 'required|numeric|min:0',
        ]);

        $operadorId = session('operador_id');

        try {
            $clienteGrupo = ClienteGrupo::findOrFail($idClienteGrupo);

            $clienteGrupo->update([
                'CantidadMinimaGrupo' => $request->CantidadMinimaGrupo,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mínimo actualizado correctamente',
                'data' => $clienteGrupo,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en ClienteGrupoController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ INACTIVAR UN MÍNIMO
     */
    public function destroy($idClienteGrupo)
    {
        $operadorId = session('operador_id');

        try {
            $clienteGrupo = ClienteGrupo::findOrFail($idClienteGrupo);

            $clienteGrupo->update([
                'ActivoInactivo' => 0,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Mínimo inactivado correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error en ClienteGrupoController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al inactivar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ OBTENER GRUPOS DE UN CONTENEDOR ESPECÍFICO
     */
    public function gruposDelCliente(Request $request, $identificadorId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $idContenedor = $request->get('id_contenedor');

        try {
            if ($idContenedor) {
                $grupos = $this->obtenerGruposDeContenedor($idContenedor, $clienteId, $sucursalId);
            } else {
                $grupos = $this->obtenerGruposDelCliente($identificadorId, $clienteId, $sucursalId);
            }

            $minimos = ClienteGrupo::where('IdIdentificador', $identificadorId)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->pluck('CantidadMinimaGrupo', 'IdGrupoAnalisis');

            $resultado = [];
            foreach ($grupos as $grupo) {
                $resultado[] = [
                    'IdGrupoAnalisis' => $grupo['IdGrupoAnalisis'],
                    'NombreGrupo' => $grupo['NombreGrupo'],
                    'Contenedores' => $grupo['Contenedores'],
                    'CantidadMinimaGrupo' => $minimos[$grupo['IdGrupoAnalisis']] ?? null,
                    'Configurado' => isset($minimos[$grupo['IdGrupoAnalisis']]),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en gruposDelCliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // MÉTODOS PRIVADOS
    // ============================================================

    /**
     * ✅ OBTENER LOS GRUPOS ÚNICOS DE UN CONTENEDOR ESPECÍFICO
     */
    private function obtenerGruposDeContenedor($idContenedor, $clienteId, $sucursalId)
    {
        $grupos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_grupo as cg')
            ->join('inventario_productogrupoanalisis as g', 'cg.IdGrupoAnalisis', '=', 'g.IdGrupoAnalisis')
            ->join('operacion_pedidos_clientes_contenedor as c', 'cg.IdContenedor', '=', 'c.IdContenedor')
            ->where('cg.IdContenedor', $idContenedor)
            ->select(
                'g.IdGrupoAnalisis',
                'g.Grupo as NombreGrupo',
                'c.Codigo as ContenedorCodigo'
            )
            ->orderBy('g.Grupo')
            ->get();

        $gruposUnicos = [];
        foreach ($grupos as $item) {
            if (!isset($gruposUnicos[$item->IdGrupoAnalisis])) {
                $gruposUnicos[$item->IdGrupoAnalisis] = [
                    'IdGrupoAnalisis' => $item->IdGrupoAnalisis,
                    'NombreGrupo' => $item->NombreGrupo,
                    'Contenedores' => [],
                ];
            }
            if (!in_array($item->ContenedorCodigo, $gruposUnicos[$item->IdGrupoAnalisis]['Contenedores'])) {
                $gruposUnicos[$item->IdGrupoAnalisis]['Contenedores'][] = $item->ContenedorCodigo;
            }
        }

        return array_values($gruposUnicos);
    }

    /**
     * ✅ OBTENER LOS GRUPOS ÚNICOS DE TODOS LOS CONTENEDORES DE UN CLIENTE
     */
    private function obtenerGruposDelCliente($identificadorId, $clienteId, $sucursalId)
    {
        $contenedoresIds = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_cliente')
            ->where('IdIdentificador', $identificadorId)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->pluck('IdContenedor')
            ->toArray();

        if (empty($contenedoresIds)) {
            return [];
        }

        $grupos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_contenedor_grupo as cg')
            ->join('inventario_productogrupoanalisis as g', 'cg.IdGrupoAnalisis', '=', 'g.IdGrupoAnalisis')
            ->join('operacion_pedidos_clientes_contenedor as c', 'cg.IdContenedor', '=', 'c.IdContenedor')
            ->whereIn('cg.IdContenedor', $contenedoresIds)
            ->select(
                'g.IdGrupoAnalisis',
                'g.Grupo as NombreGrupo',
                'c.Codigo as ContenedorCodigo'
            )
            ->orderBy('g.Grupo')
            ->get();

        $gruposUnicos = [];
        foreach ($grupos as $item) {
            if (!isset($gruposUnicos[$item->IdGrupoAnalisis])) {
                $gruposUnicos[$item->IdGrupoAnalisis] = [
                    'IdGrupoAnalisis' => $item->IdGrupoAnalisis,
                    'NombreGrupo' => $item->NombreGrupo,
                    'Contenedores' => [],
                ];
            }
            if (!in_array($item->ContenedorCodigo, $gruposUnicos[$item->IdGrupoAnalisis]['Contenedores'])) {
                $gruposUnicos[$item->IdGrupoAnalisis]['Contenedores'][] = $item->ContenedorCodigo;
            }
        }

        return array_values($gruposUnicos);
    }
}