<?php
// app/Http/Controllers/ContextoController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContextoController extends Controller
{
    private function g() { return DB::connection('mysql_gestion_comercial_alimentos'); }
    private function f() { return DB::connection('facturacion'); }

    public function index(Request $request)
    {
        $operadorId = (int) $request->session()->get('operador_id');

        $empresas = $this->g()
            ->table('todos_cliente as c')
            ->join('todos_operador_sucursaldb as os', 'os.IdCliente', '=', 'c.IdCliente')
            ->where('os.IdOperador', $operadorId)
            ->selectRaw('c.IdCliente as id, c.Nombre as nombre, c.NIT as nit, c.facturacion_habilitada')
            ->distinct()
            ->orderBy('c.Nombre')
            ->get();

        return Inertia::render('Contexto/Index', [
            'empresas' => $empresas,
            'selected' => [
                'empresa_id'  => session('cliente_id'),
                'sucursal_id' => session('cliente_sucursal_id'),
            ],
            'isSuper' => (int) session('operador_tipo_id') === 1,
        ]);
    }

    public function sucursales(Request $request, $empresaId)
    {
        $operadorId = (int) $request->session()->get('operador_id');

        $sucursales = $this->g()
            ->table('todos_cliente_sucursal as s')
            ->join('todos_operador_sucursaldb as os', function ($j) {
                $j->on('os.IdSucursal', '=', 's.IdClienteSucursal')
                  ->on('os.IdCliente', '=', 's.IdCliente');
            })
            ->where('os.IdOperador', $operadorId)
            ->where('os.IdCliente',  (int) $empresaId)
            ->selectRaw('s.IdClienteSucursal as id, s.Nombre as nombre, s.NumeroSucursal as numero, s.facturacion_habilitada')
            ->orderBy('s.Nombre')
            ->get();

        return response()->json($sucursales);
    }
    public function store(Request $request)
    {
        // 🔥 RECIBIR DATOS DE FORM DATA O JSON
        $empresaId = $request->input('empresa_id');
        $sucursalId = $request->input('sucursal_id');
        
        if (!$empresaId || !$sucursalId) {
            return response()->json(['error' => 'Faltan datos'], 422);
        }
        
        $data = [
            'empresa_id' => $empresaId,
            'sucursal_id' => $sucursalId
        ];

        $operadorId = (int) $request->session()->get('operador_id');

        $asignada = $this->g()->table('todos_operador_sucursaldb')
            ->where('IdOperador', $operadorId)
            ->where('IdCliente',  $data['empresa_id'])
            ->where('IdSucursal', $data['sucursal_id'])
            ->exists();

        if (! $asignada) {
            return response()->json(['error' => 'No tienes asignada esa empresa/sucursal.'], 422);
        }

        $empresa = $this->g()->table('todos_cliente')
            ->where('IdCliente', $data['empresa_id'])
            ->select('IdCliente','Nombre','NIT', 'facturacion_habilitada')
            ->first();

        $sucursal = $this->g()->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $data['sucursal_id'])
            ->select('IdClienteSucursal','Nombre','NumeroSucursal', 'facturacion_habilitada')
            ->first();

        session([
            'cliente_id'             => (int)$empresa?->IdCliente,
            'cliente_nombre'         => $empresa?->Nombre,
            'cliente_nit'            => $empresa?->NIT,
            'cliente_sucursal_id'    => (int)$sucursal?->IdClienteSucursal,
            'cliente_sucursal_nombre'=> $sucursal?->Nombre,
            'cliente_sucursal_numero'=> $sucursal?->NumeroSucursal,
            'tiene_facturacion'      => (bool)$sucursal?->facturacion_habilitada,
            'global_empresa_nombre'  => $empresa?->Nombre,
            'global_sucursal_nombre' => $sucursal?->Nombre,
            'global_sucursal_numero' => $sucursal?->NumeroSucursal,
        ]);

        foreach (array_keys(session()->all()) as $k) {
            if (str_starts_with($k, 'menu_tree_')) session()->forget($k);
        }

        $tieneFacturacion = (bool)$sucursal?->facturacion_habilitada;

        if ($tieneFacturacion) {
            $mapEmp = $this->f()->table('map_cliente_empresa')
                ->where('idClienteGestion', (int)$empresa?->IdCliente)
                ->first();

            $mapSuc = $this->f()->table('map_sucursal_sucursal')
                ->where('idClienteSucursalGestion', (int)$sucursal?->IdClienteSucursal)
                ->first();

            if ($mapEmp && $mapSuc) {
                $empresaFact = $this->f()->table('empresa')
                    ->where('idEmpresa', $mapEmp->idEmpresa)
                    ->first();

                $sucursalFact = $this->f()->table('sucursal')
                    ->where('idSucursal', $mapSuc->idSucursal)
                    ->first();

                session([
                    'empresa_id_facturacion'  => (int)$mapEmp->idEmpresa,
                    'sucursal_id_facturacion' => (int)$mapSuc->idSucursal,
                    'sucursal_nombre'         => $sucursalFact?->nombre ?? $sucursal?->Nombre,
                    'sucursal_numero'         => $sucursalFact?->codigo ?? $sucursal?->NumeroSucursal,
                    'ambiente_facturacion'    => $empresaFact?->ambiente,
                    'modalidad_facturacion'   => $empresaFact?->modalidad,
                ]);
                
                session()->forget(['punto_venta_id', 'punto_venta_codigo', 'punto_venta_nombre']);
                
                return response()->json([
                    'success' => true,
                    'redirect' => route('contexto.pdv.index')
                ]);
            } else {
                session(['error_facturacion' => 'Facturación habilitada sin mapeo']);
                return response()->json([
                    'success' => true,
                    'redirect' => route('oficial.index'),
                    'warning' => 'Facturación no configurada'
                ]);
            }
        }

        session()->forget(['empresa_id_facturacion', 'sucursal_id_facturacion', 'punto_venta_id', 'punto_venta_codigo', 'punto_venta_nombre']);
        
        return response()->json([
            'success' => true,
            'redirect' => route('oficial.index')
        ]);
    }
}