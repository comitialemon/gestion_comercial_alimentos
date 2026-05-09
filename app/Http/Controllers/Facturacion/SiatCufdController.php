<?php
// app/Http/Controllers/Facturacion/SiatCufdController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiatCufdController extends Controller
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    public function vigente()
    {
        $ctx = [
            'cliente_nombre' => session('cliente_nombre'),
            'cliente_nit' => session('cliente_nit'),
            'sucursal_nombre' => session('sucursal_nombre'),
            'sucursal_numero' => session('sucursal_numero'),
            'punto_venta_nombre' => session('punto_venta_nombre'),
            'punto_venta_codigo' => session('punto_venta_codigo'),
            'ambiente' => session('ambiente_facturacion'),
            'modalidad' => session('modalidad_facturacion'),
        ];
        
        $resultado = $this->facturacionApi->getCufdVigente();
        $historial = $this->facturacionApi->getCufdHistorial();
        
        return Inertia::render('Facturacion/SiatCUFD/Vigente', [
            'contexto' => $ctx,
            'cufd' => $resultado,
            'historial' => $historial['data'] ?? [],
            'flash' => session()->only(['success', 'error'])
        ]);
    }

    public function solicitar(Request $request)
    {
        $resultado = $this->facturacionApi->solicitarCufd();
        
        if (!$resultado['success']) {
            return redirect()->back()->with('error', $resultado['message']);
        }
        
        return redirect()->back()->with('success', $resultado['message']);
    }
}