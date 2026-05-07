<?php

namespace App\Http\Controllers\Gestion\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\Menu\AsignarMenuRequest;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\Cliente;
use App\Services\Gestion\Menu\MenuService;
use App\Services\Gestion\Menu\MenuOperadorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AsignarMenuController extends Controller
{
    private $menuService;
    private $menuOperadorService;

    public function __construct(
        MenuService $menuService,
        MenuOperadorService $menuOperadorService
    ) {
        $this->menuService = $menuService;
        $this->menuOperadorService = $menuOperadorService;
    }

    /**
     * Muestra la vista de asignación de menús
     */
    public function index(Request $request)
    {
        $clienteId = $request->session()->get('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        // Verificar que la empresa existe
        $empresa = Cliente::find($clienteId);
        if (!$empresa) {
            return redirect()->route('contexto.index')
                ->with('error', 'La empresa seleccionada no existe');
        }

        // Obtener operadores de esta empresa usando el modelo
        $operadores = Operador::whereHas('empresas', function($query) use ($clienteId) {
                $query->where('todos_cliente.IdCliente', $clienteId);
            })
            ->with('identificador')
            ->get()
            ->map(fn($op) => [
                'id' => $op->IdOperador,
                'nombre' => $op->identificador?->Nombre ?? 'Sin nombre',
                'ci' => $op->identificador?->CI_NIT ?? ''
            ]);

        // Obtener árbol completo de menús
        $menuCompleto = $this->menuService->getMenuCompleto();

        return Inertia::render('Gestion/Menu/AsignarMenu', [
            'operadores' => $operadores,
            'menuCompleto' => $menuCompleto,
            'clienteId' => $clienteId
        ]);
    }

    /**
     * Obtiene los menús asignados a un operador específico
     */
    public function getAsignados(Request $request, int $operadorId)
    {
        $clienteId = $request->session()->get('cliente_id');
        
        if (!$clienteId) {
            return response()->json(['error' => 'No hay cliente seleccionado'], 400);
        }

        $asignados = $this->menuOperadorService->obtenerMenusAsignados($operadorId, $clienteId);

        return response()->json($asignados);
    }

    /**
     * Guarda la asignación de menús
     */
    public function store(AsignarMenuRequest $request)
    {
        $clienteId = $request->session()->get('cliente_id');
        
        if (!$clienteId) {
            return back()->with('error', 'No hay cliente seleccionado');
        }

        $operadorId = $request->input('operador_id');
        $menuIds = $request->input('menus', []);

        try {
            $this->menuOperadorService->asignarMenusConPadres($operadorId, $clienteId, $menuIds);

            // Limpiar cache del menú para este operador
            $cacheKey = "menu_tree_op_{$operadorId}_cli_{$clienteId}";
            session()->forget($cacheKey);

            return redirect()->back()
                ->with('success', 'Menús asignados correctamente');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Error al asignar menús: ' . $e->getMessage());
        }
    }
}