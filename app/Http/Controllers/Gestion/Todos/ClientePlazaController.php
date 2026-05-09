<?php
// app/Http/Controllers/Gestion/Todos/ClientePlazaController.php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ClientePlaza;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientePlazaController extends Controller
{
    public function index()
    {
        $plazas = ClientePlaza::orderBy('Plaza')->get();
        
        return Inertia::render('Gestion/Todos/Plaza/Index', [
            'plazas' => $plazas
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'Plaza' => 'required|string|max:255',
            'Abreviacion' => 'required|string|max:50',
        ]);

        ClientePlaza::create($request->all());

        return redirect()->route('gestion.plazas.index')
            ->with('success', 'Plaza creada correctamente');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'Plaza' => 'required|string|max:255',
            'Abreviacion' => 'required|string|max:50',
        ]);

        $plaza = ClientePlaza::findOrFail($id);
        $plaza->update($request->all());

        return redirect()->route('gestion.plazas.index')
            ->with('success', 'Plaza actualizada correctamente');
    }

    public function destroy($id)
    {
        $plaza = ClientePlaza::findOrFail($id);
        
        // Verificar si tiene sucursales asociadas
        if ($plaza->sucursales()->count() > 0) {
            return redirect()->route('gestion.plazas.index')
                ->with('error', 'No se puede eliminar la plaza porque tiene sucursales asociadas');
        }
        
        $plaza->delete();

        return redirect()->route('gestion.plazas.index')
            ->with('success', 'Plaza eliminada correctamente');
    }
}