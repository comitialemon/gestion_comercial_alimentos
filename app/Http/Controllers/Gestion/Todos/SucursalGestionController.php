<?php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\ClientePlaza;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SucursalGestionController extends Controller
{
    public function index()
    {
        $sucursales = ClienteSucursal::porContexto()
            ->with('plaza')
            ->orderBy('NumeroSucursal')
            ->get();

        $plazas = ClientePlaza::orderBy('Plaza')
            ->get(['IdPlaza as id', 'Plaza as nombre']);

        return Inertia::render('Gestion/Todos/Sucursales/Index', [
            'sucursales' => $sucursales,
            'plazas' => $plazas,
        ]);
    }

    public function create()
    {
        $plazas = ClientePlaza::orderBy('Plaza')
            ->get(['IdPlaza as id', 'Plaza as nombre']);

        return Inertia::render('Gestion/Todos/Sucursales/Create', [
            'plazas' => $plazas,
        ]);
    }

    public function edit($id)
    {
        $sucursal = ClienteSucursal::porContexto()
            ->findOrFail($id);

        $plazas = ClientePlaza::orderBy('Plaza')
            ->get(['IdPlaza as id', 'Plaza as nombre']);

        return Inertia::render('Gestion/Todos/Sucursales/Create', [
            'sucursal' => $sucursal,
            'plazas' => $plazas,
            'editando' => true,
        ]);
    }

    /**
     * Obtener el siguiente número de sucursal disponible
     */
    public function obtenerSiguienteNumero()
    {
        try {
            $clienteId = session('cliente_id');
            
            // Obtener el máximo número de sucursal para este cliente
            $maxNumero = ClienteSucursal::where('IdCliente', $clienteId)
                ->max('NumeroSucursal');
            
            $siguienteNumero = ($maxNumero ?? 0) + 1;
            
            // Obtener el máximo orden
            $maxOrden = ClienteSucursal::where('IdCliente', $clienteId)
                ->max('Orden');
            
            $siguienteOrden = ($maxOrden ?? 0) + 1;
            
            return response()->json([
                'success' => true,
                'siguienteNumero' => $siguienteNumero,
                'siguienteOrden' => $siguienteOrden,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener siguiente número: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el siguiente número',
                'siguienteNumero' => 1,
                'siguienteOrden' => 1,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'IdPlaza' => 'required|exists:todos_cliente_plaza,IdPlaza',
            'Nombre' => 'required|string|max:255',
            'Direccion' => 'required|string|max:255',
            'Celular' => 'required|string|max:30',
            'NumeroSucursal' => 'nullable|integer',
            'Orden' => 'nullable|integer|min:0',
            'ActivoInactivo' => 'required|boolean',
            'ActivaInactivaR' => 'required|boolean',
        ]);

        try {
            $sucursal = ClienteSucursal::create([
                'IdCliente' => session('cliente_id'),
                'IdPlaza' => $request->IdPlaza,
                'Nombre' => $request->Nombre,
                'Direccion' => $request->Direccion,
                'Celular' => $request->Celular,
                'NumeroSucursal' => $request->NumeroSucursal ?? 0,
                'ActivaInactivaR' => $request->ActivaInactivaR,
                'Orden' => $request->Orden ?? 0,
                'ActivoInactivo' => $request->ActivoInactivo,
                'ControlInternoEfectivo' => 0,
                'facturacion_habilitada' => 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal creada correctamente',
                'sucursal' => $sucursal->load('plaza')
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al crear sucursal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $sucursal = ClienteSucursal::porContexto()->findOrFail($id);

        $request->validate([
            'IdPlaza' => 'required|exists:todos_cliente_plaza,IdPlaza',
            'Nombre' => 'required|string|max:255',
            'Direccion' => 'required|string|max:255',
            'Celular' => 'required|string|max:30',
            'NumeroSucursal' => 'nullable|integer',
            'Orden' => 'nullable|integer|min:0',
            'ActivoInactivo' => 'required|boolean',
            'ActivaInactivaR' => 'required|boolean',
        ]);

        try {
            $sucursal->update([
                'IdPlaza' => $request->IdPlaza,
                'Nombre' => $request->Nombre,
                'Direccion' => $request->Direccion,
                'Celular' => $request->Celular,
                'NumeroSucursal' => $request->NumeroSucursal ?? 0,
                'ActivaInactivaR' => $request->ActivaInactivaR,
                'Orden' => $request->Orden ?? 0,
                'ActivoInactivo' => $request->ActivoInactivo,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sucursal actualizada correctamente',
                'sucursal' => $sucursal->load('plaza')
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al actualizar sucursal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $sucursal = ClienteSucursal::porContexto()->findOrFail($id);

        try {
            $sucursal->delete();
            return response()->json([
                'success' => true,
                'message' => 'Sucursal eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
}