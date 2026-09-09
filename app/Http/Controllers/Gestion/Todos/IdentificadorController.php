<?php
// app/Http/Controllers/Gestion/Todos/IdentificadorController.php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class IdentificadorController extends Controller
{
    /**
     * Listado de identificadores
     */
    public function index(Request $request)
    {
        $query = Identificador::query();

        // Búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('CI_NIT', 'like', "%{$search}%")
                  ->orWhere('Nombre', 'like', "%{$search}%");
            });
        }

        // 🔥 SI ES UNA PETICIÓN AJAX, DEVOLVER JSON
        if ($request->wantsJson() || $request->ajax()) {
            $identificadores = $query->orderBy('IdIdentificador', 'desc')->get();
            return response()->json($identificadores);
        }

        $identificadores = $query->orderBy('IdIdentificador', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Gestion/Todos/Identificador/Index', [
            'items' => $identificadores,
            'filtros' => [
                'search' => $request->search,
            ],
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'CI_NIT' => 'required|numeric|unique:mysql_gestion_comercial_alimentos.todos_identificador,CI_NIT',
                'Nombre' => 'required|string|max:100',
            ]);

            $identificador = Identificador::create([
                'CI_NIT' => $request->CI_NIT,
                'Nombre' => $request->Nombre,
                'IdOperadorIngreso' => session('operador_id', 1),
                'FechaIngreso' => now(),
                'IdOperadorEdita' => session('operador_id', 1),
                'FechaEdita' => now(),
            ]);

            // 🔥 SI ES PETICIÓN AJAX, DEVOLVER JSON
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Identificador creado correctamente',
                    'identificador' => $identificador
                ]);
            }

            return redirect()->back()->with('success', 'Identificador creado correctamente');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Error de validación'
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error al crear identificador: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $identificador = Identificador::findOrFail($id);

            $request->validate([
                'CI_NIT' => 'required|numeric|unique:mysql_gestion_comercial_alimentos.todos_identificador,CI_NIT,' . $id . ',IdIdentificador',
                'Nombre' => 'required|string|max:100',
            ]);

            $identificador->update([
                'CI_NIT' => $request->CI_NIT,
                'Nombre' => $request->Nombre,
                'IdOperadorEdita' => session('operador_id', 1),
                'FechaEdita' => now(),
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Identificador actualizado correctamente',
                    'identificador' => $identificador
                ]);
            }

            return redirect()->back()->with('success', 'Identificador actualizado correctamente');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Error de validación'
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('Error al actualizar identificador: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $identificador = Identificador::findOrFail($id);
            $identificador->delete();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Identificador eliminado correctamente'
                ]);
            }

            return redirect()->back()->with('success', 'Identificador eliminado correctamente');

        } catch (\Exception $e) {
            Log::error('Error al eliminar identificador: ' . $e->getMessage());
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al eliminar: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }
}