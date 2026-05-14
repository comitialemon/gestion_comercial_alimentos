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
        // 🔥 AGREGAR CSRF EXPLÍCITAMENTE
        $middleware->web(prepend: [
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, // 👈 CSRF activo
        ]);
        
        // Grupo web (append)
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Alias de middlewares
        $middleware->alias([
            'auth.operador'         => \App\Http\Middleware\AuthOperador::class,
            'contexto.requerido'    => \App\Http\Middleware\EnsureContextSelected::class,
            'facturacion.requerida' => \App\Http\Middleware\EnsureFacturacionMapped::class,
            'verificar.contexto'    => \App\Http\Middleware\VerificarContexto::class,
            'evitar.contexto.duplicado' => \App\Http\Middleware\EvitarContextoDuplicado::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();