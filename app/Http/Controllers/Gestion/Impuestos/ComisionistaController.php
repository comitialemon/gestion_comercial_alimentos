<?php
// app/Http/Controllers/Gestion/Impuestos/ComisionistaController.php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Comisionista;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ComisionistaController extends Controller
{
    /**
     * Listado de comisionistas (filtrado por cliente logueado)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        $query = Comisionista::with(['identificador', 'cliente'])
            ->where('IdCliente', $clienteId);

        // Buscar por nombre o CI
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('identificador', function($q) use ($search) {
                $q->where('Nombre', 'like', "%{$search}%")
                  ->orWhere('CI_NIT', 'like', "%{$search}%");
            });
        }

        $comisionistas = $query->orderBy('IdComisionista', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Gestion/Impuestos/Comisionista/Index', [
            'comisionistas' => $comisionistas,
            'filtros' => [
                'search' => $request->search,
            ],
            'contexto_actual' => [
                'cliente_id' => session('cliente_id'),
                'cliente_nombre' => session('cliente_nombre'),
            ],
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    /**
     * Buscar identificadores (AJAX para select)
     */
    public function buscarIdentificador(Request $request)
    {
        $term = $request->get('q', '');
        
        $results = Identificador::where('CI_NIT', 'like', "%{$term}%")
            ->orWhere('Nombre', 'like', "%{$term}%")
            ->orderBy('Nombre')
            ->limit(20)
            ->get()
            ->map(fn($item) => [
                'id' => $item->IdIdentificador,
                'ci' => $item->CI_NIT,
                'nombre' => $item->Nombre,
            ]);

        return response()->json($results);
    }

    /**
     * Guardar nuevo comisionista
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'IdIdentificador' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_identificador,IdIdentificador',
                'Comision' => 'required|numeric|min:0|max:100',
            ]);

            // Verificar si ya existe para este cliente
            $existe = Comisionista::where('IdIdentificador', $request->IdIdentificador)
                ->where('IdCliente', $clienteId)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Este identificador ya está registrado como comisionista para esta empresa.');
            }

            Comisionista::create([
                'IdIdentificador' => $request->IdIdentificador,
                'Comision' => $request->Comision,
                'IdCliente' => $clienteId,
            ]);

            return redirect()->back()->with('success', 'Comisionista creado correctamente.');

        } catch (\Exception $e) {
            Log::error('Error creando comisionista: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar comisionista
     */
    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'IdIdentificador' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_identificador,IdIdentificador',
                'Comision' => 'required|numeric|min:0|max:100',
            ]);

            $comisionista = Comisionista::where('IdCliente', $clienteId)
                ->where('IdComisionista', $id)
                ->firstOrFail();

            // Verificar duplicado excluyendo el actual
            $existe = Comisionista::where('IdIdentificador', $request->IdIdentificador)
                ->where('IdCliente', $clienteId)
                ->where('IdComisionista', '!=', $id)
                ->exists();

            if ($existe) {
                return redirect()->back()->with('error', 'Este identificador ya está registrado como comisionista para esta empresa.');
            }

            $comisionista->update([
                'IdIdentificador' => $request->IdIdentificador,
                'Comision' => $request->Comision,
            ]);

            return redirect()->back()->with('success', 'Comisionista actualizado correctamente.');

        } catch (\Exception $e) {
            Log::error('Error actualizando comisionista: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar comisionista
     */
    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        
        try {
            $comisionista = Comisionista::where('IdCliente', $clienteId)
                ->where('IdComisionista', $id)
                ->firstOrFail();
            
            $nombre = $comisionista->identificador?->Nombre ?? 'Comisionista';
            $comisionista->delete();

            return redirect()->back()->with('success', "Comisionista '{$nombre}' eliminado correctamente.");

        } catch (\Exception $e) {
            Log::error('Error eliminando comisionista: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }
}