<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class VerificarFecha
{
    public function handle(Request $request, Closure $next)
    {
        $fechaActual = date('Y-m-d');
        
        Log::info('Verificando fecha: ' . $fechaActual);
        
        $fechaExiste = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', $fechaActual)
            ->where('ActivoInactivo', 0)
            ->exists();

        Log::info('Fecha existe: ' . ($fechaExiste ? 'SI' : 'NO'));
        
        if (!$fechaExiste) {
            Log::warning('Fecha NO habilitada');
            
            // 🔥 CERRAR SESIÓN SI ESTÁ LOGUEADO
            if ($request->session()->has('operador_id')) {
                $request->session()->flush();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            
            // 🔥 MOSTRAR VISTA DE ERROR
            return Inertia::render('Gestion/Todos/Operador/FechaNoHabilitada', [
                'fecha' => date('d/m/Y')
            ]);
        }

        return $next($request);
    }
}