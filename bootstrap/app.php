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
        // Grupo web
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // 👇 Alias de middlewares (Laravel 11 reemplaza al viejo Kernel)
        $middleware->alias([
            'auth.operador'         => \App\Http\Middleware\AuthOperador::class,              // tu alias existente
            'contexto.requerido'    => \App\Http\Middleware\EnsureContextSelected::class,     // contexto de Gestión
            'facturacion.requerida' => \App\Http\Middleware\EnsureFacturacionMapped::class,   // exige mapeo (opcional)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
