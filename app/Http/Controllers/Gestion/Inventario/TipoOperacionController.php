<?php
// app/Http/Controllers/Gestion/Inventario/TipoOperacionController.php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\TipoOperacion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class TipoOperacionController extends Controller
{
    /**
     * Listado de tipos de operación (SOLO del cliente logueado)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        $query = TipoOperacion::where('IdCliente', $clienteId);

        // Buscar por detalle o concepto
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('Detalle', 'like', "%{$search}%")
                  ->orWhere('Concepto', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('IdTipoOperacion', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Gestion/Inventario/TipoOperacion/Index', [
            'items' => $items,
            'filtros' => [
                'search' => $request->search,
            ],
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    /**
     * Guardar nuevo tipo de operación
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'Detalle' => 'required|string|max:255',
                'Concepto' => 'required|string|max:30',
                'ActivoInactivo' => 'required|boolean',
            ]);

            // Verificar si ya existe para este cliente
            $existe = TipoOperacion::where('IdCliente', $clienteId)
                ->where('Detalle', $request->Detalle)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Este tipo de operación ya existe para tu empresa');
            }

            TipoOperacion::create([
                'Detalle' => $request->Detalle,
                'Concepto' => $request->Concepto,
                'ActivoInactivo' => $request->ActivoInactivo,
                'IdCliente' => $clienteId,
            ]);

            return redirect()->back()->with('success', 'Tipo de operación creado correctamente');

        } catch (\Exception $e) {
            Log::error('Error creando tipo de operación: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar tipo de operación
     */
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'Detalle' => 'required|string|max:255',
                'Concepto' => 'required|string|max:30',
                'ActivoInactivo' => 'required|boolean',
            ]);

            $item = TipoOperacion::where('IdCliente', $clienteId)
                ->where('IdTipoOperacion', $id)
                ->firstOrFail();

            // Verificar duplicado excluyendo el actual
            $existe = TipoOperacion::where('IdCliente', $clienteId)
                ->where('Detalle', $request->Detalle)
                ->where('IdTipoOperacion', '!=', $id)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Este tipo de operación ya existe para tu empresa');
            }

            $item->update([
                'Detalle' => $request->Detalle,
                'Concepto' => $request->Concepto,
                'ActivoInactivo' => $request->ActivoInactivo,
            ]);

            return redirect()->back()->with('success', 'Tipo de operación actualizado correctamente');

        } catch (\Exception $e) {
            Log::error('Error actualizando tipo de operación: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar tipo de operación
     */
    public function destroy($id)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $item = TipoOperacion::where('IdCliente', $clienteId)
                ->where('IdTipoOperacion', $id)
                ->firstOrFail();

            $nombre = $item->Detalle;
            $item->delete();

            return redirect()->back()->with('success', "Tipo de operación '{$nombre}' eliminado correctamente");

        } catch (\Exception $e) {
            Log::error('Error eliminando tipo de operación: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }
}