<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiatOperacionesController extends Controller
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    /**
     * Mostrar vista de cierre de operaciones
     */
    public function showCierre()
    {
        $contexto = [
            'cliente_nombre' => session('cliente_nombre'),
            'cliente_nit' => session('cliente_nit'),
            'sucursal_nombre' => session('sucursal_nombre'),
            'sucursal_numero' => session('sucursal_numero'),
            'punto_venta_nombre' => session('punto_venta_nombre'),
            'punto_venta_codigo' => session('punto_venta_codigo'),
            'punto_venta_id' => session('punto_venta_id'),
            'ambiente' => session('ambiente_facturacion'),
            'modalidad' => session('modalidad_facturacion'),
        ];

        return Inertia::render('Facturacion/Operaciones/Cierre', [
            'contexto' => $contexto,
            'flash' => session()->only(['success', 'error'])
        ]);
    }

    /**
     * Ejecutar cierre de operaciones
     */
    public function cierre(Request $request)
    {
        $resultado = $this->facturacionApi->cerrarOperaciones();

        if (!$resultado['ok']) {
            return redirect()->back()->with('error', $resultado['mensaje'] ?? 'Error al cerrar operaciones');
        }

        // Limpiar CUIS y CUFD de sesión para forzar recarga
        session()->forget(['cuis_vigente', 'cufd_vigente']);

        return redirect()->back()->with('success', $resultado['mensaje'] ?? '✅ Cierre de operaciones exitoso');
    }
}