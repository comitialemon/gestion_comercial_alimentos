<?php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\OperadorSucursalDb;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Operador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperadorSucursalController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        // Obtener todas las asignaciones
        $asignaciones = OperadorSucursalDb::where('IdCliente', $clienteId)
            ->with(['sucursal', 'operador.identificador'])
            ->get();

        // Obtener sucursales activas de la empresa actual
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);

        // Obtener operadores activos
        $operadores = Operador::where('ActivoInactivo', 0)
            ->with('identificador')
            ->orderBy('IdOperador')
            ->get()
            ->map(fn($op) => [
                'id' => $op->IdOperador,
                'nombre' => $op->identificador?->Nombre ?? 'Sin nombre',
                'ci' => $op->identificador?->CI_NIT ?? '',
                'iniciales' => $op->Iniciales,
            ]);

        return Inertia::render('Gestion/Todos/OperadorSucursal/Index', [
            'asignaciones' => $asignaciones,
            'sucursales' => $sucursales,
            'operadores' => $operadores,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'IdOperador' => 'required|exists:todos_operador,IdOperador',
        ]);

        $clienteId = session('cliente_id');

        // Verificar si ya existe la asignación
        $existe = OperadorSucursalDb::where('IdCliente', $clienteId)
            ->where('IdSucursal', $request->IdSucursal)
            ->where('IdOperador', $request->IdOperador)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Esta asignación ya existe'
            ], 422);
        }

        try {
            $asignacion = OperadorSucursalDb::create([
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->IdSucursal,
                'IdOperador' => $request->IdOperador,
            ]);

            // Cargar relaciones para la respuesta
            $asignacion->load(['sucursal', 'operador.identificador']);

            return response()->json([
                'success' => true,
                'message' => 'Asignación creada correctamente',
                'asignacion' => $asignacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear asignación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear asignación: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'IdOperador' => 'required|exists:todos_operador,IdOperador',
        ]);

        $clienteId = session('cliente_id');
        $asignacion = OperadorSucursalDb::where('IdSucursalDB', $id)
            ->where('IdCliente', $clienteId)
            ->firstOrFail();

        // Verificar si la nueva asignación ya existe (excluyendo la actual)
        $existe = OperadorSucursalDb::where('IdCliente', $clienteId)
            ->where('IdSucursal', $request->IdSucursal)
            ->where('IdOperador', $request->IdOperador)
            ->where('IdSucursalDB', '!=', $id)
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Esta asignación ya existe'
            ], 422);
        }

        try {
            $asignacion->update([
                'IdSucursal' => $request->IdSucursal,
                'IdOperador' => $request->IdOperador,
            ]);

            $asignacion->load(['sucursal', 'operador.identificador']);

            return response()->json([
                'success' => true,
                'message' => 'Asignación actualizada correctamente',
                'asignacion' => $asignacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar asignación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar asignación: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        
        try {
            $asignacion = OperadorSucursalDb::where('IdSucursalDB', $id)
                ->where('IdCliente', $clienteId)
                ->firstOrFail();
            
            $asignacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Asignación eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar asignación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar asignación: ' . $e->getMessage()
            ], 500);
        }
    }
}