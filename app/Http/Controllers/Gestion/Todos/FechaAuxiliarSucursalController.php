<?php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\FechaAuxiliarSucursal;
use App\Models\Gestion\Todos\Fecha;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class FechaAuxiliarSucursalController extends Controller
{
    /**
     * Muestra el formulario tipo grid con filas dinámicas
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        
        // Obtener todas las sucursales del cliente
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('NumeroSucursal')
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero'])
            ->map(fn($s) => [
                'id' => $s->id,
                'display' => $s->numero ? "{$s->numero} - {$s->nombre}" : $s->nombre
            ]);
        
        // Obtener todas las fechas disponibles
        $fechas = Fecha::orderBy('Fecha', 'desc')
            ->get(['IdFecha', DB::raw("DATE_FORMAT(Fecha, '%d/%m/%Y') as display")]);
        
        // Obtener registros existentes
        $registros = FechaAuxiliarSucursal::where('IdCliente', $clienteId)
            ->with(['sucursal', 'fecha'])
            ->orderBy('IdFechaAuxiliar', 'desc')
            ->get()
            ->map(fn($r) => [
                'id' => $r->IdFechaAuxiliar,
                'sucursal_id' => $r->IdSucursal,
                'sucursal_display' => $r->sucursal ? 
                    ($r->sucursal->NumeroSucursal ? "{$r->sucursal->NumeroSucursal} - {$r->sucursal->Nombre}" : $r->sucursal->Nombre) 
                    : '',
                'fecha_id' => $r->IdFecha,
                'fecha_display' => $r->fecha ? date('d/m/Y', strtotime($r->fecha->Fecha)) : '',
                'fecha_apertura' => $r->FechaApertura ? date('d/m/Y H:i:s', strtotime($r->FechaApertura)) : '',
            ]);
        
        return Inertia::render('Gestion/Todos/FechaAuxiliarSucursal/Index', [
            'sucursales' => $sucursales,
            'fechas' => $fechas,
            'registros' => $registros,
        ]);
    }
    
    /**
     * Guardar un nuevo registro (fila)
     */
    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'fecha_id' => 'required|exists:todos_fecha,IdFecha',
        ]);
        
        $clienteId = session('cliente_id');
        
        // Verificar si ya existe
        $existe = FechaAuxiliarSucursal::where('IdCliente', $clienteId)
            ->where('IdSucursal', $request->sucursal_id)
            ->where('IdFecha', $request->fecha_id)
            ->exists();
        
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un registro para esta sucursal y fecha'
            ], 422);
        }
        
        try {
            $registro = FechaAuxiliarSucursal::create([
                'IdFecha' => $request->fecha_id,
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->sucursal_id,
                'FechaApertura' => now(),
            ]);
            
            // Obtener datos completos para la respuesta
            $sucursal = ClienteSucursal::find($request->sucursal_id);
            $fecha = Fecha::find($request->fecha_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Registro creado correctamente',
                'registro' => [
                    'id' => $registro->IdFechaAuxiliar,
                    'sucursal_id' => $request->sucursal_id,
                    'sucursal_display' => $sucursal ? 
                        ($sucursal->NumeroSucursal ? "{$sucursal->NumeroSucursal} - {$sucursal->Nombre}" : $sucursal->Nombre) 
                        : '',
                    'fecha_id' => $request->fecha_id,
                    'fecha_display' => $fecha ? date('d/m/Y', strtotime($fecha->Fecha)) : '',
                    'fecha_apertura' => date('d/m/Y H:i:s'),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar un registro
     */
    public function destroy($id)
    {
        try {
            $registro = FechaAuxiliarSucursal::findOrFail($id);
            $registro->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Registro eliminado correctamente'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener una fecha específica (para validaciones)
     */
    public function getFecha($id)
    {
        $fecha = Fecha::find($id);
        if (!$fecha) {
            return response()->json(['success' => false, 'message' => 'Fecha no encontrada'], 404);
        }
        
        return response()->json([
            'success' => true,
            'fecha' => [
                'id' => $fecha->IdFecha,
                'display' => date('d/m/Y', strtotime($fecha->Fecha)),
            ]
        ]);
    }
    
    /**
     * Obtener una sucursal específica
     */
    public function getSucursal($id)
    {
        $sucursal = ClienteSucursal::find($id);
        if (!$sucursal) {
            return response()->json(['success' => false, 'message' => 'Sucursal no encontrada'], 404);
        }
        
        return response()->json([
            'success' => true,
            'sucursal' => [
                'id' => $sucursal->IdClienteSucursal,
                'display' => $sucursal->NumeroSucursal ? "{$sucursal->NumeroSucursal} - {$sucursal->Nombre}" : $sucursal->Nombre,
            ]
        ]);
    }
}