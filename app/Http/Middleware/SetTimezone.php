<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetTimezone
{
    public function handle(Request $request, Closure $next)
    {
        // Solo si el usuario tiene contexto (cliente seleccionado)
        if ($request->session()->has('cliente_id')) {
            $clienteId = $request->session()->get('cliente_id');
            
            try {
                // Obtener la zona horaria del cliente desde la base de datos
                $cliente = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_cliente')
                    ->where('IdCliente', $clienteId)
                    ->first(['zona_horaria']);
                
                if ($cliente && !empty($cliente->zona_horaria)) {
                    $zonaHoraria = $cliente->zona_horaria;
                    
                    // Configurar la zona horaria de Laravel
                    Config::set('app.timezone', $zonaHoraria);
                    date_default_timezone_set($zonaHoraria);
                    
                    // Guardar en sesión para uso rápido
                    $request->session()->put('zona_horaria', $zonaHoraria);
                    
                    Log::info('⏰ Zona horaria configurada', [
                        'cliente_id' => $clienteId,
                        'zona_horaria' => $zonaHoraria
                    ]);
                } else {
                    // Si no tiene zona horaria configurada, usar la predeterminada
                    $zonaHoraria = 'America/La_Paz';
                    Config::set('app.timezone', $zonaHoraria);
                    date_default_timezone_set($zonaHoraria);
                    $request->session()->put('zona_horaria', $zonaHoraria);
                    
                    Log::info('⏰ Zona horaria por defecto (sin configurar en BD)', [
                        'cliente_id' => $clienteId,
                        'zona_horaria' => $zonaHoraria
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('❌ Error configurando zona horaria: ' . $e->getMessage());
                
                // Fallback a Bolivia
                $zonaHoraria = 'America/La_Paz';
                Config::set('app.timezone', $zonaHoraria);
                date_default_timezone_set($zonaHoraria);
                $request->session()->put('zona_horaria', $zonaHoraria);
            }
        } else {
            // Si no hay cliente logueado, usar la predeterminada
            $zonaHoraria = 'America/La_Paz';
            Config::set('app.timezone', $zonaHoraria);
            date_default_timezone_set($zonaHoraria);
        }
        
        return $next($request);
    }
}