<?php

namespace App\Services\Gestion\Menu;

use App\Models\Gestion\Menu\MenuAdministrador;
use App\Models\Gestion\Menu\MenuOperador;
use App\Models\Gestion\Todos\OperadorTipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;  // 🔥 AGREGAR
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MenuService
{
    private $g;

    public function __construct()
    {
        $this->g = DB::connection('mysql_gestion_comercial_alimentos');
    }

    /**
     * 🔥 NUEVO: Obtiene el árbol de menú con CACHÉ POR VERSIÓN
     * Este es el método que llamará el middleware
     */
    public function obtenerArbol(int $tipoId, int $operadorId, int $clienteId): array
    {
        // Verificar si tiene menús asignados individualmente
        $menuOperador = $this->getMenuIdsPorOperador($operadorId, $clienteId);
        $modo = !empty($menuOperador) ? 'por_operador' : 'por_columna';

        $columna = $modo === 'por_columna'
            ? $this->resolverColumnaPorTipo($tipoId)
            : '__POR_OPERADOR__';

        if ($columna === '__NONE__') {
            return [];
        }

        // 🔥 CLAVE DE CACHÉ ATÓMICA POR VERSIÓN
        // La versión global permite invalidar TODA la caché del menú de un solo golpe
        $version = Cache::rememberForever('menu_global_version', fn() => time());
        
        $cacheKey = "menu_v{$version}_{$modo}_" . (
            $modo === 'por_operador' 
                ? "op_{$operadorId}_cli_{$clienteId}" 
                : "tipo_{$tipoId}_{$columna}"
        );

        // Cache por 24 horas (86400 segundos)
        // Si se invalida la versión, esta clave queda huérfana y expira sola
        return Cache::remember($cacheKey, 86400, function () use ($modo, $menuOperador, $columna) {
            Log::info('MENU.regenerando_cache', ['modo' => $modo]);
            
            if ($modo === 'por_operador') {
                $items = $this->getItemsPorIds($menuOperador);
            } else {
                $items = $this->getItemsPorColumna($columna);
            }

            if (empty($items)) {
                return [];
            }

            $items = $this->asegurarPadres($items);
            return $this->buildTree($items);
        });
    }

    /**
     * 🔥 NUEVO: Invalida la caché del menú globalmente
     * Solo incrementa la versión, no necesita borrar claves una por una
     */
    public static function invalidarCache(): void
    {
        $nuevaVersion = time();
        Cache::forever('menu_global_version', $nuevaVersion);
        Log::info('MENU.cache_invalidada', ['nueva_version' => $nuevaVersion]);
    }

    /**
     * Método original (lo mantienes para compatibilidad, pero puedes deprecarlo)
     */
    public function getMenuTreeForOperador(int $operadorId, int $clienteId, int $tipoId): array
    {
        // Delegar al nuevo método
        return $this->obtenerArbol($tipoId, $operadorId, $clienteId);
    }

    /**
     * Obtiene el árbol completo de menús (para asignación)
     * Este NO necesita caché porque solo lo usan administradores
     */
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

    /**
     * Obtiene IDs de menú asignados a un operador
     */
    public function getMenuIdsPorOperador(int $operadorId, int $clienteId): array
    {
        return MenuOperador::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->pluck('IdMenu')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }

    /**
     * Resuelve la columna de permiso según el tipo de operador
     */
    private function resolverColumnaPorTipo(int $tipoId): string
    {
        $detalle = OperadorTipo::where('IdOperadorTipo', $tipoId)->value('Detalle');
        $detalle = is_string($detalle) ? trim($detalle) : '';

        if (empty($detalle)) {
            return 'Administrador';
        }

        $detalleNorm = $this->normalizar($detalle);
        
        $excepciones = [
            $this->normalizar('SuperUsuario') => 'Administrador',
            $this->normalizar('VentaMayoristas') => 'VentaMayorista',
            $this->normalizar('VentaMonitorCocina') => 'MonitorCocina',
            $this->normalizar('VentaMonitorEntregas') => 'MonitorCocina',
        ];

        if (isset($excepciones[$detalleNorm])) {
            return $excepciones[$detalleNorm];
        }

        $columnas = MenuAdministrador::getPermisoColumns();
        $columnasNorm = [];
        foreach ($columnas as $col) {
            $columnasNorm[$this->normalizar($col)] = $col;
        }

        if (isset($columnasNorm[$detalleNorm])) {
            return $columnasNorm[$detalleNorm];
        }

        return 'Administrador';
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
}