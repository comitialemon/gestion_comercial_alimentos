<?php
// app/Http/Controllers/Gestion/Inventario/ProductoLineaController.php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\ProductoLinea;
use App\Models\Gestion\Inventario\ProductoEstado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ProductoLineaController extends Controller
{
    /**
     * Listado de líneas de producto (SOLO del cliente logueado)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        $query = ProductoLinea::where('IdCliente', $clienteId)
            ->with('estado');

        // Buscar por nombre
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('Linea', 'like', "%{$search}%");
        }

        $items = $query->orderBy('IdLinea', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Obtener estados para el select
        $estados = ProductoEstado::where('IdCliente', $clienteId)
            ->get(['IdEstado as id', 'Estado as nombre']);

        return Inertia::render('Gestion/Inventario/ProductoLinea/Index', [
            'items' => $items,
            'estados' => $estados,
            'filtros' => [
                'search' => $request->search,
            ],
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    /**
     * Guardar nueva línea de producto
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'IdEstado' => 'required|integer|exists:inventario_producto_estado,IdEstado',
                'Linea' => 'required|string|max:50',
            ]);

            // Verificar si ya existe para este cliente
            $existe = ProductoLinea::where('IdCliente', $clienteId)
                ->where('Linea', $request->Linea)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Esta línea ya existe para tu empresa');
            }

            ProductoLinea::create([
                'IdEstado' => $request->IdEstado,
                'Linea' => $request->Linea,
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
                'IdOperador' => $operadorId,
            ]);

            return redirect()->back()->with('success', 'Línea de producto creada correctamente');

        } catch (\Exception $e) {
            Log::error('Error creando línea de producto: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar línea de producto
     */
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'IdEstado' => 'required|integer|exists:inventario_producto_estado,IdEstado',
                'Linea' => 'required|string|max:50',
            ]);

            $item = ProductoLinea::where('IdCliente', $clienteId)
                ->where('IdLinea', $id)
                ->firstOrFail();

            // Verificar duplicado excluyendo el actual
            $existe = ProductoLinea::where('IdCliente', $clienteId)
                ->where('Linea', $request->Linea)
                ->where('IdLinea', '!=', $id)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Esta línea ya existe para tu empresa');
            }

            $item->update([
                'IdEstado' => $request->IdEstado,
                'Linea' => $request->Linea,
            ]);

            return redirect()->back()->with('success', 'Línea de producto actualizada correctamente');

        } catch (\Exception $e) {
            Log::error('Error actualizando línea de producto: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar línea de producto
     */
    public function destroy($id)
    {
        $clienteId = session('cliente_id');

        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $item = ProductoLinea::where('IdCliente', $clienteId)
                ->where('IdLinea', $id)
                ->firstOrFail();

            // Verificar si tiene productos asociados
            if ($item->productos()->count() > 0) {
                return redirect()->back()->with('error', 'No se puede eliminar porque tiene productos asociados');
            }

            $nombre = $item->Linea;
            $item->delete();

            return redirect()->back()->with('success', "Línea de producto '{$nombre}' eliminada correctamente");

        } catch (\Exception $e) {
            Log::error('Error eliminando línea de producto: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }
}