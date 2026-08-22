<?php

namespace App\Services\Gestion\Menu;

use App\Models\Gestion\Menu\MenuAdministrador;
use App\Models\Gestion\Menu\MenuOperador;
use App\Models\Gestion\Todos\OperadorTipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class MenuService
{
    private $g;

    public function __construct()
    {
        $this->g = DB::connection('mysql_gestion_comercial_alimentos');
    }

    public function getMenuTreeForOperador(int $operadorId, int $clienteId, int $tipoId): array
    {
        // ✅ 1. Verificar si tiene menús personalizados
        $menuOperador = $this->getMenuIdsPorOperador($operadorId, $clienteId);

        if (!empty($menuOperador)) {
            Log::info('MENU.USANDO_PERSONALIZADO', [
                'operador_id' => $operadorId,
                'total_ids' => count($menuOperador)
            ]);
            $items = $this->getItemsPorIds($menuOperador);
            $items = $this->asegurarPadres($items);
            return $this->buildTree($items);
        }

        // ✅ 2. NO tiene menús personalizados → usar menú por TIPO
        Log::info('MENU.USANDO_POR_TIPO', [
            'operador_id' => $operadorId,
            'tipo_id' => $tipoId
        ]);

        $columna = $this->resolverColumnaPorTipo($tipoId);
        
        // ✅ Si no hay columna válida → MENÚ VACÍO
        if ($columna === '__NONE__') {
            Log::info('MENU.SIN_COLUMNA_VALIDA', [
                'operador_id' => $operadorId,
                'tipo_id' => $tipoId
            ]);
            return [];
        }

        $items = $this->getItemsPorColumna($columna);
        
        if (empty($items)) {
            Log::info('MENU.SIN_ITEMS_POR_COLUMNA', [
                'operador_id' => $operadorId,
                'columna' => $columna
            ]);
            return [];
        }

        $items = $this->asegurarPadres($items);
        return $this->buildTree($items);
    }

    public function getMenuCompleto(): array
    {
        $items = MenuAdministrador::select([
                'Id as id',
                'Description as title',
                'Link as href',
                'Parent as parent',
                'Node_Order as node_order'
            ])
            ->orderBy('Parent')
            ->orderBy('Node_Order')
            ->get()
            ->toArray();

        return $this->buildTree($items);
    }

    public function getMenuIdsPorOperador(int $operadorId, int $clienteId): array
    {
        return MenuOperador::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->pluck('IdMenu')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }

    /**
     * ✅ CORREGIDO: Resuelve columna, si no encuentra → '__NONE__'
     */
    private function resolverColumnaPorTipo(int $tipoId): string
    {
        $detalle = OperadorTipo::where('IdOperadorTipo', $tipoId)->value('Detalle');
        $detalle = is_string($detalle) ? trim($detalle) : '';

        if (empty($detalle)) {
            Log::warning('MENU.tipo_sin_detalle', ['tipo_id' => $tipoId]);
            return '__NONE__';
        }

        // ✅ MAPEO DE DETALLES A COLUMNAS REALES
        $mapeo = [
            'SuperUsuario' => 'SuperUsuario',
            'Informes' => 'Informes',
            'VentaMostrador' => 'VentaMostrador',
            'Produccion' => 'Produccion',
            'ControlInterno' => 'ControlInterno',
            'EstadoCuenta' => 'EstadoCuenta',
            'PedidoClientes' => 'PedidoClientes',
        ];

        $detalleNorm = $this->normalizar($detalle);
        
        foreach ($mapeo as $key => $columna) {
            if ($this->normalizar($key) === $detalleNorm) {
                $columnas = MenuAdministrador::getPermisoColumns();
                if (in_array($columna, $columnas)) {
                    Log::info('MENU.columna_resuelta', [
                        'tipo_id' => $tipoId,
                        'detalle' => $detalle,
                        'columna' => $columna
                    ]);
                    return $columna;
                }
                break;
            }
        }

        // 🔥 FALLBACK: Si no hay columna, devolver '__NONE__' (menú vacío)
        Log::warning('MENU.fallback_none', [
            'tipo_id' => $tipoId,
            'detalle' => $detalle
        ]);
        
        return '__NONE__';
    }

    private function normalizar(string $texto): string
    {
        return Str::of($texto)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->value();
    }

    private function getItemsPorIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return MenuAdministrador::select([
                'Id as id',
                'Description as title',
                'Link as href',
                'Parent as parent',
                'Node_Order as node_order'
            ])
            ->whereIn('Id', $ids)
            ->orderBy('Parent')
            ->orderBy('Node_Order')
            ->get()
            ->toArray();
    }

    private function getItemsPorColumna(string $columna): array
    {
        return MenuAdministrador::select([
                'Id as id',
                'Description as title',
                'Link as href',
                'Parent as parent',
                'Node_Order as node_order'
            ])
            ->where($columna, 1)
            ->orderBy('Parent')
            ->orderBy('Node_Order')
            ->get()
            ->toArray();
    }

    private function asegurarPadres(array $items): array
    {
        $byId = collect($items)->keyBy('id');
        $parentsToAdd = [];

        foreach ($items as $item) {
            $parent = $item['parent'];
            while ($parent > 0 && !$byId->has($parent)) {
                $parentsToAdd[] = $parent;
                $abuelo = MenuAdministrador::where('Id', $parent)->value('Parent');
                $parent = $abuelo ?: 0;
            }
        }

        if (!empty($parentsToAdd)) {
            $parents = MenuAdministrador::select([
                    'Id as id',
                    'Description as title',
                    'Link as href',
                    'Parent as parent',
                    'Node_Order as node_order'
                ])
                ->whereIn('Id', array_unique($parentsToAdd))
                ->get()
                ->toArray();

            $items = array_merge($items, $parents);
        }

        return $items;
    }

    private function buildTree(array $items, int $parentId = 0): array
    {
        $tree = [];
        
        foreach ($items as $item) {
            if ($item['parent'] == $parentId) {
                $children = $this->buildTree($items, $item['id']);
                $node = [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'href' => $item['href'],
                ];
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }

        usort($tree, function($a, $b) use ($items) {
            $orderA = 0;
            $orderB = 0;
            foreach ($items as $item) {
                if ($item['id'] == $a['id']) $orderA = $item['node_order'];
                if ($item['id'] == $b['id']) $orderB = $item['node_order'];
            }
            return $orderA <=> $orderB;
        });

        return $tree;
    }

    public function obtenerArbol(int $tipoId, int $operadorId, int $clienteId): array
    {
        $menuOperador = $this->getMenuIdsPorOperador($operadorId, $clienteId);

        $modo = !empty($menuOperador) ? 'personalizado' : 'por_tipo';

        $version = Cache::rememberForever('menu_global_version', fn() => time());
        
        $cacheKey = "menu_v{$version}_{$modo}_" . (
            $modo === 'personalizado' 
                ? "op_{$operadorId}_cli_{$clienteId}" 
                : "tipo_{$tipoId}"
        );

        return Cache::remember($cacheKey, 86400, function () use ($tipoId, $operadorId, $clienteId, $modo, $menuOperador) {
            Log::info('MENU.regenerando_cache', ['modo' => $modo]);
            
            if ($modo === 'personalizado') {
                $items = $this->getItemsPorIds($menuOperador);
            } else {
                $columna = $this->resolverColumnaPorTipo($tipoId);
                if ($columna === '__NONE__') {
                    return [];
                }
                $items = $this->getItemsPorColumna($columna);
            }

            if (empty($items)) {
                return [];
            }

            $items = $this->asegurarPadres($items);
            return $this->buildTree($items);
        });
    }

    public static function invalidarCache(): void
    {
        $nuevaVersion = time();
        Cache::forever('menu_global_version', $nuevaVersion);
        Log::info('MENU.cache_invalidada', ['nueva_version' => $nuevaVersion]);
    }
}