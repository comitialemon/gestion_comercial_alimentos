<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 🔥 CONFIAR EN LOS PROXIES (Nginx)
        $middleware->trustProxies(at: '*');

        // 🔥 CSRF explícito
        $middleware->web(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);

        // 🔥 EXCLUIR RUTAS DEL WEBHOOK DEL CSRF
        $middleware->validateCsrfTokens(except: [
            'api/banco-ganadero/*',
        ]);

        // 🔥 Middlewares globales del grupo web
        $middleware->web(append: [
            \App\Http\Middleware\DominioCliente::class,
            \App\Http\Middleware\SetTimezone::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,

            // 🔥 NUEVO: headers anti-cache para TODAS las respuestas HTML.
            // Evita que Chrome/Edge sirvan HTML cacheado con datos viejos
            // (saldo de billetera, pedidos finalizados, etc.).
            // Solo afecta respuestas text/html. Los assets (CSS/JS/imágenes)
            // no se ven afectados.
            \App\Http\Middleware\PreventInertiaCache::class,
        ]);

        $middleware->alias([
            'auth.operador'         => \App\Http\Middleware\AuthOperador::class,
            'contexto.requerido'    => \App\Http\Middleware\EnsureContextSelected::class,
            'facturacion.requerida' => \App\Http\Middleware\EnsureFacturacionMapped::class,
            'verificar.contexto'    => \App\Http\Middleware\VerificarContexto::class,
            'evitar.contexto.duplicado' => \App\Http\Middleware\EvitarContextoDuplicado::class,
            'verificar.fecha' => \App\Http\Middleware\VerificarFecha::class,
            'set.timezone' => \App\Http\Middleware\SetTimezone::class,
            'prevent.inertia.cache' => \App\Http\Middleware\PreventInertiaCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();