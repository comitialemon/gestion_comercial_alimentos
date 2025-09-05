<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContextoController extends Controller
{
    private function g() { return DB::connection('mysql_gestion_comercial_alimentos'); }

    public function index(Request $request)
    {
        $operadorId = (int) $request->session()->get('operador_id');

        $empresas = $this->g()
            ->table('todos_cliente as c')
            ->join('todos_operador_sucursaldb as os', 'os.IdCliente', '=', 'c.IdCliente')
            ->where('os.IdOperador', $operadorId)
            ->selectRaw('c.IdCliente as id, c.Nombre as nombre, c.NIT as nit')
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
            ->selectRaw('s.IdClienteSucursal as id, s.Nombre as nombre, s.NumeroSucursal as numero')
            ->orderBy('s.Nombre')
            ->get();

        return response()->json($sucursales);
    }

    // ... arriba queda igual

    public function store(Request $request)
    {
        $data = $request->validate([
            'empresa_id'  => ['required','integer'],
            'sucursal_id' => ['required','integer'],
        ]);

        $operadorId = (int) $request->session()->get('operador_id');

        // validar asignación
        $asignada = $this->g()->table('todos_operador_sucursaldb')
            ->where('IdOperador', $operadorId)
            ->where('IdCliente',  $data['empresa_id'])
            ->where('IdSucursal', $data['sucursal_id'])
            ->exists();

        if (! $asignada) {
            return back()->withErrors(['contexto' => 'No tienes asignada esa empresa/sucursal.']);
        }

        // nombres
        $empresa = $this->g()->table('todos_cliente')
            ->where('IdCliente', $data['empresa_id'])
            ->select('IdCliente','Nombre','NIT')->first();

        $sucursal = $this->g()->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $data['sucursal_id'])
            ->select('IdClienteSucursal','Nombre','NumeroSucursal')->first();

        // guardar gestión (globales)
        session([
            'cliente_id'             => (int)$empresa?->IdCliente,
            'cliente_sucursal_id'    => (int)$sucursal?->IdClienteSucursal,

            'global_empresa_id'      => (int)$empresa?->IdCliente,
            'global_empresa_nombre'  => $empresa?->Nombre,
            'global_sucursal_id'     => (int)$sucursal?->IdClienteSucursal,
            'global_sucursal_nombre' => $sucursal?->Nombre,
            'global_sucursal_numero' => $sucursal?->NumeroSucursal,
        ]);

        // limpiar cache menú
        foreach (array_keys(session()->all()) as $k) {
            if (str_starts_with($k, 'menu_tree_')) session()->forget($k);
        }

        // mapeo facturación (opcional)
        $mapEmp = DB::connection('mysql')->table('map_cliente_empresa')
            ->where('idClienteGestion', (int)$empresa?->IdCliente)->first();

        $mapSuc = DB::connection('mysql')->table('map_sucursal_sucursal')
            ->where('idClienteSucursalGestion', (int)$sucursal?->IdClienteSucursal)->first();

        if ($mapEmp && $mapSuc) {
            session([
                'empresa_id'  => (int)$mapEmp->idEmpresa,
                'sucursal_id' => (int)$mapSuc->idSucursal,
            ]);
        } else {
            session()->forget(['empresa_id','sucursal_id']);
        }

        $intended = $request->session()->pull('url.intended', route('oficial.index'));
        return redirect()->to($intended)->with('ok','Contexto actualizado');
    }


    private function forgetMenuCache(Request $request): void
    {
        foreach (array_keys($request->session()->all()) as $key) {
            if (str_starts_with($key, 'menu_tree_')) {
                $request->session()->forget($key);
            }
        }
    }
}
