<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureContextSelected
{
    protected array $exemptRoutes = [
        'contexto.*',
        'logout',
        'login.*',
        'up',
        'contexto.pdv.*'
    ];

    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs($this->exemptRoutes)) {
            return $next($request);
        }

        // Verificar que tenga contexto de gestión (siempre necesario)
        if ($request->session()->has('cliente_id') && $request->session()->has('cliente_sucursal_id')) {
            
            // Si TIENE facturación, verificar que también tenga punto de venta
            $tieneFacturacion = $request->session()->get('tiene_facturacion', false);
            
            if ($tieneFacturacion && !$request->session()->has('punto_venta_id')) {
                if (! $request->session()->has('url.intended')) {
                    $request->session()->put('url.intended', $request->fullUrl());
                }
                return redirect()->route('contexto.pdv.index')
                    ->with('warning', 'Selecciona punto de venta');
            }
            
            // Todo bien, puede continuar
            return $next($request);
        }

        if (! $request->session()->has('url.intended')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect()->route('contexto.index')
            ->withErrors(['contexto' => 'Selecciona empresa y sucursal.']);
    }
}