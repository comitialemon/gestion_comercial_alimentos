<?php
// app/Http/Controllers/Gestion/Inventario/ProductoEstadoController.php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller; // ← IMPORTANTE
use App\Models\Gestion\Inventario\ProductoEstado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ProductoEstadoController extends Controller
{
    /**
     * Listado de estados de producto (SOLO del cliente logueado)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        $query = ProductoEstado::where('IdCliente', $clienteId);

        // 🔥 BÚSQUEDA POR NOMBRE - ESTO ES LO QUE FALTABA
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('Estado', 'like', "%{$search}%");
        }

        $items = $query->orderBy('IdEstado', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Gestion/Inventario/ProductoEstado/Index', [
            'items' => $items,
            'fillable' => ['Estado'],
            'filtros' => [
                'search' => $request->search,
            ],
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    /**
     * Guardar nuevo estado de producto
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'Estado' => 'required|string|max:30',
            ]);

            // Verificar si ya existe para este cliente
            $existe = ProductoEstado::where('IdCliente', $clienteId)
                ->where('Estado', $request->Estado)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Este estado ya existe para tu empresa');
            }

            ProductoEstado::create([
                'Estado' => $request->Estado,
                'IdCliente' => $clienteId,
                'IOperador' => $operadorId,
            ]);

            return redirect()->back()->with('success', 'Estado de producto creado correctamente');

        } catch (\Exception $e) {
            Log::error('Error creando estado de producto: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar estado de producto
     */
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'Estado' => 'required|string|max:30',
            ]);

            $item = ProductoEstado::where('IdCliente', $clienteId)
                ->where('IdEstado', $id)
                ->firstOrFail();

            // Verificar duplicado excluyendo el actual
            $existe = ProductoEstado::where('IdCliente', $clienteId)
                ->where('Estado', $request->Estado)
                ->where('IdEstado', '!=', $id)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Este estado ya existe para tu empresa');
            }

            $item->update([
                'Estado' => $request->Estado,
            ]);

            return redirect()->back()->with('success', 'Estado de producto actualizado correctamente');

        } catch (\Exception $e) {
            Log::error('Error actualizando estado de producto: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar estado de producto
     */
    public function destroy($id)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $item = ProductoEstado::where('IdCliente', $clienteId)
                ->where('IdEstado', $id)
                ->firstOrFail();

            $nombre = $item->Estado;
            $item->delete();

            return redirect()->back()->with('success', "Estado de producto '{$nombre}' eliminado correctamente");

        } catch (\Exception $e) {
            Log::error('Error eliminando estado de producto: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }
}