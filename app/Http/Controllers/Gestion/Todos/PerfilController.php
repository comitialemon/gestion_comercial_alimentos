<?php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class PerfilController extends Controller
{
    public function edit()
    {
        $operadorId = session('operador_id');
        
        if (!$operadorId) {
            return redirect()->route('login.show')
                ->with('error', 'No hay sesión activa');
        }

        $operador = Operador::with('identificador')
            ->findOrFail($operadorId);

        // Obtener identificadores para el selector (solo si se quiere cambiar)
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);

        return Inertia::render('Gestion/Todos/Perfil/Edit', [
            'operador' => $operador,
            'identificadores' => $identificadores,
        ]);
    }

    public function update(Request $request)
    {
        $operadorId = session('operador_id');
        $operador = Operador::findOrFail($operadorId);

        $request->validate([
            'Iniciales' => 'required|string|max:5',
            'NombreAcceso' => 'required|string|max:20|unique:mysql_gestion_comercial_alimentos.todos_operador,NombreAcceso,' . $operadorId . ',IdOperador',
            'Clave' => 'nullable|string|min:4|max:15',
            'DireccionDomicilio' => 'nullable|string',
            'TelefonoDomicilio' => 'nullable|string|max:20',
            'NumeroCelular' => 'nullable|string|max:20',
        ]);

        try {
            $datos = [
                'Iniciales' => strtoupper($request->Iniciales),
                'NombreAcceso' => $request->NombreAcceso,
                'DireccionDomicilio' => $request->DireccionDomicilio,
                'TelefonoDomicilio' => $request->TelefonoDomicilio,
                'NumeroCelular' => $request->NumeroCelular,
            ];

            // Solo actualizar contraseña si se envía una nueva
            if ($request->filled('Clave')) {
                $datos['Clave'] = $request->Clave;
            }

            $operador->update($datos);

            // Actualizar nombre de acceso en sesión si cambió
            if ($request->NombreAcceso !== session('operador_nombre')) {
                session(['operador_nombre' => $request->NombreAcceso]);
            }

            return redirect()->back()->with('success', 'Perfil actualizado correctamente');

        } catch (\Exception $e) {
            Log::error('Error al actualizar perfil: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al actualizar perfil: ' . $e->getMessage())
                ->withInput();
        }
    }
}