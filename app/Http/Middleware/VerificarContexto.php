<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerificarContexto
{
    protected $exemptRoutes = [
        'login.show',
        'login.do',
        'logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Rutas exentas
        if ($this->isExemptRoute($request)) {
            return $next($request);
        }

        $hasContexto = $this->hasContexto();
        $hasPuntoVenta = $this->hasPuntoVenta();
        $tieneFacturacion = session('tiene_facturacion', false);

        // ⚠️ INYECTAR VARIABLES EN LA VISTA PARA QUE EL FRONTEND SEPA EL ESTADO
        Inertia::share([
            'hasContexto' => $hasContexto,
            'hasPuntoVenta' => $hasPuntoVenta,
            'tieneFacturacion' => $tieneFacturacion,
        ]);

        // =============================================
        // CASO 1: No tiene contexto (empresa + sucursal)
        // =============================================
        if (!$hasContexto) {
            if ($this->isContextoRoute($request) || $this->isPdvRoute($request)) {
                return $this->preventCache($next($request));
            }
            return redirect()->route('contexto.index');
        }

        // =============================================
        // CASO 2: Tiene contexto pero NO tiene punto de venta
        // =============================================
        if ($hasContexto && !$hasPuntoVenta && $tieneFacturacion) {
            if ($this->isPdvRoute($request)) {
                return $this->preventCache($next($request));
            }
            if ($this->isContextoRoute($request)) {
                return redirect()->route('contexto.pdv.index');
            }
            return redirect()->route('contexto.pdv.index');
        }

        // =============================================
        // CASO 3: Tiene contexto Y punto de venta (flujo completo)
        // =============================================
        if ($hasContexto && $hasPuntoVenta) {
            if ($this->isContextoRoute($request) || $this->isPdvRoute($request)) {
                return redirect()->route('oficial.index');
            }
        }

        // =============================================
        // CASO 4: Tiene contexto pero sin facturación
        // =============================================
        if ($hasContexto && !$tieneFacturacion) {
            if ($this->isContextoRoute($request) || $this->isPdvRoute($request)) {
                return redirect()->route('oficial.index');
            }
        }

        return $this->preventCache($next($request));
    }

    private function hasContexto(): bool
    {
        return session()->has('cliente_id') && 
               session()->has('cliente_sucursal_id') &&
               session('cliente_id') > 0 &&
               session('cliente_sucursal_id') > 0;
    }

    private function hasPuntoVenta(): bool
    {
        $tieneFacturacion = session('tiene_facturacion', false);
        if (!$tieneFacturacion) {
            return true;
        }
        return session()->has('punto_venta_id') && session('punto_venta_id') > 0;
    }

    private function isContextoRoute(Request $request): bool
    {
        return $request->routeIs('contexto.index') || 
               $request->routeIs('contexto.store') ||
               $request->routeIs('contexto.sucursales');
    }

    private function isPdvRoute(Request $request): bool
    {
        return $request->routeIs('contexto.pdv.*');
    }

    private function isExemptRoute(Request $request): bool
    {
        foreach ($this->exemptRoutes as $route) {
            if ($request->routeIs($route)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 🔥 EVITA QUE EL NAVEGADOR CACHEE LA PÁGINA
     */
    private function preventCache($response)
    {
        // 'no-store' es el factor determinante para el botón adelante/atrás
        $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->header('Pragma', 'no-cache');
        $response->header('Expires', 'Sun, 02 Jan 1990 00:00:00 GMT'); 
        return $response;
    }
}