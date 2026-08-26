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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Obtener el IdOperadorTipo para "MenuPorOperador" dinámicamente
     */
    private function getIdOperadorTipoMenuPorOperador()
    {
        $tipo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador_tipo')
            ->where('Detalle', 'MenuPorOperador')
            ->first();
        
        if (!$tipo) {
            throw new \Exception('No se encontró el tipo de operador "MenuPorOperador"');
        }
        
        return $tipo->IdOperadorTipo;
    }

    /**
     * Muestra la vista de asignación de menús
     * SOLO operadores de tipo "MenuPorOperador"
     */
    public function index(Request $request)
    {
        $clienteId = $request->session()->get('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        $empresa = Cliente::find($clienteId);
        if (!$empresa) {
            return redirect()->route('contexto.index')
                ->with('error', 'La empresa seleccionada no existe');
        }

        // 🔥 PASO 1: Obtener el ID del tipo "MenuPorOperador"
        $tipo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador_tipo')
            ->where('Detalle', 'MenuPorOperador')
            ->first();
        
        if (!$tipo) {
            return redirect()->back()
                ->with('error', 'No se encontró el tipo de operador "MenuPorOperador"');
        }
        
        $idTipoMenuPorOperador = $tipo->IdOperadorTipo;

        // 🔥 ========== DEPURACIÓN COMPLETA ==========
        \Log::info('=== DEPURACIÓN ASIGNAR MENÚ ===');
        \Log::info('Cliente ID: ' . $clienteId);
        \Log::info('ID Tipo MenuPorOperador: ' . $idTipoMenuPorOperador);

        // 1. Verificar operadores de tipo MenuPorOperador (sin importar empresa)
        $operadoresTipo = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->where('IdOperadorTipo', $idTipoMenuPorOperador)
            ->where('ActivoInactivo', 0)
            ->get();
        
        \Log::info('Operadores con tipo ' . $idTipoMenuPorOperador . ' y ACTIVOS (ActivoInactivo=0): ' . $operadoresTipo->count());
        foreach ($operadoresTipo as $op) {
            \Log::info('  - ID: ' . $op->IdOperador);
        }

        // 2. Verificar operadores asignados a la empresa (con sus sucursales)
        $operadoresAsignados = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador_sucursaldb as os')
            ->join('todos_cliente_sucursal as cs', 'os.IdSucursal', '=', 'cs.IdClienteSucursal')
            ->where('cs.IdCliente', $clienteId)
            ->get();
        
        \Log::info('Operadores asignados a empresa ' . $clienteId . ': ' . $operadoresAsignados->count());
        foreach ($operadoresAsignados as $op) {
            \Log::info('  - ID: ' . $op->IdOperador . ', Sucursal ID: ' . $op->IdSucursal);
        }

        // 3. Obtener IDs de operadores de tipo MenuPorOperador
        $idsTipo = $operadoresTipo->pluck('IdOperador')->toArray();
        \Log::info('IDs de tipo MenuPorOperador: ' . implode(',', $idsTipo));

        // 4. Obtener IDs de operadores asignados a la empresa
        $idsAsignados = $operadoresAsignados->pluck('IdOperador')->toArray();
        \Log::info('IDs asignados a empresa: ' . implode(',', $idsAsignados));

        // 5. Intersección (operadores que cumplen ambas condiciones)
        $idsIntersect = array_intersect($idsTipo, $idsAsignados);
        \Log::info('IDs que cumplen ambas condiciones: ' . count($idsIntersect));
        \Log::info('IDs: ' . implode(',', $idsIntersect));
        // =============================================

        // 🔥 PASO 2: Usar ese ID en la consulta principal
        $operadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->join('todos_operador_sucursaldb as os', 'o.IdOperador', '=', 'os.IdOperador')
            ->join('todos_cliente_sucursal as cs', 'os.IdSucursal', '=', 'cs.IdClienteSucursal')
            ->where('cs.IdCliente', $clienteId)
            ->where('o.ActivoInactivo', 0)
            ->where('o.IdOperadorTipo', $idTipoMenuPorOperador)
            ->select(
                'o.IdOperador as id',
                'i.Nombre as nombre',
                'i.CI_NIT as ci',
                'o.IdOperadorTipo as tipo'
            )
            ->orderBy('i.Nombre')
            ->distinct()
            ->get()
            ->map(fn($op) => [
                'id' => $op->id,
                'nombre' => $op->nombre ?? 'Sin nombre',
                'ci' => $op->ci ?? '',
                'tipo' => $op->tipo,
            ]);

        \Log::info('RESULTADO FINAL: ' . $operadores->count() . ' operadores encontrados');

        $menuCompleto = $this->menuService->getMenuCompleto();

        return Inertia::render('Gestion/Menu/AsignarMenu', [
            'operadores' => $operadores,
            'menuCompleto' => $menuCompleto,
            'clienteId' => $clienteId,
            'total_operadores' => $operadores->count()
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
     * 🔥 GUARDA LA ASIGNACIÓN DE MENÚS - CON FLASH MESSAGES PARA TOAST
     */
    public function store(AsignarMenuRequest $request)
    {
        try {
            // Obtener datos del request
            $operadorId = $request->input('operador_id');
            $clienteId = $request->input('cliente_id');
            $menuIds = $request->input('menus', []);

            // Validación adicional
            if (!$operadorId) {
                return redirect()->back()->with('error', 'Operador no especificado');
            }

            if (!$clienteId) {
                return redirect()->back()->with('error', 'Cliente no especificado');
            }

            // Log para depuración
            Log::info('=== ASIGNACIÓN DE MENÚS RECIBIDA ===');
            Log::info('Operador ID: ' . $operadorId);
            Log::info('Cliente ID: ' . $clienteId);
            Log::info('Menús IDs: ' . json_encode($menuIds));
            Log::info('Total menús: ' . count($menuIds));

            // Si no hay menús, enviar array vacío
            if (!is_array($menuIds)) {
                $menuIds = [];
            }

            // 🔥 Llamar al servicio para asignar
            $this->menuOperadorService->asignarMenusConPadres($operadorId, $clienteId, $menuIds);

            // Limpiar cache del menú para este operador
            $cacheKey = "menu_tree_op_{$operadorId}_cli_{$clienteId}";
            session()->forget($cacheKey);

            Log::info('✅ Menús asignados correctamente', [
                'operador_id' => $operadorId,
                'total' => count($menuIds)
            ]);

            // ✅ REDIRECCIONAR CON MENSAJE FLASH PARA TOAST
            return redirect()->back()->with('success', 'Menús asignados correctamente (' . count($menuIds) . ' menús)');

        } catch (\Exception $e) {
            Log::error('❌ Error al asignar menús: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Error al asignar menús: ' . $e->getMessage());
        }
    }
}