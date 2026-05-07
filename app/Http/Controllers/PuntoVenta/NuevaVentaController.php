<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\LugarVenta;
use App\Models\Gestion\Impuestos\Comisionista;
use Inertia\Inertia;
use Illuminate\Http\Request;  // 🔥 AGREGAR ESTA LÍNEA

class NuevaVentaController extends Controller
{
    /**
     * Muestra el formulario de selección de lugar de venta y comisionista
     */
    public function create()
    {
        // Obtener lugares de venta filtrados por cliente y sucursal de la sesión
        $lugaresVenta = LugarVenta::porContexto()
            ->orderBy('Orden')
            ->get(['IdLugar as id', 'Lugar as nombre']);

        // Obtener comisionistas filtrados por cliente de la sesión
        $comisionistas = Comisionista::porContexto()
            ->with('identificador')
            ->orderBy('IdComisionista')
            ->get()
            ->map(fn($c) => [
                'id' => $c->IdComisionista,
                'nombre' => $c->identificador->Nombre ?? 'Sin nombre',
            ]);

        return Inertia::render('PuntoVenta/NuevaVenta', [
            'lugaresVenta' => $lugaresVenta,
            'comisionistas' => $comisionistas,
        ]);
    }

    /**
     * Guarda la selección y redirige al formulario de venta
     */
    public function store(Request $request)
    {
        $request->validate([
            'lugar_venta_id' => 'required|integer|exists:impuestos_ventas_lugar_venta,IdLugar',
            'comisionista_id' => 'required|integer|exists:impuestos_ventas_comisionitas,IdComisionista',
        ]);

        // Guardar en sesión los datos seleccionados
        session([
            'venta_lugar_id' => $request->lugar_venta_id,
            'venta_comisionista_id' => $request->comisionista_id,
        ]);

        return redirect()->route('ventas.formulario');
    }
}