<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoAprobacionConfig;
use App\Models\Gestion\Todos\Operador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductoAprobacionConfigController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        
        $configuraciones = ProductoAprobacionConfig::porContexto()
            ->with('operador.identificador')
            ->orderBy('IdProductoAprobacionConfig')
            ->get();
        
        // Obtener operadores disponibles (que no están ya configurados)
        $operadoresDisponibles = Operador::whereHas('empresas', function($q) use ($clienteId) {
                $q->where('todos_cliente.IdCliente', $clienteId);
            })
            ->whereNotIn('IdOperador', $configuraciones->pluck('IdOperador'))
            ->with('identificador')
            ->orderBy('IdOperador')
            ->get()
            ->map(fn($op) => [
                'id' => $op->IdOperador,
                'nombre' => $op->identificador?->Nombre ?? 'Sin nombre',
                'ci' => $op->identificador?->CI_NIT ?? ''
            ]);
        
        return Inertia::render('Gestion/Inventario/ProductoAprobacionConfig/Index', [
            'configuraciones' => $configuraciones,
            'operadoresDisponibles' => $operadoresDisponibles,
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'IdOperador' => 'required|exists:todos_operador,IdOperador',
        ]);
        
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        // Verificar si ya existe
        $existe = ProductoAprobacionConfig::porContexto()
            ->where('IdOperador', $request->IdOperador)
            ->exists();
        
        if ($existe) {
            return redirect()->back()->with('error', 'Este operador ya está configurado como aprobador');
        }
        
        try {
            ProductoAprobacionConfig::create([
                'IdCliente' => $clienteId,
                'IdOperador' => $request->IdOperador,
                'ActivoInactivo' => 0,
                'IdOperadorIngresa' => $operadorId,
                'FechaIngreso' => now(),
            ]);
            
            return redirect()->back()->with('success', 'Aprobador agregado correctamente');
            
        } catch (\Exception $e) {
            Log::error('Error al agregar aprobador: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al agregar: ' . $e->getMessage());
        }
    }
    
    public function destroy($id)
    {
        try {
            $config = ProductoAprobacionConfig::porContexto()->findOrFail($id);
            $config->delete();
            
            // ✅ Devolver JSON en lugar de redirección
            return response()->json([
                'success' => true,
                'message' => 'Aprobador eliminado correctamente'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function toggle($id)
    {
        try {
            $config = ProductoAprobacionConfig::porContexto()->findOrFail($id);
            $config->update([
                'ActivoInactivo' => $config->ActivoInactivo == 0 ? 1 : 0,
                'IdOperadorActualiza' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);
            
            $estado = $config->ActivoInactivo == 0 ? 'activado' : 'desactivado';
            return redirect()->back()->with('success', "Aprobador {$estado} correctamente");
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al cambiar estado');
        }
    }
}