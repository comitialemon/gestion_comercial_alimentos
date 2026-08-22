<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class MenuController extends Controller
{
    private function g() {
        return DB::connection('mysql_gestion_comercial_alimentos');
    }

    public function index(Request $request)
    {
        $operadorId = (int) $request->session()->get('operador_id');
        $tipoId     = (int) $request->session()->get('operador_tipo_id');
        $usuario    = (string) $request->session()->get('operador_nombre', 'Usuario');

        if (!$operadorId || !$tipoId) {
            return Inertia::render('Menu/Index', ['menu' => [], 'usuario' => $usuario]);
        }

        // 1) Detalle del tipo
        $detalleTipo = $this->g()
            ->table('todos_operador_tipo')
            ->where('IdOperadorTipo', $tipoId)
            ->value('Detalle');

        // 2) Resolver nombre de columna en menu_administrador
        $colPerm = $this->resolverColumnaPermiso($detalleTipo);

        // 3) Traer ítems donde esa columna = 1
        $items = $this->itemsPorColumna($colPerm);

        // 4) Asegurar padres
        $items = $this->conPadres($items);

        // 5) Pasar a árbol
        $menu = $this->aArbol($items);

        return Inertia::render('Menu/Index', [
            'menu'    => $menu,
            'usuario' => $usuario,
        ]);
    }

    /**
     * 🔥 CORREGIDO: Convierte el Detalle a nombre de columna
     */
    private function resolverColumnaPermiso(?string $detalleTipo): string
    {
        if (!$detalleTipo) {
            Log::warning('MENU.detalle_tipo_vacio', ['tipo_id' => session('operador_tipo_id')]);
            return 'SuperUsuario';
        }

        $normal = preg_replace('/\s+/', '', $detalleTipo);

        // ✅ MAPEO DE DETALLES A COLUMNAS REALES
        $mapeo = [
            'SuperUsuario' => 'SuperUsuario',
            'Informes' => 'Informes',
            'VentaMostrador' => 'VentaMostrador',
            'Produccion' => 'Produccion',
            'ControlInterno' => 'ControlInterno',
            'EstadoCuenta' => 'EstadoCuenta',
            'PedidoClientes' => 'PedidoClientes', // ✅ NUEVO
            // Mantener compatibilidad con nombres antiguos
            'Administrador' => 'SuperUsuario',
            'VentaMonitorCocina' => 'MonitorCocina',
            'VentaMonitorEntregas' => 'MonitorEntregas',
            'VentaMayoristas' => 'VentaMayorista',
        ];

        $col = $mapeo[$normal] ?? $normal;

        // ✅ Verificar que la columna existe
        if (!Schema::connection('mysql_gestion_comercial_alimentos')->hasColumn('menu_administrador', $col)) {
            Log::warning('MENU.columna_no_existe', [
                'detalle' => $detalleTipo,
                'columna_intentada' => $col,
                'columna_usada' => 'SuperUsuario'
            ]);
            return 'SuperUsuario';
        }

        Log::info('MENU.columna_resuelta', [
            'detalle' => $detalleTipo,
            'columna' => $col
        ]);

        return $col;
    }

    private function itemsPorColumna(string $colPerm): array
    {
        return DB::connection('mysql_gestion_comercial_alimentos')
            ->table('menu_administrador')
            ->select(['Id','Description as title','Link as href','Parent','Node_Order'])
            ->where($colPerm, 1)
            ->orderBy('Parent')
            ->orderBy('Node_Order')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    private function conPadres(array $items): array
    {
        $byId = [];
        foreach ($items as $r) $byId[$r['Id']] = true;

        $faltan = [];
        foreach ($items as $r) {
            if (!empty($r['Parent']) && !isset($byId[$r['Parent']])) {
                $faltan[] = (int) $r['Parent'];
            }
        }
        if ($faltan) {
            $padres = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('menu_administrador')
                ->select(['Id','Description as title','Link as href','Parent','Node_Order'])
                ->whereIn('Id', array_unique($faltan))
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
            $todos = array_merge($items, $padres);
            $uniq  = [];
            foreach ($todos as $row) $uniq[$row['Id']] = $row;
            return array_values($uniq);
        }
        return $items;
    }

    private function aArbol(array $flat): array
    {
        $map = [];
        foreach ($flat as $n) { $n['children'] = []; $map[$n['Id']] = $n; }

        $root = [];
        foreach ($map as $id => &$n) {
            if (!empty($n['Parent']) && isset($map[$n['Parent']])) {
                $map[$n['Parent']]['children'][] = $n;
            } else {
                $root[] = $n;
            }
        }

        $sort = function (&$nodes) use (&$sort) {
            usort($nodes, fn($a,$b) => ($a['Node_Order']??0) <=> ($b['Node_Order']??0));
            foreach ($nodes as &$n) if (!empty($n['children'])) $sort($n['children']);
        };
        $sort($root);

        return $root;
    }
}