<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PreventInertiaCache
 *
 * Añade headers HTTP para que el navegador no guarde las respuestas HTML
 * del módulo de pedidos en su bfcache / render cache.
 *
 * Es una capa complementaria. La defensa principal es el uso de
 * `replace: true` / `window.location.replace` en el frontend.
 */
class PreventInertiaCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        if (str_contains($contentType, 'text/html')) {
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, max-age=0'
            );

            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('Vary', 'X-Inertia', false);
        }

        return $response;
    }
}