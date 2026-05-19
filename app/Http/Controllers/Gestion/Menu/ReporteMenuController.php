<?php

namespace App\Http\Controllers\Gestion\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReporteMenuController extends Controller
{
    public function index()
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero.');
        }

        // 1. Obtener menús asignados directamente
        $menusAsignados = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('menu_operador as mo')
            ->join('menu_administrador as ma', 'mo.IdMenu', '=', 'ma.Id')
            ->where('mo.IdCliente', $clienteId)
            ->select(
                'mo.IdOperador',
                'ma.Id',
                'ma.Description',
                'ma.Parent',
                'ma.Node_Order'
            )
            ->orderBy('mo.IdOperador')
            ->orderBy('ma.Parent')
            ->orderBy('ma.Node_Order')
            ->get();

        if ($menusAsignados->isEmpty()) {
            return Inertia::render('Gestion/Menu/ReporteMenu', [
                'datos' => ['agrupado' => [], 'nombres' => []],
            ]);
        }

        // 2. Agrupar por operador
        $agrupado = [];
        foreach ($menusAsignados as $item) {
            $idOp = $item->IdOperador;
            if (!isset($agrupado[$idOp])) {
                $agrupado[$idOp] = [];
            }
            $agrupado[$idOp][] = [
                'id' => $item->Id,
                'desc' => $item->Description,
                'parent' => $item->Parent,
                'node_order' => $item->Node_Order,
            ];
        }

        // 3. Para cada operador, agregar los padres faltantes
        foreach ($agrupado as $idOp => &$lista) {
            $lista = $this->agregarPadresFaltantes($lista);
            
            // Ordenar por parent y node_order
            usort($lista, function($a, $b) {
                if ($a['parent'] != $b['parent']) {
                    return $a['parent'] <=> $b['parent'];
                }
                return ($a['node_order'] ?? 0) <=> ($b['node_order'] ?? 0);
            });
        }

        // 4. Obtener nombres de operadores
        $operadorIds = array_keys($agrupado);
        $operadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->whereIn('o.IdOperador', $operadorIds)
            ->select('o.IdOperador', 'i.Nombre')
            ->get()
            ->keyBy('IdOperador');

        $nombres = [];
        foreach ($agrupado as $idOp => $lista) {
            $operador = $operadores->get($idOp);
            $nombres[$idOp] = $operador ? $operador->Nombre : "Operador #{$idOp}";
        }

        asort($nombres);

        // Reordenar $agrupado según el orden de $nombres
        $agrupadoOrdenado = [];
        foreach (array_keys($nombres) as $idOp) {
            if (isset($agrupado[$idOp])) {
                $agrupadoOrdenado[$idOp] = $agrupado[$idOp];
            }
        }

        return Inertia::render('Gestion/Menu/ReporteMenu', [
            'datos' => [
                'agrupado' => $agrupadoOrdenado,
                'nombres' => $nombres,
            ],
        ]);
    }

    /**
     * Agrega los padres faltantes a la lista de menús
     */
    private function agregarPadresFaltantes($lista)
    {
        $idsExistentes = collect($lista)->pluck('id')->toArray();
        $padresNecesarios = collect($lista)->pluck('parent')->filter(function($parent) {
            return $parent != 0;
        })->toArray();
        
        $padresFaltantes = array_diff($padresNecesarios, $idsExistentes);
        
        if (empty($padresFaltantes)) {
            return $lista;
        }
        
        // Obtener los menús padres faltantes
        $padres = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('menu_administrador')
            ->whereIn('Id', $padresFaltantes)
            ->select('Id', 'Description', 'Parent', 'Node_Order')
            ->get();
        
        $nuevosItems = [];
        foreach ($padres as $padre) {
            $nuevosItems[] = [
                'id' => $padre->Id,
                'desc' => $padre->Description,
                'parent' => $padre->Parent,
                'node_order' => $padre->Node_Order,
            ];
        }
        
        // Recursivamente agregar padres de los padres
        $listaCompleta = array_merge($lista, $nuevosItems);
        return $this->agregarPadresFaltantes($listaCompleta);
    }

    public function getArbol($operadorId)
    {
        $clienteId = session('cliente_id');
        
        // Obtener menús asignados a este operador
        $menus = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('menu_operador as mo')
            ->join('menu_administrador as ma', 'mo.IdMenu', '=', 'ma.Id')
            ->where('mo.IdCliente', $clienteId)
            ->where('mo.IdOperador', $operadorId)
            ->select(
                'ma.Id',
                'ma.Description',
                'ma.Parent',
                'ma.Node_Order'
            )
            ->orderBy('ma.Parent')
            ->orderBy('ma.Node_Order')
            ->get();

        $lista = $menus->map(function($item) {
            return [
                'id' => $item->Id,
                'desc' => $item->Description,
                'parent' => $item->Parent,
                'node_order' => $item->Node_Order,
            ];
        })->toArray();

        // Agregar padres faltantes
        $listaCompleta = $this->agregarPadresFaltantes($lista);

        // Construir árbol
        $arbol = $this->armarArbol($listaCompleta, 0);

        return response()->json([
            'success' => true,
            'arbol' => $arbol,
        ]);
    }

    /**
     * Función recursiva para armar el árbol
     */
    private function armarArbol($lista, $padre = 0)
    {
        $resultado = [];
        
        // Filtrar hijos del padre actual
        $hijos = array_filter($lista, function($item) use ($padre) {
            return $item['parent'] == $padre;
        });
        
        // Ordenar hijos por node_order
        usort($hijos, function($a, $b) {
            return ($a['node_order'] ?? 0) <=> ($b['node_order'] ?? 0);
        });
        
        foreach ($hijos as $item) {
            // Verificar si tiene hijos
            $tieneHijos = false;
            foreach ($lista as $subitem) {
                if ($subitem['parent'] == $item['id']) {
                    $tieneHijos = true;
                    break;
                }
            }
            
            $node = [
                'id' => $item['id'],
                'desc' => $item['desc'],
                'parent' => $item['parent'],
                'icono' => $tieneHijos ? '📂' : '📄',
                'tiene_hijos' => $tieneHijos,
                'children' => [],
            ];
            
            if ($tieneHijos) {
                $node['children'] = $this->armarArbol($lista, $item['id']);
            }
            
            $resultado[] = $node;
        }
        
        return $resultado;
    }
}