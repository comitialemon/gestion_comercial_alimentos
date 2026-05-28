<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Gestion\Todos\ClienteTema;

class LoadClientTheme
{
    public function handle($request, Closure $next)
    {
        $clienteId = session('cliente_id');
        
        if ($clienteId) {
            $tema = ClienteTema::getByCliente($clienteId);
            $tieneTema = ClienteTema::tieneTemaPersonalizado($clienteId);
            
            session([
                'tema_color_principal' => $tema->color_principal,
                'tema_color_secundario' => $tema->color_secundario,
                'tema_color_fondo' => $tema->color_fondo,
                'tema_color_texto' => $tema->color_texto,
                'tema_color_acento' => $tema->color_acento,
                'tema_logo_url' => $tema->logo_url,
                'tema_nombre_sistema' => $tema->nombre_sistema,
                'tiene_tema_personalizado' => $tieneTema, // 🔥 flag importante
            ]);
        } else {
            // Si no hay cliente en sesión, usar default
            $default = ClienteTema::getDefaultTheme();
            session([
                'tema_color_principal' => $default->color_principal,
                'tema_color_secundario' => $default->color_secundario,
                'tema_color_fondo' => $default->color_fondo,
                'tema_color_texto' => $default->color_texto,
                'tema_color_acento' => $default->color_acento,
                'tema_nombre_sistema' => $default->nombre_sistema,
                'tiene_tema_personalizado' => false,
            ]);
        }
        
        return $next($request);
    }
}