<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Gestion\Todos\Operador\LoginController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\OficialController;
use App\Http\Controllers\Facturacion\ImportarEmpresasController;
use App\Http\Controllers\MenuController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login.show');
    Route::post('/login', [LoginController::class, 'do'])->middleware('throttle:login')->name('login.do');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['auth.operador'])->group(function () {

    // Ir primero a contexto
    Route::get('/', fn() => redirect()->route('contexto.index'))->name('home');

    // Contexto (Gestión)
    Route::get('/contexto', [ContextoController::class, 'index'])->name('contexto.index');
    Route::get('/contexto/sucursales/{empresa}', [ContextoController::class, 'sucursales'])->name('contexto.sucursales');
    Route::post('/contexto', [ContextoController::class, 'store'])->name('contexto.store');

    // Módulos (requiere contexto)
    Route::middleware('contexto.requerido')->group(function () {

        Route::get('/oficial', [OficialController::class, 'index'])->name('oficial.index');

        Route::get('/facturacion/importar/empresas',  [ImportarEmpresasController::class, 'index'])
            ->name('fact.import.empresas.index');
        Route::post('/facturacion/importar/empresas/{idCliente}', [ImportarEmpresasController::class, 'store'])
            ->name('fact.import.empresas.store');

        Route::middleware('facturacion.requerida')->group(function () {
            Route::get('/facturacion/cufd', fn () => 'Aquí iría tu CUFD real...')->name('cufd.index');
        });
    });

    // opcional
    Route::get('/api/menu', [MenuController::class, 'index'])->name('menu.index');
});
