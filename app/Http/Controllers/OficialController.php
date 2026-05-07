<?php
namespace App\Http\Controllers;

use Inertia\Inertia;

class OficialController extends Controller
{
    public function index()
    {
        $hasFact = session()->has('empresa_id') && session()->has('sucursal_id');

        return Inertia::render('Oficial/Index', [
            'gestion' => [
                'cliente_id'          => session('cliente_id'),
                'cliente_sucursal_id' => session('cliente_sucursal_id'),
                'empresa_nombre'      => session('global_empresa_nombre'),
                'sucursal_nombre'     => session('global_sucursal_nombre'),
                'sucursal_numero'     => session('global_sucursal_numero'),
            ],
            'facturacion' => [
                'empresa_id'  => session('empresa_id'),
                'sucursal_id' => session('sucursal_id'),
                'completo'    => $hasFact,
            ],
            'flash' => [
                'ok'   => session('ok'),
                'warn' => session('warn'),
            ],
        ]);
    }
}
