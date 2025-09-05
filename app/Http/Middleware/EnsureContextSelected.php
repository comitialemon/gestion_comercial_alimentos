<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureContextSelected
{
    protected array $exemptRoutes = ['contexto.*','logout','login.*','up'];

    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs($this->exemptRoutes)) {
            return $next($request);
        }

        if ($request->session()->has('cliente_id') && $request->session()->has('cliente_sucursal_id')) {
            return $next($request);
        }

        if (! $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('contexto.index')
            ->withErrors(['contexto' => 'Selecciona empresa y sucursal.']);
    }
}
