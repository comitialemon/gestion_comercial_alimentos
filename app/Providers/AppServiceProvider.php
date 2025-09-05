<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Inertia\Inertia; // 👈 IMPORTANTE

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // 5 intentos por minuto por IP+usuario
        RateLimiter::for('login', function (Request $request) {
            $key = $request->ip().'|'.$request->input('usuario');
            return [ Limit::perMinute(5)->by($key) ];
        });

        // 🔥 Compartir datos globales con todas las vistas Inertia
        Inertia::share([
            'operadorNombre'  => fn () => session('operador_nombre'),
            'empresaNombre'   => fn () => session('global_empresa_nombre'),
            'sucursalNombre'  => fn () => session('global_sucursal_nombre'),
        ]);
    }
}
