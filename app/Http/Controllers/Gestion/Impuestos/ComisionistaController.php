<?php
// app/Http/Controllers/Gestion/Impuestos/ComisionistaController.php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Comisionista;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ComisionistaController extends Controller
{
    /**
     * Listado de comisionistas
     */
    public function index(Request $request)
    {
        $query = Comisionista::with(['identificador', 'cliente']);

        // Filtrar por cliente (empresa)
        if ($request->filled('cliente_id')) {
            $query->where('IdCliente', $request->cliente_id);
        }

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

        // Para el filtro de empresas
        $empresas = Cliente::orderBy('Nombre')->get(['IdCliente as id', 'Nombre as nombre']);

        return Inertia::render('Gestion/Impuestos/Comisionista/Index', [
            'comisionistas' => $comisionistas,
            'filtros' => [
                'cliente_id' => $request->cliente_id,
                'search' => $request->search,
            ],
            'empresas' => $empresas,
            'contexto_actual' => [
                'cliente_id' => session('cliente_id'),
                'cliente_nombre' => session('cliente_nombre'),
            ],
        ]);
    }

    /**
     * Mostrar formulario para crear
     */
    public function create()
    {
        $empresas = Cliente::orderBy('Nombre')->get(['IdCliente as id', 'Nombre as nombre']);
        
        // Búsqueda de identificadores (para el select con búsqueda)
        $identificadores = Identificador::orderBy('Nombre')
            ->limit(50)
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        return Inertia::render('Gestion/Impuestos/Comisionista/Create', [
            'empresas' => $empresas,
            'identificadores' => $identificadores,
            'defaults' => [
                'IdCliente' => session('cliente_id'),
            ],
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
        $request->validate([
            'IdIdentificador' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_identificador,IdIdentificador',
            'Comision' => 'required|numeric|min:0|max:100',
            'IdCliente' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_cliente,IdCliente',
        ]);

        // Verificar si ya existe para este cliente
        $existe = Comisionista::where('IdIdentificador', $request->IdIdentificador)
            ->where('IdCliente', $request->IdCliente)
            ->exists();

        if ($existe) {
            return back()->with('error', 'Este identificador ya está registrado como comisionista para esta empresa.');
        }

        Comisionista::create([
            'IdIdentificador' => $request->IdIdentificador,
            'Comision' => $request->Comision,
            'IdCliente' => $request->IdCliente,
        ]);

        return redirect()->route('gestion.comisionista.index')
            ->with('success', 'Comisionista creado correctamente.');
    }

    /**
     * Mostrar formulario para editar
     */
    public function edit($id)
    {
        $comisionista = Comisionista::with(['identificador', 'cliente'])->findOrFail($id);
        
        $empresas = Cliente::orderBy('Nombre')->get(['IdCliente as id', 'Nombre as nombre']);
        
        $identificadores = Identificador::orderBy('Nombre')
            ->limit(50)
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        return Inertia::render('Gestion/Impuestos/Comisionista/Create', [
            'comisionista' => $comisionista,
            'empresas' => $empresas,
            'identificadores' => $identificadores,
            'editando' => true,
        ]);
    }

    /**
     * Actualizar comisionista
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'IdIdentificador' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_identificador,IdIdentificador',
            'Comision' => 'required|numeric|min:0|max:100',
            'IdCliente' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_cliente,IdCliente',
        ]);

        $comisionista = Comisionista::findOrFail($id);

        // Verificar duplicado excluyendo el actual
        $existe = Comisionista::where('IdIdentificador', $request->IdIdentificador)
            ->where('IdCliente', $request->IdCliente)
            ->where('IdComisionista', '!=', $id)
            ->exists();

        if ($existe) {
            return back()->with('error', 'Este identificador ya está registrado como comisionista para esta empresa.');
        }

        $comisionista->update([
            'IdIdentificador' => $request->IdIdentificador,
            'Comision' => $request->Comision,
            'IdCliente' => $request->IdCliente,
        ]);

        return redirect()->route('gestion.comisionista.index')
            ->with('success', 'Comisionista actualizado correctamente.');
    }

    /**
     * Eliminar comisionista
     */
    public function destroy($id)
    {
        $comisionista = Comisionista::findOrFail($id);
        $comisionista->delete();

        return redirect()->route('gestion.comisionista.index')
            ->with('success', 'Comisionista eliminado correctamente.');
    }
}