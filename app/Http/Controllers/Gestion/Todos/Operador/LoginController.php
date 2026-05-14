<?php

namespace App\Http\Controllers\Gestion\Todos\Operador;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Operador;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function show()
    {
        return inertia('Gestion/Todos/Operador/Login');
    }
    public function do(Request $request)
    {
        $request->validate([
            'usuario' => 'required|string',
            'clave'   => 'required|string',
        ]);

        $operador = Operador::query()
            ->where('NombreAcceso', $request->usuario)
            ->where('Clave', $request->clave)
            ->with('identificador')
            ->first();

        if (! $operador) {
            return back()->withErrors(['usuario' => 'Usuario o contraseña incorrectos.']);
        }

        $request->session()->regenerate();
        
        session([
            'operador_id'      => (int) $operador->IdOperador,
            'operador_nombre'  => $operador->identificador->Nombre ?? $operador->NombreAcceso,
            'operador_tipo_id' => (int) $operador->IdOperadorTipo,
        ]);

        session()->forget([
            'cliente_id', 'cliente_sucursal_id',
            'global_empresa_id', 'global_empresa_nombre',
            'global_sucursal_id', 'global_sucursal_nombre', 'global_sucursal_numero',
            'empresa_id', 'sucursal_id'
        ]);

        // Redirigir a contexto con un parámetro para forzar recarga
        return redirect()->route('contexto.index', ['reload' => 1]);
    }
    

    public function logout(Request $request)
    {
        // Limpiar cache de menú en sesión
        foreach (array_keys($request->session()->all()) as $key) {
            if (str_starts_with($key, 'menu_tree_')) {
                $request->session()->forget($key);
            }
        }

        // 🔥 DESTRUIR SESIÓN COMPLETAMENTE
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.show');
    }
}