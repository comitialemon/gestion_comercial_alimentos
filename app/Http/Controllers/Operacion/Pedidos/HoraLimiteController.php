<?php

namespace App\Http\Controllers\Operacion\Pedidos;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\HoraLimite;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HoraLimiteController extends Controller
{
    /**
     * Mostrar listado de horas límite (con filtro por tipo)
     */
    public function index(Request $request)
    {
        $tiposDisponibles = HoraLimite::tiposDisponibles();

        $tipo = $request->get('tipo', HoraLimite::TIPO_PEDIDO_ORDINARIO);

        if (!array_key_exists($tipo, $tiposDisponibles)) {
            $tipo = HoraLimite::TIPO_PEDIDO_ORDINARIO;
        }

        $horas = HoraLimite::porContexto()
            ->porTipo($tipo)
            ->ordenado()
            ->get();

        $horaActiva = HoraLimite::porContexto()
            ->porTipo($tipo)
            ->activos()
            ->first();

        return Inertia::render('Operacion/Pedidos/HoraLimite/Index', [
            'horas' => $horas,
            'horasDisponibles' => $this->getHorasDisponibles($horas),
            'horaActiva' => $horaActiva,
            'tipoActual' => $tipo,
            'tiposDisponibles' => array_values($tiposDisponibles),
        ]);
    }

    /**
     * Guardar nueva hora límite
     */
    public function store(Request $request)
    {
        $tiposValidos = implode(',', array_keys(HoraLimite::tiposDisponibles()));

        $request->validate([
            'Hora' => 'required|integer|min:1|max:24',
            'ActivaControlDia' => 'boolean',
            'Tipo' => "required|in:{$tiposValidos}",
        ]);

        $existe = HoraLimite::porContexto()
            ->porTipo($request->Tipo)
            ->where('Hora', $request->Hora)
            ->exists();

        if ($existe) {
            return redirect()->back()->withErrors([
                'Hora' => 'Ya existe una configuración para la hora ' . $request->Hora . ':00 en este tipo de pedido'
            ])->withInput();
        }

        HoraLimite::create([
            'Hora' => $request->Hora,
            'ActivaControlDia' => $request->ActivaControlDia ? 1 : 0,
            'IdCliente' => session('cliente_id'),
            'IdSucursal' => 0,
            'Tipo' => $request->Tipo,
        ]);

        return redirect()->back()->with('success', 'Hora límite agregada correctamente');
    }

    /**
     * Actualizar hora límite
     */
    public function update(Request $request, $id)
    {
        $hora = HoraLimite::porContexto()->findOrFail($id);

        $request->validate([
            'Hora' => 'required|integer|min:1|max:24',
            'ActivaControlDia' => 'boolean',
        ]);

        $existe = HoraLimite::porContexto()
            ->porTipo($hora->Tipo)
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
     * Horas disponibles (1-24 que no están configuradas para ese tipo)
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
     * API: Obtener hora límite ACTIVA por tipo
     */
    public function apiGetActivas(Request $request)
    {
        $tiposDisponibles = HoraLimite::tiposDisponibles();
        $tipo = $request->get('tipo', HoraLimite::TIPO_PEDIDO_ORDINARIO);

        if (!array_key_exists($tipo, $tiposDisponibles)) {
            $tipo = HoraLimite::TIPO_PEDIDO_ORDINARIO;
        }

        $horaActiva = HoraLimite::porContexto()
            ->porTipo($tipo)
            ->activos()
            ->first();

        return response()->json([
            'success' => true,
            'tipo' => $tipo,
            'hora_activa' => $horaActiva ? [
                'id' => $horaActiva->IdHoraLimite,
                'hora' => $horaActiva->Hora,
                'hora_formateada' => $horaActiva->HoraFormateada,
            ] : null,
        ]);
    }
}