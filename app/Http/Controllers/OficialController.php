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
    public function debug()
    {
        return response()->json([
            'cliente_nit' => session('cliente_nit'),
            'cliente_nombre' => session('cliente_nombre'),
            'empresa_id_facturacion' => session('empresa_id_facturacion'),
            'sucursal_id_facturacion' => session('sucursal_id_facturacion'),
            'punto_venta_id' => session('punto_venta_id'),
            'tiene_facturacion' => session('tiene_facturacion'),
        ]);
    }
}
