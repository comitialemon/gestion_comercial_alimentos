<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================
// CONCILIACIÓN DIARIA DE QRs
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