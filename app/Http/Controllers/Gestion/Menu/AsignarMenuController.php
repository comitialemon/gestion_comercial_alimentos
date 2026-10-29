<?php

namespace App\Http\Controllers\Gestion\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\Menu\AsignarMenuRequest;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Menu\Notificaciones\NotificacionTipo;
use App\Models\Gestion\Menu\Notificaciones\OperadorNotificacion;
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

        $menuCompleto = $this->menuService->getMenuCompleto();

        // 🔥 PASO 3: Traer los tipos de notificaciones disponibles
        $notificacionesTipos = NotificacionTipo::where('ActivoInactivo', 1)->get();

        return Inertia::render('Gestion/Menu/AsignarMenu', [
            'operadores' => $operadores,
            'menuCompleto' => $menuCompleto,
            'clienteId' => $clienteId,
            'notificacionesTipos' => $notificacionesTipos, // 🔥 SE LO PASAMOS A LA VISTA
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
     * 🔥 NUEVO: Obtiene las notificaciones asignadas a un operador
     */
    public function getNotificaciones(Request $request, int $operadorId)
    {
        $clienteId = $request->session()->get('cliente_id');
        
        if (!$clienteId) {
            return response()->json(['error' => 'No hay cliente seleccionado'], 400);
        }

        $asignadas = OperadorNotificacion::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->pluck('IdNotificacionTipo')
            ->toArray();

        return response()->json($asignadas);
    }

    /**
     * 🔥 GUARDA LA ASIGNACIÓN DE MENÚS Y NOTIFICACIONES
     */
    public function store(AsignarMenuRequest $request)
    {
        try {
            // Obtener datos del request
            $operadorId = $request->input('operador_id');
            $clienteId = $request->input('cliente_id');
            $menuIds = $request->input('menus', []);
            $notificacionesIds = $request->input('notificaciones', []); // 🔥 NUEVO

            // Validación adicional
            if (!$operadorId) {
                return redirect()->back()->with('error', 'Operador no especificado');
            }

            if (!$clienteId) {
                return redirect()->back()->with('error', 'Cliente no especificado');
            }

            // Si no hay menús, enviar array vacío
            if (!is_array($menuIds)) {
                $menuIds = [];
            }

            // Si no hay notificaciones, enviar array vacío
            if (!is_array($notificacionesIds)) {
                $notificacionesIds = [];
            }

            // 1. GUARDAR MENÚS
            $this->menuOperadorService->asignarMenusConPadres($operadorId, $clienteId, $menuIds);

            // 2. GUARDAR NOTIFICACIONES
            // Primero borramos las asignaciones anteriores de este operador en esta empresa
            OperadorNotificacion::where('IdOperador', $operadorId)
                ->where('IdCliente', $clienteId)
                ->delete();

            // Luego insertamos las nuevas
            foreach ($notificacionesIds as $idNotif) {
                OperadorNotificacion::create([
                    'IdOperador' => $operadorId,
                    'IdNotificacionTipo' => $idNotif,
                    'IdCliente' => $clienteId,
                    'ActivoInactivo' => 1,
                    'IdOperadorInserta' => $request->session()->get('operador_id'),
                    'FechaInserta' => now(),
                ]);
            }

            // Limpiar cache del menú para este operador
            $cacheKey = "menu_tree_op_{$operadorId}_cli_{$clienteId}";
            session()->forget($cacheKey);

            Log::info('✅ Menús y notificaciones asignados correctamente', [
                'operador_id' => $operadorId,
                'total_menus' => count($menuIds),
                'total_notificaciones' => count($notificacionesIds)
            ]);

            // ✅ REDIRECCIONAR CON MENSAJE FLASH PARA TOAST
            return redirect()->back()->with('success', 'Asignación guardada correctamente');

        } catch (\Exception $e) {
            Log::error('❌ Error al asignar menús/notificaciones: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with('error', 'Error al guardar la asignación: ' . $e->getMessage());
        }
    }
}