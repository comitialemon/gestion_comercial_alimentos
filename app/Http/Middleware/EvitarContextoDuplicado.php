<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EvitarContextoDuplicado
{
    public function handle(Request $request, Closure $next)
    {
        $esContexto = $request->routeIs('contexto.index') || 
                      $request->routeIs('contexto.store') ||
                      $request->routeIs('contexto.sucursales') ||
                      $request->routeIs('contexto.pdv.*');
        
        if ($esContexto) {
            $hasContexto = session()->has('cliente_id') && session()->has('cliente_sucursal_id');
            $hasPuntoVenta = session()->has('punto_venta_id');
            $tieneFacturacion = session('tiene_facturacion', false);
            
            // Si ya tiene contexto completo
            if ($hasContexto && ($hasPuntoVenta || !$tieneFacturacion)) {
                // 🔥 Redirigir a oficial y además enviar header para que el frontend bloquee el historial
                return redirect()->route('oficial.index')
                    ->with('error', 'No puedes acceder a la selección de contexto.')
                    ->withHeaders([
                        'X-Bloquear-Historial' => 'true'
                    ]);
            }
        }
        
        return $next($request);
    }
}