<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthOperador
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('operador_id')) {
            return redirect()->route('login.show');
        }

        return $next($request);
    }
}
