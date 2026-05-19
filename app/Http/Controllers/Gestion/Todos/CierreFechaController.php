<?php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Fecha;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class CierreFechaController extends Controller
{
    public function index(Request $request)
    {
        $query = Fecha::orderBy('ActivoInactivo', 'asc')
            ->orderBy('Fecha', 'desc');

        // Filtrar por mes y año
        if ($request->filled('mes') && $request->filled('anio')) {
            $mes = $request->mes;
            $anio = $request->anio;
            $query->whereMonth('Fecha', $mes)
                  ->whereYear('Fecha', $anio);
        }

        $fechas = $query->paginate(50);

        return Inertia::render('Gestion/Todos/CierreFecha/Index', [
            'fechas' => $fechas,
        ]);
    }

    public function update(Request $request, $id)
    {
        $fecha = Fecha::findOrFail($id);

        $request->validate([
            'ActivoInactivo' => 'required|boolean',
            'CierreSucursal' => 'required|boolean',
            'CierrePermanente' => 'required|boolean',
        ]);

        try {
            $fecha->update([
                'ActivoInactivo' => $request->ActivoInactivo ? 0 : 1,
                'CierreSucursal' => $request->CierreSucursal ? 1 : 0,
                'CierrePermanente' => $request->CierrePermanente ? 1 : 0,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fecha actualizada correctamente',
                'fecha' => $fecha
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar fecha: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateMultiple(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:todos_fecha,IdFecha',
            'campo' => 'required|string|in:ActivoInactivo,CierreSucursal,CierrePermanente',
            'valor' => 'required|boolean',
        ]);

        try {
            $valor = $request->valor ? 1 : 0;
            
            // Para ActivoInactivo: 0=activo, 1=inactivo
            if ($request->campo === 'ActivoInactivo') {
                $valor = $request->valor ? 0 : 1;
            }

            Fecha::whereIn('IdFecha', $request->ids)
                ->update([$request->campo => $valor]);

            return response()->json([
                'success' => true,
                'message' => 'Fechas actualizadas correctamente',
                'actualizadas' => count($request->ids)
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar múltiples fechas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar: ' . $e->getMessage()
            ], 500);
        }
    }
}