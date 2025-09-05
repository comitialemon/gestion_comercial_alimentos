<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class MenuController extends Controller
{
    private function g() { // conexión Gestión
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

        // 1) Detalle del tipo (p.ej. "Vendedor")
        $detalleTipo = $this->g()
            ->table('todos_operador_tipo')
            ->where('IdOperadorTipo', $tipoId)
            ->value('Detalle');

        // 2) Resolver nombre de columna en menu_administrador
        $colPerm = $this->resolverColumnaPermiso($detalleTipo); // p.ej. "Vendedor"

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

    /** Convierte el Detalle a nombre de columna, validando contra el schema */
    private function resolverColumnaPermiso(?string $detalleTipo): string
    {
        if (!$detalleTipo) return 'Administrador';

        $normal = preg_replace('/\s+/', '', $detalleTipo); // quita espacios
        // Excepciones si el Detalle no coincide con el nombre real de la columna
        $ex = [
            'SuperUsuario'         => 'Administrador',
            'VentaMonitorCocina'   => 'MonitorCocina',
            'VentaMonitorEntregas' => 'MonitorEntregas', // si creas esa columna
            'VentaMayoristas'      => 'VentaMayorista',
        ];
        $col = $ex[$normal] ?? $normal;

        // Si la columna no existe, fallback seguro
        if (!Schema::hasColumn('menu_administrador', $col)) {
            return 'Administrador';
        }
        return $col;
    }

    /** Menú por columna booleana (e.g. Vendedor = 1) */
    private function itemsPorColumna(string $colPerm): array
    {
        return DB::table('menu_administrador')
            ->select(['Id','Description as title','Link as href','Parent','Node_Order'])
            ->where($colPerm, 1)
            ->orderBy('Parent')->orderBy('Node_Order')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    /** Agrega padres si sólo vinieron hijos */
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
            $padres = DB::table('menu_administrador')
                ->select(['Id','Description as title','Link as href','Parent','Node_Order'])
                ->whereIn('Id', array_unique($faltan))
                ->get()
                ->map(fn($r) => (array) $r)
                ->toArray();
            // unificar por Id
            $todos = array_merge($items, $padres);
            $uniq  = [];
            foreach ($todos as $row) $uniq[$row['Id']] = $row;
            return array_values($uniq);
        }
        return $items;
    }

    /** Convierte lista plana a árbol, ordenado por Node_Order */
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
