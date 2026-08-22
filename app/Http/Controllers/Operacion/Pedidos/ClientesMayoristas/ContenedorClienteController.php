<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ContenedorCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ContenedorClienteController extends Controller
{
    /**
     * ✅ OBTENER CLIENTES ASIGNADOS A UN CONTENEDOR
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

            $clientesConNombres = $clientes->map(function($item) {
                $identificador = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_identificador')
                    ->where('IdIdentificador', $item->IdIdentificador)
                    ->first();

                return [
                    'IdContenedorCliente' => $item->IdContenedorCliente,
                    'IdIdentificador' => $item->IdIdentificador,
                    'Nombre' => $identificador ? $identificador->Nombre : 'Sin nombre',
                    'CI_NIT' => $identificador ? $identificador->CI_NIT : '',
                    'CantidadMinima' => $item->CantidadMinima,
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
                ->where('o.ActivoInactivo', 0) // ✅ 0 = Activo
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
     * ✅ OBTENER CAPACIDAD DEL CONTENEDOR (para validación en frontend)
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
     * ✅ ASIGNAR CLIENTE A CONTENEDOR - CON VALIDACIÓN DE CAPACIDAD
     */
    public function asignarCliente(Request $request, $contenedorId)
    {
        $request->validate([
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'CantidadMinima' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            // ✅ OBTENER CONTENEDOR CON SU CAPACIDAD
            $contenedor = Contenedor::where('IdContenedor', $contenedorId)
                ->where('IdCliente', $clienteId)
                ->first();

            if (!$contenedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contenedor no encontrado'
                ], 404);
            }

            // ✅ VALIDAR: Cantidad mínima NO puede ser mayor que la capacidad del contenedor
            if ($request->CantidadMinima > $contenedor->CapacidadTotal) {
                return response()->json([
                    'success' => false,
                    'message' => "La cantidad mínima ({$request->CantidadMinima}) no puede ser mayor que la capacidad del contenedor ({$contenedor->CapacidadTotal})"
                ], 400);
            }

            // ✅ VERIFICAR SI YA ESTÁ ASIGNADO
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
            $asignacion = ContenedorCliente::create([
                'IdContenedor' => $contenedorId,
                'IdIdentificador' => $request->IdIdentificador,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'CantidadMinima' => $request->CantidadMinima,
                'ActivoInactivo' => 1,
                'IdOperadorInserta' => $operadorId,
                'FechaInserta' => Carbon::now('America/La_Paz'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cliente asignado correctamente',
                'data' => $asignacion
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
     * ✅ ACTUALIZAR CANTIDAD MÍNIMA - CON VALIDACIÓN DE CAPACIDAD
     */
    public function actualizarMinimo(Request $request, $id)
    {
        $request->validate([
            'CantidadMinima' => 'required|numeric|min:0',
        ]);

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

            // ✅ OBTENER CAPACIDAD DEL CONTENEDOR
            $contenedor = Contenedor::where('IdContenedor', $asignacion->IdContenedor)
                ->first();

            if (!$contenedor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contenedor no encontrado'
                ], 404);
            }

            // ✅ VALIDAR: Cantidad mínima NO puede ser mayor que la capacidad del contenedor
            if ($request->CantidadMinima > $contenedor->CapacidadTotal) {
                return response()->json([
                    'success' => false,
                    'message' => "La cantidad mínima ({$request->CantidadMinima}) no puede ser mayor que la capacidad del contenedor ({$contenedor->CapacidadTotal})"
                ], 400);
            }

            $asignacion->update([
                'CantidadMinima' => $request->CantidadMinima,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cantidad mínima actualizada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en actualizarMinimo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ ELIMINAR CLIENTE DEL CONTENEDOR
     */
    // En ContenedorClienteController@eliminarCliente
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

            // ✅ OPCIÓN 1: Eliminar físicamente (permitir reasignar)
            $asignacion->delete(); // ← ELIMINAR FÍSICAMENTE

            // ✅ OPCIÓN 2: Desactivar (NO permite reasignar)
            // $asignacion->update([
            //     'ActivoInactivo' => 0,
            //     'IdOperadorActualiza' => session('operador_id'),
            //     'FechaActualiza' => Carbon::now('America/La_Paz'),
            // ]);

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