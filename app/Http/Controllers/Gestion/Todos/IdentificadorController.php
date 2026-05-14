<?php
// app/Http/Controllers/Gestion/Todos/IdentificadorController.php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;

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

        $identificadores = $query->orderBy('IdIdentificador', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Gestion/Todos/Identificador/Index', [
            'items' => $identificadores,
            'filtros' => [
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
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

        return response()->json([
            'success' => true,
            'message' => 'Identificador creado correctamente',
            'identificador' => [
                'IdIdentificador' => $identificador->IdIdentificador,
                'CI_NIT' => $identificador->CI_NIT,
                'Nombre' => $identificador->Nombre,
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
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

        return redirect()->back()->with('success', 'Identificador actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Verificar si el identificador está siendo usado en otros lugares
        $identificador = Identificador::findOrFail($id);
        
        // Puedes agregar validaciones aquí si está siendo usado por comisionistas, etc.
        
        $identificador->delete();

        return redirect()->back()->with('success', 'Identificador eliminado correctamente.');
    }
}