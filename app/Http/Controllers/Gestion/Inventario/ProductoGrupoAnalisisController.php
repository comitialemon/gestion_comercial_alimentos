<?php
// app/Http/Controllers/Gestion/Inventario/ProductoGrupoAnalisisController.php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;  // ← IMPORTANTE
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ProductoGrupoAnalisisController extends Controller
{
    /**
     * Listado de grupos de análisis (SOLO del cliente logueado)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        $query = ProductoGrupoAnalisis::where('IdCliente', $clienteId);

        // Buscar por nombre
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('Grupo', 'like', "%{$search}%");
        }

        $items = $query->orderBy('IdGrupoAnalisis', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Gestion/Inventario/ProductoGrupoAnalisis/Index', [
            'items' => $items,
            'filtros' => [
                'search' => $request->search,
            ],
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    /**
     * Guardar nuevo grupo de análisis
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'Grupo' => 'required|string|max:255',
            ]);

            // Verificar si ya existe para este cliente
            $existe = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
                ->where('Grupo', $request->Grupo)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Este grupo de análisis ya existe para tu empresa');
            }

            ProductoGrupoAnalisis::create([
                'Grupo' => $request->Grupo,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
            ]);

            return redirect()->back()->with('success', 'Grupo de análisis creado correctamente');

        } catch (\Exception $e) {
            Log::error('Error creando grupo de análisis: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar grupo de análisis
     */
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'Grupo' => 'required|string|max:255',
            ]);

            $item = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
                ->where('IdGrupoAnalisis', $id)
                ->firstOrFail();

            // Verificar duplicado excluyendo el actual
            $existe = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
                ->where('Grupo', $request->Grupo)
                ->where('IdGrupoAnalisis', '!=', $id)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Este grupo de análisis ya existe para tu empresa');
            }

            $item->update([
                'Grupo' => $request->Grupo,
            ]);

            return redirect()->back()->with('success', 'Grupo de análisis actualizado correctamente');

        } catch (\Exception $e) {
            Log::error('Error actualizando grupo de análisis: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar grupo de análisis
     */
    public function destroy($id)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $item = ProductoGrupoAnalisis::where('IdCliente', $clienteId)
                ->where('IdGrupoAnalisis', $id)
                ->firstOrFail();

            $nombre = $item->Grupo;
            $item->delete();

            return redirect()->back()->with('success', "Grupo de análisis '{$nombre}' eliminado correctamente");

        } catch (\Exception $e) {
            Log::error('Error eliminando grupo de análisis: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }
}