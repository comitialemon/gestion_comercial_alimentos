<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** Conexión Gestión */
    private function g() { return DB::connection('mysql_gestion_comercial_alimentos'); }
    
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

        // 🔥 CARGAR EMPRESA Y SUCURSAL DESDE BD SI NO ESTÁN EN SESIÓN 🔥
        $empresaNombre = session('global_empresa_nombre');
        $sucursalNombre = session('global_sucursal_nombre');
        
        if (!$empresaNombre && session('cliente_id')) {
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', session('cliente_id'))
                ->first();
            $empresaNombre = $empresa->Nombre ?? null;
            // Guardar en sesión para futuras peticiones
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

        $auth = [
            'operador' => [
                'id'      => (int) session('operador_id'),
                'nombre'  => (string) $operadorNombre,
                'tipo_id' => (int) session('operador_tipo_id'),
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

        // Menú
        $menu = [];
        if ($ctx['ready'] && !empty($auth['operador']['tipo_id'])) {
            $menu = $this->menuTreeFor(
                (int)$auth['operador']['tipo_id'],
                (int)$auth['operador']['id'],
                (int)$ctx['cliente_id']
            );
        }

        return array_merge(parent::share($request), [
            'auth'            => $auth,
            'ctx'             => $ctx,
            'menu'            => $menu,
            'empresaNombre'   => $empresaNombre,
            'sucursalNombre'  => $sucursalNombre,
            'operadorNombre'  => $operadorNombre,
        ]);
    }

    /* ====================== MENÚ ====================== */

    private function menuTreeFor(int $tipoId, int $operadorId, int $clienteId): array
    {
        $idsOp = $this->idsMenuPorOperador($operadorId, $clienteId);

        $modo = 'por_columna';
        if (!empty($idsOp)) $modo = 'por_operador';

        $columna = $modo === 'por_columna'
            ? $this->resolverColumnaMenu($tipoId)
            : '__POR_OPERADOR__';

        Log::info('MENU.resolver', [
            'tipoId'     => $tipoId,
            'operadorId' => $operadorId,
            'clienteId'  => $clienteId,
            'modo'       => $modo,
            'columna'    => $columna,
        ]);

        if ($columna === '__NONE__') {
            return [];
        }

        $cacheKey = $modo === 'por_operador'
            ? "menu_tree_op_{$operadorId}_cli_{$clienteId}"
            : "menu_tree_tipo_{$tipoId}_{$columna}";

        if (session()->has($cacheKey)) {
            return session($cacheKey);
        }

        $items = $modo === 'por_operador'
            ? $this->itemsPorOperadorIds($idsOp)
            : $this->itemsPorColumna($columna);

        Log::info('MENU.items', [
            'modo'   => $modo,
            'col'    => $columna,
            'count'  => is_array($items) ? count($items) : 0,
        ]);

        if (empty($items)) {
            session([$cacheKey => []]);
            return [];
        }

        $items = $this->asegurarPadres($items);
        $tree  = $this->aArbol($items);

        session([$cacheKey => $tree]);
        return $tree;
    }

    private function resolverColumnaMenu(int $tipoId): string
    {
        $detalle = $this->g()->table('todos_operador_tipo')
            ->where('IdOperadorTipo', $tipoId)
            ->value('Detalle');

        $detalle = is_string($detalle) ? trim($detalle) : '';

        $colsReal = $this->columnasMenu();
        $mapNorm  = [];
        foreach ($colsReal as $c) $mapNorm[$this->norm($c)] = $c;

        $exc = [
            $this->norm('SuperUsuario')         => 'Administrador',
            $this->norm('MenuPorOperador')      => '__POR_OPERADOR__',
            $this->norm('VentaMayoristas')      => 'VentaMayorista',
            $this->norm('VentaMonitorCocina')   => 'MonitorCocina',
            $this->norm('VentaMonitorEntregas') => 'MonitorCocina',
        ];

        $col = null;

        if ($detalle !== '') {
            $det = $this->norm($detalle);
            if (isset($exc[$det])) {
                $col = $exc[$det];
            } elseif (isset($mapNorm[$det])) {
                $col = $mapNorm[$det];
            }
        }

        if ($col === '__POR_OPERADOR__') {
            if (!Schema::connection('mysql_gestion_comercial_alimentos')->hasTable('menu_operador')) {
                return $this->fallbackPreferible($colsReal);
            }
            return $col;
        }

        if (!$col || !in_array($col, $colsReal, true)) {
            return $this->fallbackPreferible($colsReal);
        }

        return $col;
    }

    private function fallbackPreferible(array $colsReal): string
    {
        $normCols = [];
        foreach ($colsReal as $c) $normCols[$this->norm($c)] = $c;

        foreach (['Operador', 'Administrador', 'Usuario'] as $pref) {
            $n = $this->norm($pref);
            if (isset($normCols[$n])) return $normCols[$n];
        }
        return $colsReal[0] ?? '__NONE__';
    }

    private function norm(string $s): string
    {
        return Str::of($s)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();
    }

    private function columnasMenu(): array
    {
        $all = Schema::connection('mysql_gestion_comercial_alimentos')
            ->getColumnListing('menu_administrador');

        $excluir = [
            'Id','ID','id',
            'Description','Descripcion','description','descripcion',
            'Link','link',
            'Parent','parent',
            'Node_Order','node_order','Order','Orden',
        ];

        return array_values(array_filter($all, fn($c) => !in_array($c, $excluir, true)));
    }

    private function idsMenuPorOperador(int $operadorId, int $clienteId): array
    {
        $schema = Schema::connection('mysql_gestion_comercial_alimentos');
        if (!$schema->hasTable('menu_operador')) return [];

        return $this->g()->table('menu_operador')
            ->where('IdOperador', $operadorId)
            ->where('IdCliente',  $clienteId)
            ->pluck('IdMenu')
            ->map(fn($x) => (int)$x)
            ->all();
    }

    private function itemsPorOperadorIds(array $ids): array
    {
        if (empty($ids)) return [];

        $rows = $this->g()->table('menu_administrador')
            ->select([
                'Id as id',
                'Description as title',
                'Link as href',
                'Parent as parent',
                'Node_Order as node_order',
            ])
            ->whereIn('Id', $ids)
            ->orderBy('Parent')->orderBy('Node_Order')
            ->get();

        return $rows->map(fn($r) => (array)$r)->all();
    }

    private function itemsPorColumna(string $col): array
    {
        $rows = $this->g()->table('menu_administrador')
            ->select([
                'Id as id',
                'Description as title',
                'Link as href',
                'Parent as parent',
                'Node_Order as node_order',
            ])
            ->where($col, 1)
            ->orderBy('Parent')->orderBy('Node_Order')
            ->get();

        return $rows->map(fn($r) => (array)$r)->all();
    }

    private function asegurarPadres(array $items): array
    {
        $byId = collect($items)->keyBy('id');
        $conn = $this->g();

        $pending = collect($items)
            ->pluck('parent')
            ->filter(fn($p) => $p && !$byId->has($p))
            ->unique()
            ->values();

        while ($pending->isNotEmpty()) {
            $parents = $conn->table('menu_administrador')
                ->select([
                    'Id as id',
                    'Description as title',
                    'Link as href',
                    'Parent as parent',
                    'Node_Order as node_order',
                ])
                ->whereIn('Id', $pending)
                ->get()
                ->map(fn($r) => (array)$r);

            foreach ($parents as $p) {
                if (!$byId->has($p['id'])) $byId->put($p['id'], $p);
            }

            $pending = collect($parents)
                ->pluck('parent')
                ->filter(fn($p) => $p && !$byId->has($p))
                ->unique()
                ->values();
        }

        return $byId->values()->sortBy(['parent','node_order'])->values()->all();
    }

    private function aArbol(array $items): array
    {
        $group = collect($items)->groupBy('parent');

        $build = function($parent) use (&$build, $group) {
            return ($group->get($parent, collect()))
                ->sortBy('node_order')
                ->map(fn($i) => [
                    'id'       => $i['id'],
                    'title'    => $i['title'],
                    'href'     => $i['href'],
                    'children' => $build($i['id']),
                ])->values()->all();
        };

        return $build(0);
    }
}