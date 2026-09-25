<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Operacion\Pedidos\ClientesMayoristas\ContenedorGrupoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\GrupoClienteDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ContenedorGrupoClienteController extends Controller
{
    /**
     * ✅ OBTENER GRUPOS ASIGNADOS A UN CONTENEDOR
     */
    public function getGruposAsignados($contenedorId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $contenedor = Contenedor::porCliente($clienteId)
                ->where('IdContenedor', $contenedorId)
                ->firstOrFail();

            $asignados = ContenedorGrupoCliente::where('IdContenedor', $contenedorId)
                ->where('ActivoInactivo', 1)
                ->with('grupoCliente')
                ->get()
                ->map(function ($item) {
                    $grupo = $item->grupoCliente;
                    
                    // Contar clientes activos del grupo
                    $totalClientes = 0;
                    if ($grupo) {
                        $totalClientes = GrupoClienteDetalle::where('IdGrupoCliente', $grupo->IdGrupoCliente)
                            ->where('ActivoInactivo', 1)
                            ->count();
                    }

                    return [
                        'IdContenedorGrupoCliente' => $item->IdContenedorGrupoCliente,
                        'IdGrupoCliente' => $item->IdGrupoCliente,
                        'Nombre' => $grupo ? $grupo->Nombre : 'Sin nombre',
                        'Descripcion' => $grupo ? $grupo->Descripcion : null,
                        'TotalClientes' => $totalClientes,
                        'FechaInserta' => $item->FechaInserta 
                            ? Carbon::parse($item->FechaInserta)->format('d/m/Y H:i') 
                            : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $asignados,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getGruposAsignados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ OBTENER GRUPOS DISPONIBLES PARA ASIGNAR
     * 
     * Disponibles = grupos activos que NO están asignados a este contenedor.
     */
    public function getGruposDisponibles(Request $request, $contenedorId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        try {
            $contenedor = Contenedor::porCliente($clienteId)
                ->where('IdContenedor', $contenedorId)
                ->firstOrFail();

            // IDs ya asignados a este contenedor
            $idsAsignados = ContenedorGrupoCliente::where('IdContenedor', $contenedorId)
                ->where('ActivoInactivo', 1)
                ->pluck('IdGrupoCliente')
                ->toArray();

            // Grupos activos del cliente
            $query = GrupoCliente::porCliente($clienteId)
                ->porSucursal($sucursalId)
                ->where('ActivoInactivo', 1)
                ->whereNotIn('IdGrupoCliente', $idsAsignados);

            // Búsqueda opcional
            if ($request->filled('buscar')) {
                $buscar = $request->buscar;
                $query->where(function ($q) use ($buscar) {
                    $q->where('Nombre', 'LIKE', "%{$buscar}%")
                      ->orWhere('Descripcion', 'LIKE', "%{$buscar}%");
                });
            }

            $grupos = $query->orderBy('Nombre', 'asc')->get();

            $resultado = $grupos->map(function ($grupo) {
                // Contar clientes activos
                $totalClientes = GrupoClienteDetalle::where('IdGrupoCliente', $grupo->IdGrupoCliente)
                    ->where('ActivoInactivo', 1)
                    ->count();

                return [
                    'IdGrupoCliente' => $grupo->IdGrupoCliente,
                    'Nombre' => $grupo->Nombre,
                    'Descripcion' => $grupo->Descripcion,
                    'TotalClientes' => $totalClientes,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $resultado,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en getGruposDisponibles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ ASIGNAR UN GRUPO AL CONTENEDOR
     */
    public function asignarGrupo(Request $request, $contenedorId)
    {
        $request->validate([
            'IdGrupoCliente' => 'required|exists:operacion_pedidos_clientes_grupo_cliente,IdGrupoCliente',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            $contenedor = Contenedor::porCliente($clienteId)
                ->where('IdContenedor', $contenedorId)
                ->firstOrFail();

            $grupo = GrupoCliente::porCliente($clienteId)
                ->porSucursal($sucursalId)
                ->where('IdGrupoCliente', $request->IdGrupoCliente)
                ->firstOrFail();

            // Verificar que no esté ya asignado
            $existe = ContenedorGrupoCliente::where('IdContenedor', $contenedorId)
                ->where('IdGrupoCliente', $request->IdGrupoCliente)
                ->where('ActivoInactivo', 1)
                ->exists();

            if ($existe) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este grupo ya está asignado al contenedor'
                ], 400);
            }

            // Crear asignación (o reactivar si existía inactiva)
            $asignacion = ContenedorGrupoCliente::updateOrCreate(
                [
                    'IdContenedor' => $contenedorId,
                    'IdGrupoCliente' => $request->IdGrupoCliente,
                ],
                [
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'ActivoInactivo' => 1,
                    'IdOperadorInserta' => $operadorId,
                    'FechaInserta' => Carbon::now('America/La_Paz'),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Grupo asignado correctamente',
                'data' => $asignacion,
            ]);

        } catch (\Exception $e) {
            Log::error('Error en asignarGrupo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ ELIMINAR UN GRUPO DEL CONTENEDOR
     */
    public function eliminarGrupo($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        try {
            $asignacion = ContenedorGrupoCliente::porCliente($clienteId)
                ->where('IdContenedorGrupoCliente', $id)
                ->where('ActivoInactivo', 1)
                ->firstOrFail();

            $asignacion->update([
                'ActivoInactivo' => 0,
                'IdOperadorActualiza' => $operadorId,
                'FechaActualiza' => Carbon::now('America/La_Paz'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado del contenedor correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error en eliminarGrupo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar grupo: ' . $e->getMessage()
            ], 500);
        }
    }
}