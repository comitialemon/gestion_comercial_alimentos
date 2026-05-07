<?php
// app/Http/Middleware/RequiereFacturacion.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequiereFacturacion
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->get('tiene_facturacion', false)) {
            return redirect()->route('oficial.index')
                ->with('error', 'Esta empresa/sucursal no tiene habilitada la facturación electrónica');
        }

        return $next($request);
    }
}