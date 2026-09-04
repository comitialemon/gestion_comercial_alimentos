<?php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\OperadorTipo;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OperadorController extends Controller
{
    public function index(Request $request)
    {
        $query = Operador::with(['identificador', 'tipo']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('identificador', function($q) use ($search) {
                $q->where('Nombre', 'like', "%{$search}%")
                  ->orWhere('CI_NIT', 'like', "%{$search}%");
            })->orWhere('NombreAcceso', 'like', "%{$search}%");
        }

        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }

        if ($request->filled('tipo')) {
            $query->where('IdOperadorTipo', $request->tipo);
        }

        $operadores = $query->orderBy('IdOperador', 'desc')
            ->paginate(20)
            ->withQueryString();

        $tiposOperador = OperadorTipo::orderBy('Detalle')->get();
        $identificadores = Identificador::orderBy('Nombre')->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        return Inertia::render('Gestion/Todos/Operador/Index', [
            'operadores' => $operadores,
            'tiposOperador' => $tiposOperador,
            'identificadores' => $identificadores,
            'filtros' => [
                'search' => $request->search,
                'estado' => $request->estado,
                'tipo' => $request->tipo,
            ],
        ]);
    }

    public function store(Request $request)
    {
        // 🔥 CONVERTIR TELÉFONOS A STRING antes de validar
        $request->merge([
            'TelefonoDomicilio' => $request->TelefonoDomicilio !== null && $request->TelefonoDomicilio !== '' 
                ? (string) $request->TelefonoDomicilio 
                : null,
            'NumeroCelular' => $request->NumeroCelular !== null && $request->NumeroCelular !== '' 
                ? (string) $request->NumeroCelular 
                : null,
        ]);

        $request->validate([
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'Iniciales' => 'required|string|max:5',
            'Clave' => 'required|string|min:4|max:15',
            'NombreAcceso' => 'required|string|max:20|unique:mysql_gestion_comercial_alimentos.todos_operador,NombreAcceso',
            'DireccionDomicilio' => 'nullable|string',
            'TelefonoDomicilio' => 'nullable|string|max:20',
            'NumeroCelular' => 'nullable|string|max:20',
            'IdOperadorTipo' => 'required|exists:todos_operador_tipo,IdOperadorTipo',
            'ActivoInactivo' => 'required|boolean',
        ]);

        try {
            $operador = Operador::create([
                'IdIdentificador' => $request->IdIdentificador,
                'Iniciales' => strtoupper($request->Iniciales),
                'Clave' => $request->Clave,
                'NombreAcceso' => $request->NombreAcceso,
                'DireccionDomicilio' => $request->DireccionDomicilio,
                'TelefonoDomicilio' => $request->TelefonoDomicilio,
                'NumeroCelular' => $request->NumeroCelular,
                'IdOperadorTipo' => $request->IdOperadorTipo,
                'ActivoInactivo' => $request->ActivoInactivo ? 0 : 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Operador creado correctamente',
                'operador' => $operador->load('identificador', 'tipo')
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear operador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear operador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $operador = Operador::findOrFail($id);

        // 🔥 CONVERTIR TELÉFONOS A STRING antes de validar
        $request->merge([
            'TelefonoDomicilio' => $request->TelefonoDomicilio !== null && $request->TelefonoDomicilio !== '' 
                ? (string) $request->TelefonoDomicilio 
                : null,
            'NumeroCelular' => $request->NumeroCelular !== null && $request->NumeroCelular !== '' 
                ? (string) $request->NumeroCelular 
                : null,
        ]);

        $request->validate([
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'Iniciales' => 'required|string|max:5',
            'Clave' => 'nullable|string|min:4|max:15',
            'NombreAcceso' => 'required|string|max:20|unique:mysql_gestion_comercial_alimentos.todos_operador,NombreAcceso,' . $id . ',IdOperador',
            'DireccionDomicilio' => 'nullable|string',
            'TelefonoDomicilio' => 'nullable|string|max:20',
            'NumeroCelular' => 'nullable|string|max:20',
            'IdOperadorTipo' => 'required|exists:todos_operador_tipo,IdOperadorTipo',
            'ActivoInactivo' => 'required|boolean',
        ]);

        try {
            $datos = [
                'IdIdentificador' => $request->IdIdentificador,
                'Iniciales' => strtoupper($request->Iniciales),
                'NombreAcceso' => $request->NombreAcceso,
                'DireccionDomicilio' => $request->DireccionDomicilio,
                'TelefonoDomicilio' => $request->TelefonoDomicilio,
                'NumeroCelular' => $request->NumeroCelular,
                'IdOperadorTipo' => $request->IdOperadorTipo,
                'ActivoInactivo' => $request->ActivoInactivo ? 0 : 1,
            ];

            if ($request->filled('Clave')) {
                $datos['Clave'] = $request->Clave;
            }

            $operador->update($datos);

            return response()->json([
                'success' => true,
                'message' => 'Operador actualizado correctamente',
                'operador' => $operador->load('identificador', 'tipo')
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar operador: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar operador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $operador = Operador::findOrFail($id);

        try {
            $operador->update(['ActivoInactivo' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'Operador desactivado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar operador: ' . $e->getMessage()
            ], 500);
        }
    }

    public function activar($id)
    {
        $operador = Operador::findOrFail($id);

        try {
            $operador->update(['ActivoInactivo' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'Operador activado correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al activar operador: ' . $e->getMessage()
            ], 500);
        }
    }
}