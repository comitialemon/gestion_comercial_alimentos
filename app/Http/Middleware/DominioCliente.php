<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DominioCliente
{
    /**
     * Middleware que detecta si la petición entra por un dominio
     * asignado a una empresa específica, y guarda esa empresa en sesión
     * para que el selector de contexto solo muestre esa empresa.
     *
     * Si entra por IP o dominio desconocido → no hace nada (flujo normal).
     */
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost(); // "hamacassrl.com", "www.hamacassrl.com" o "200.58.74.173"

        // Si es IP, no hacemos nada
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $next($request);
        }

        // Normalizar: quitar "www." para que coincida siempre
        $hostNormalizado = preg_replace('/^www\./', '', $host);

        // Buscar empresa por dominio (con o sin www)
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('dominio', $hostNormalizado)
            ->orWhere('dominio', $host)
            ->first();

        // Dominio no registrado → flujo normal
        if (! $empresa) {
            return $next($request);
        }

        // 🔥 Guardar empresa del dominio SOLO si aún no hay contexto elegido
        // (para no sobreescribir si el usuario ya eligió empresa)
        if (! $request->session()->has('cliente_id')) {
            $request->session()->put('dominio_empresa_id', (int) $empresa->IdCliente);
            $request->session()->put('dominio_empresa_nombre', $empresa->Nombre);
        }

        return $next($request);
    }
}