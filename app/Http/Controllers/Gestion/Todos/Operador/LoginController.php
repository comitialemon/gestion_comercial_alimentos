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
            ->where('Clave', $request->clave) // sin hash según tu tabla
            ->first();

        if (! $operador) {
            return back()->withErrors(['usuario' => 'Usuario o contraseña incorrectos.']);
        }

        // 🔒 evita session fixation
        $request->session()->regenerate();

        // Guardar datos del operador
        session([
            'operador_id'      => (int) $operador->IdOperador,
            'operador_nombre'  => (string) $operador->NombreAcceso,
            'operador_tipo_id' => (int) $operador->IdOperadorTipo,
        ]);

        // Limpiar contexto previo (empresa/sucursal)
        session()->forget([
            'cliente_id', 'cliente_sucursal_id',
            'global_empresa_id', 'global_empresa_nombre',
            'global_sucursal_id', 'global_sucursal_nombre', 'global_sucursal_numero',
            'empresa_id', 'sucursal_id'
        ]);

        return redirect()->route('contexto.index');
    }

    public function logout(Request $request)
    {
        // Limpiar cache de menú en sesión
        foreach (array_keys($request->session()->all()) as $key) {
            if (str_starts_with($key, 'menu_tree_')) {
                $request->session()->forget($key);
            }
        }

        // Limpia todo lo demás de ser necesario…
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.show');
    }

}
