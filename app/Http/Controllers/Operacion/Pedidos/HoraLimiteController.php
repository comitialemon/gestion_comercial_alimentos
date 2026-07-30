<?php

namespace App\Http\Controllers\Operacion\Pedidos;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\HoraLimite;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HoraLimiteController extends Controller
{
    /**
     * Mostrar listado de horas límite
     */
    public function index()
    {
        $horas = HoraLimite::porContexto()
            ->ordenado()
            ->get();

        // Obtener la hora activa (solo una)
        $horaActiva = HoraLimite::porContexto()
            ->activos()
            ->first();

        return Inertia::render('Operacion/Pedidos/HoraLimite/Index', [
            'horas' => $horas,
            'horasDisponibles' => $this->getHorasDisponibles($horas),
            'horaActiva' => $horaActiva,
        ]);
    }

    /**
     * Guardar nueva hora límite
     */
    public function store(Request $request)
    {
        $request->validate([
            'Hora' => 'required|integer|min:1|max:24',
            'ActivaControlDia' => 'boolean',
        ]);

        // Verificar si ya existe esa hora
        $existe = HoraLimite::porContexto()
            ->where('Hora', $request->Hora)
            ->exists();

        if ($existe) {
            return redirect()->back()->withErrors([
                'Hora' => 'Ya existe una configuración para la hora ' . $request->Hora . ':00'
            ])->withInput();
        }

        HoraLimite::create([
            'Hora' => $request->Hora,
            'ActivaControlDia' => $request->ActivaControlDia ? 1 : 0,
            'IdCliente' => session('cliente_id'),
            'IdSucursal' => 0, // 0 = aplica a todas las sucursales
        ]);

        return redirect()->back()->with('success', 'Hora límite agregada correctamente');
    }

    /**
     * Actualizar hora límite (usando POST con _method=PUT)
     */
    public function update(Request $request, $id)
    {
        \Log::info('=== UPDATE HORA LÍMITE ===');
        \Log::info('ID: ' . $id);
        \Log::info('Data: ', $request->all());
        
        $hora = HoraLimite::porContexto()->findOrFail($id);

        $request->validate([
            'Hora' => 'required|integer|min:1|max:24',
            'ActivaControlDia' => 'boolean',
        ]);

        // Verificar si ya existe otra hora con el mismo valor
        $existe = HoraLimite::porContexto()
            ->where('Hora', $request->Hora)
            ->where('IdHoraLimite', '!=', $id)
            ->exists();

        if ($existe) {
            return redirect()->back()->withErrors([
                'Hora' => 'Ya existe una configuración para la hora ' . $request->Hora . ':00'
            ])->withInput();
        }

        $hora->update([
            'Hora' => $request->Hora,
            'ActivaControlDia' => $request->ActivaControlDia ? 1 : 0,
        ]);

        return redirect()->back()->with('success', 'Hora límite actualizada correctamente');
    }

    /**
     * Eliminar hora límite
     */
    public function destroy($id)
    {
        try {
            $hora = HoraLimite::porContexto()->findOrFail($id);
            $hora->delete();

            return response()->json([
                'success' => true,
                'message' => 'Hora límite eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener horas disponibles (1-24 que no están ya configuradas)
     */
    private function getHorasDisponibles($horasExistentes)
    {
        $horasOcupadas = $horasExistentes->pluck('Hora')->toArray();
        $horas = [];

        for ($i = 1; $i <= 24; $i++) {
            $horas[] = [
                'value' => $i,
                'label' => str_pad($i, 2, '0', STR_PAD_LEFT) . ':00',
                'disponible' => !in_array($i, $horasOcupadas)
            ];
        }

        return $horas;
    }

    /**
     * API: Obtener hora límite ACTIVA (solo una)
     */
    public function apiGetActivas()
    {
        $horaActiva = HoraLimite::porContexto()
            ->activos()
            ->first();

        return response()->json([
            'success' => true,
            'hora_activa' => $horaActiva ? [
                'id' => $horaActiva->IdHoraLimite,
                'hora' => $horaActiva->Hora,
                'hora_formateada' => $horaActiva->HoraFormateada,
            ] : null,
        ]);
    }
}