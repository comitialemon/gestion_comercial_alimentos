<?php

namespace App\Services\Gestion\Menu;

use App\Models\Gestion\Menu\MenuAdministrador;
use App\Models\Gestion\Menu\MenuOperador;
use App\Models\Gestion\Todos\OperadorTipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class MenuOperadorService
{
    private $g;

    public function __construct()
    {
        $this->g = DB::connection('mysql_gestion_comercial_alimentos');
    }

    // =============================================
    // MÉTODOS PARA ASIGNACIÓN DE MENÚS
    // =============================================

    /**
     * Asignar menús a un operador (incluye padres automáticamente)
     */
    public function asignarMenusConPadres(int $operadorId, int $clienteId, array $menuIds): void
    {
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            // 1. Obtener todos los menús incluyendo padres
            $todosLosMenus = $this->obtenerMenusConPadres($menuIds);

            // 2. Eliminar asignaciones existentes
            MenuOperador::where('IdOperador', $operadorId)
                ->where('IdCliente', $clienteId)
                ->delete();

            // 3. Insertar nuevas asignaciones
            foreach ($todosLosMenus as $menuId) {
                MenuOperador::create([
                    'IdMenu' => $menuId,
                    'IdCliente' => $clienteId,
                    'IdOperador' => $operadorId,
                ]);
            }

            // 4. Invalidar caché
            MenuOperadorService::invalidarCache();

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('Menús asignados correctamente', [
                'operador_id' => $operadorId,
                'cliente_id' => $clienteId,
                'menus_asignados' => $todosLosMenus,
                'total' => count($todosLosMenus)
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al asignar menús: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener todos los menús incluyendo sus padres
     */
    private function obtenerMenusConPadres(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }

        $resultados = [];
        $porProcesar = $menuIds;

        while (!empty($porProcesar)) {
            $actual = array_shift($porProcesar);
            
            // Si ya está en la lista, saltar
            if (in_array($actual, $resultados)) {
                continue;
            }
            
            // Obtener el menú y su padre
            $menu = MenuAdministrador::select('Id', 'Parent')
                ->where('Id', $actual)
                ->first();
            
            if (!$menu) {
                continue;
            }
            
            // Agregar el menú actual
            $resultados[] = $actual;
            
            // Si tiene padre y no está en la lista, agregarlo para procesar
            if ($menu->Parent > 0 && !in_array($menu->Parent, $resultados)) {
                $porProcesar[] = $menu->Parent;
            }
        }

        return $resultados;
    }

    /**
     * Obtiene los menús asignados a un operador (IDs)
     */
    public function obtenerMenusAsignados(int $operadorId, int $clienteId): array
    {
        return MenuOperador::where('IdOperador', $operadorId)
            ->where('IdCliente', $clienteId)
            ->pluck('IdMenu')
            ->map(fn($id) => (int)$id)
            ->toArray();
    }

    /**
     * Invalida la caché del menú globalmente
     */
    public static function invalidarCache(): void
    {
        $nuevaVersion = time();
        Cache::forever('menu_global_version', $nuevaVersion);
        Log::info('MENU.cache_invalidada', ['nueva_version' => $nuevaVersion]);
    }

    // =============================================
    // MÉTODOS PARA OBTENER ÁRBOL DE MENÚS
    // =============================================

    /**
     * Obtiene el árbol de menú con CACHÉ POR VERSIÓN
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

        // CLAVE DE CACHÉ ATÓMICA POR VERSIÓN
        $version = Cache::rememberForever('menu_global_version', fn() => time());
        
        $cacheKey = "menu_v{$version}_{$modo}_" . (
            $modo === 'por_operador' 
                ? "op_{$operadorId}_cli_{$clienteId}" 
                : "tipo_{$tipoId}_{$columna}"
        );

        // Cache por 24 horas (86400 segundos)
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
     * Obtiene el árbol completo de menús (para asignación en administración)
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