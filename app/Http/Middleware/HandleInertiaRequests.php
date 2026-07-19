<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\DB;
use App\Services\Gestion\Menu\MenuService;
use App\Models\Gestion\Todos\ClienteTema;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    protected $menuService;
    
    public function __construct(MenuService $menuService)
    {
        $this->menuService = $menuService;
    }
    
    public function share(Request $request): array
    {
        // Obtener el nombre completo del operador
        $operadorNombre = session('operador_nombre');
        
        if (!$operadorNombre && session('operador_id')) {
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', session('operador_id'))
                ->first();
            $operadorNombre = $operador->Nombre ?? session('operador_nombre');
        }

        // Cargar empresa y sucursal desde BD si no están en sesión
        $empresaNombre = session('global_empresa_nombre');
        $sucursalNombre = session('global_sucursal_nombre');
        
        if (!$empresaNombre && session('cliente_id')) {
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', session('cliente_id'))
                ->first();
            $empresaNombre = $empresa->Nombre ?? null;
            if ($empresaNombre) session(['global_empresa_nombre' => $empresaNombre]);
        }
        
        if (!$sucursalNombre && session('cliente_sucursal_id')) {
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', session('cliente_sucursal_id'))
                ->first();
            $sucursalNombre = $sucursal->Nombre ?? null;
            if ($sucursalNombre) session(['global_sucursal_nombre' => $sucursalNombre]);
        }

        // 🔥 TEMA DINÁMICO POR CLIENTE
        $theme = $this->loadTheme(session('cliente_id'));

        // 🔥 Obtener el detalle del tipo de operador (para saber si es vendedor)
        $operadorTipoDetalle = null;
        if (session('operador_tipo_id')) {
            $tipo = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador_tipo')
                ->where('IdOperadorTipo', session('operador_tipo_id'))
                ->first();
            $operadorTipoDetalle = $tipo->Detalle ?? null;
        }

        $auth = [
            'operador' => [
                'id'      => (int) session('operador_id'),
                'nombre'  => (string) $operadorNombre,
                'tipo_id' => (int) session('operador_tipo_id'),
                'tipo_detalle' => $operadorTipoDetalle,
            ],
        ];

        $ctx = [
            'cliente_id'             => session('cliente_id'),
            'cliente_sucursal_id'    => session('cliente_sucursal_id'),
            'global_empresa_nombre'  => session('global_empresa_nombre'),
            'global_sucursal_nombre' => session('global_sucursal_nombre'),
            'global_sucursal_numero' => session('global_sucursal_numero'),
            'ready' => (bool) (session()->has('cliente_id') && session()->has('cliente_sucursal_id')),
        ];

        // 🔥 MENÚ
        $menu = [];
        if ($ctx['ready'] && !empty($auth['operador']['tipo_id'])) {
            $menu = $this->menuService->obtenerArbol(
                (int)$auth['operador']['tipo_id'],
                (int)$auth['operador']['id'],
                (int)$ctx['cliente_id']
            );
        }

        // 🔥 ZONA HORARIA - DEFINIRLA AQUÍ
        $zonaHoraria = session('zona_horaria', config('app.timezone', 'America/La_Paz'));

        return array_merge(parent::share($request), [
            'auth'            => $auth,
            'ctx'             => $ctx,
            'menu'            => $menu,
            'empresaNombre'   => $empresaNombre,
            'sucursalNombre'  => $sucursalNombre,
            'operadorNombre'  => $operadorNombre,
            'theme'           => $theme,
            'zonaHoraria'     => $zonaHoraria, // ✅ AHORA ESTÁ DEFINIDA
        ]);
    }

    /**
     * 🔥 Carga el tema del cliente (colores, logo, etc.)
     */
    private function loadTheme($clienteId): array
    {
        // Tema por defecto
        $defaultTheme = [
            'primary' => '#1f2937',
            'primary_rgb' => '31, 41, 55',
            'secondary' => '#4b5563',
            'secondary_rgb' => '75, 85, 99',
            'accent' => '#6b7280',
            'accent_rgb' => '107, 114, 128',
            'background' => '#ffffff',
            'text_dark' => '#111827',
            'text_light' => '#ffffff',
            'logo' => null,
            'systemName' => 'Sistema Gestion',
            'hasCustomTheme' => false,
        ];

        if (!$clienteId) {
            return $defaultTheme;
        }

        try {
            $tema = ClienteTema::where('id_cliente', $clienteId)
                ->where('activo', 1)
                ->first();

            if (!$tema) {
                return $defaultTheme;
            }

            return [
                'primary' => $tema->color_principal ?? '#1f2937',
                'primary_rgb' => $this->hexToRgb($tema->color_principal ?? '#1f2937'),
                'secondary' => $tema->color_secundario ?? '#4b5563',
                'secondary_rgb' => $this->hexToRgb($tema->color_secundario ?? '#4b5563'),
                'accent' => $tema->color_acento ?? '#6b7280',
                'accent_rgb' => $this->hexToRgb($tema->color_acento ?? '#6b7280'),
                'background' => $tema->color_fondo ?? '#ffffff',
                'text_dark' => $tema->color_texto_oscuro ?? '#111827',
                'text_light' => $tema->color_texto_claro ?? '#ffffff',
                'logo' => $tema->logo_url ?? null,
                'systemName' => $tema->nombre_sistema ?? 'Sistema Gestion',
                'hasCustomTheme' => true,
            ];

        } catch (\Exception $e) {
            \Log::error('Error cargando tema: ' . $e->getMessage());
            return $defaultTheme;
        }
    }

    /**
     * Convierte color HEX a RGB
     */
    private function hexToRgb($hex): string
    {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        return "{$r}, {$g}, {$b}";
    }
}