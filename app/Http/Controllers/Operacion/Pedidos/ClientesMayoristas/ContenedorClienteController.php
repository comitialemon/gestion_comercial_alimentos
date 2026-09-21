<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ContenedorCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ClienteGrupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ContenedorClienteController extends Controller
{
    /**
     * ✅ OBTENER CLIENTES ASIGNADOS A UN CONTENEDOR
     * 
     * Nota: Ya NO se devuelve CantidadMinima (ese campo ya no se usa).
     * El mínimo ahora es por grupo + cliente en la tabla cliente_grupo.
     */
    public function getClientesAsignados($contenedorId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $clientes = ContenedorCliente::where('IdContenedor', $contenedorId)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->get();

            $clientesConNombres = $clientes->map(function($item) use ($clienteId, $sucursalId) {
                $identificador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_identificador')
                    ->where('IdIdentificador', $item->IdIdentificador)
                    ->first();

                // ✅ Verificar si el cliente ya tiene mínimos configurados
                $tieneMinimos = ClienteGrupo::where('IdIdentificador', $item->IdIdentificador)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('ActivoInactivo', 1)
                    ->exists();

                $totalGrupos = ClienteGrupo::where('IdIdentificador', $item->IdIdentificador)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('ActivoInactivo', 1)
                    ->count();

                return [
                    'IdContenedorCliente' => $item->IdContenedorCliente,
                    'IdIdentificador' => $item->IdIdentificador,
                    'Nombre' => $identificador ? $identificador->Nombre : 'Sin nombre',
                    'CI_NIT' => $identificador ? $identificador->CI_NIT : '',
                    'TieneMinimosConfigurados' => $tieneMinimos,   // ✅ NUEVO
                    'TotalGruposConfigurados' => $totalGrupos,      // ✅ NUEVO
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $clientesConNombres
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getClientesAsignados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ OBTENER CLIENTES DISPONIBLES PARA ASIGNAR
     */
    public function getClientesDisponibles()
    {
        try {
            $clientes = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador as i')
                ->join('todos_operador as o', 'i.IdIdentificador', '=', 'o.IdIdentificador')
                ->join('todos_operador_tipo as ot', 'o.IdOperadorTipo', '=', 'ot.IdOperadorTipo')
                ->where('ot.Detalle', 'PedidoClientes')
                ->where('o.ActivoInactivo', 0)
                ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
                ->orderBy('i.Nombre')
                ->distinct()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $clientes
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getClientesDisponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ OBTENER CAPACIDAD DEL CONTENEDOR
     */
    public function getCapacidadContenedor($contenedorId)
    {
        $clienteId = session('cliente_id');

        try {
            $contenedor = Contenedor::where('IdContenedor', $contenedorId)
                ->where('IdCliente', $clienteId)
                ->first(['CapacidadTotal']);

            if (!$contenedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contenedor no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'CapacidadTotal' => $contenedor->CapacidadTotal
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getCapacidadContenedor: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener capacidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ ASIGNAR CLIENTE A CONTENEDOR
     * 
     * CAMBIO: Ya NO se guarda CantidadMinima (ese campo se deja en 0).
     * Se devuelven los grupos del contenedor para que el admin
     * configure los mínimos por grupo.
     */
    public function asignarCliente(Request $request, $contenedorId)
    {
        $request->validate([
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            // ✅ VERIFICAR CONTENEDOR
            $contenedor = Contenedor::where('IdContenedor', $contenedorId)
                ->where('IdCliente', $clienteId)
                ->first();

            if (!$contenedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contenedor no encontrado'
                ], 404);
            }

            // ✅ VERIFICAR QUE EL CLIENTE YA ESTÉ ASIGNADO
            $existe = ContenedorCliente::where('IdContenedor', $contenedorId)
                ->where('IdIdentificador', $request->IdIdentificador)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este cliente ya está asignado al contenedor'
                ], 400);
            }

            // ✅ CREAR ASIGNACIÓN
            // Nota: CantidadMinima se guarda en 0 porque ya no se usa.
            $asignacion = ContenedorCliente::create([
                'IdContenedor' => $contenedorId,
                'IdIdentificador' => $request->IdIdentificador,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'CantidadMinima' => 0,   // ✅ Ya no se usa
                'ActivoInactivo' => 1,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => Carbon::now('America/La_Paz'),
            ]);

            // ✅ DEVOLVER LOS GRUPOS DEL CONTENEDOR
            //    (para que el frontend muestre el modal de configurar mínimos)
            $grupos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('operacion_pedidos_clientes_contenedor_grupo as cg')
                ->join('inventario_productogrupoanalisis as g', 'cg.IdGrupoAnalisis', '=', 'g.IdGrupoAnalisis')
                ->where('cg.IdContenedor', $contenedorId)
                ->select(
                    'g.IdGrupoAnalisis',
                    'g.Grupo as NombreGrupo'
                )
                ->orderBy('g.Grupo')
                ->get();

            // ✅ Devolver también los mínimos ya configurados (si el cliente ya tenía algunos)
            $minimosExistentes = ClienteGrupo::where('IdIdentificador', $request->IdIdentificador)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->pluck('CantidadMinimaGrupo', 'IdGrupoAnalisis');

            $gruposConMinimos = $grupos->map(function($grupo) use ($minimosExistentes) {
                return [
                    'IdGrupoAnalisis' => $grupo->IdGrupoAnalisis,
                    'NombreGrupo' => $grupo->NombreGrupo,
                    'CantidadMinimaGrupo' => $minimosExistentes[$grupo->IdGrupoAnalisis] ?? null,
                    'Configurado' => isset($minimosExistentes[$grupo->IdGrupoAnalisis]),
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Cliente asignado correctamente',
                'data' => $asignacion,
                'grupos_con_minimos' => $gruposConMinimos,   // ✅ Para el modal
                'requiere_configurar_minimos' => $gruposConMinimos->contains('Configurado', false),  // ✅ Si falta configurar
            ]);

        } catch (\Exception $e) {
            Log::error('Error en asignarCliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar cliente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ ELIMINAR CLIENTE DEL CONTENEDOR
     * 
     * Nota: Se elimina físicamente la asignación del cliente al contenedor.
     * Los mínimos configurados (ClienteGrupo) NO se eliminan porque son
     * globales del cliente, no del contenedor.
     */
    public function eliminarCliente($id)
    {
        try {
            $asignacion = ContenedorCliente::where('IdContenedorCliente', $id)
                ->where('ActivoInactivo', 1)
                ->first();

            if (!$asignacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Asignación no encontrada'
                ], 404);
            }

            $asignacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cliente eliminado del contenedor correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en eliminarCliente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}