<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class DominioCliente
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        // 🔥 FORZAR URL RAÍZ DINÁMICA (esto resuelve el mixed content)
        URL::forceScheme($request->secure() ? 'https' : 'http');
        URL::forceRootUrl($request->getSchemeAndHttpHost());

        // Si es IP → no filtramos empresa, flujo normal
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $next($request);
        }

        // Normalizar www.
        $hostNormalizado = preg_replace('/^www\./', '', $host);

        // Buscar empresa por dominio
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('dominio', $hostNormalizado)
            ->orWhere('dominio', $host)
            ->first();

        if (! $empresa) {
            return $next($request);
        }

        // Guardar empresa del dominio en sesión
        if (! $request->session()->has('cliente_id')) {
            $request->session()->put('dominio_empresa_id', (int) $empresa->IdCliente);
            $request->session()->put('dominio_empresa_nombre', $empresa->Nombre);
        }

        return $next($request);
    }
}