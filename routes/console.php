<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================
// CONCILIACIÓN DIARIA DE QRs (03:00)
// ============================================================
Schedule::command('banco:conciliar-qrs')
    ->dailyAt('03:00')
    ->timezone('America/La_Paz')
    ->onSuccess(function () {
        \Log::info('✅ Conciliación de QRs completada');
    })
    ->onFailure(function () {
        \Log::error('❌ Conciliación de QRs falló');
    });

// ============================================================
// LIMPIEZA DE IDEMPOTENCY KEYS (03:30)
// ============================================================
Schedule::command('idempotency:limpiar --dias=30')
    ->dailyAt('03:30')
    ->timezone('America/La_Paz')
    ->onSuccess(function () {
        \Log::info('✅ Limpieza de idempotency keys completada');
    })
    ->onFailure(function () {
        \Log::error('❌ Limpieza de idempotency keys falló');
    });

// ============================================================
// RECONCILIACIÓN DE BILLETERAS (04:00)
// ============================================================
Schedule::command('billetera:reconciliar')
    ->dailyAt('04:00')
    ->timezone('America/La_Paz')
    ->onSuccess(function () {
        \Log::info('✅ Reconciliación de billeteras completada');
    })
    ->onFailure(function () {
        \Log::error('❌ Reconciliación de billeteras falló');
    });