<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Models\Gestion\Contabilidad\Moneda;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class CuentaController extends Controller
{
    /**
     * Vista de solo lectura (listado visual sin opciones de modificar)
     */
    public function index()
    {
        $cuentas = ContaCuenta::porContexto()
            ->with('moneda')  // 🔥 Cargar la relación con moneda
            ->orderBy('Cuenta')
            ->get();

        return Inertia::render('Gestion/Contabilidad/Cuentas/Index', [
            'cuentas' => $cuentas,
            'soloLectura' => true,
        ]);
    }
}