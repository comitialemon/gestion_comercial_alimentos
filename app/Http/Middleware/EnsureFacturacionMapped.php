<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureFacturacionMapped
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('empresa_id') || ! $request->session()->has('sucursal_id')) {
            return redirect()->route('oficial.index')
                ->with('warn', 'Falta completar el mapeo con Facturación para esta empresa/sucursal.');
        }
        return $next($request);
    }
}
