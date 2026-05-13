<?php
// app/Http/Controllers/Facturacion/SiatCuisController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Inertia\Inertia;
use Illuminate\Http\Request;

class SiatCuisController extends Controller
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
            'punto_venta_id' => session('punto_venta_id'),  // ← AGREGAR ESTO
            'ambiente' => session('ambiente_facturacion'),
            'modalidad' => session('modalidad_facturacion'),
        ];
        
        $resultado = $this->facturacionApi->getCuisVigente();
        $historial = $this->facturacionApi->getCuisHistorial();
        
        return Inertia::render('Facturacion/SiatCUIS/Vigente', [
            'contexto' => $ctx,
            'cuis' => $resultado,
            'historial' => $historial['data'] ?? [],
            'flash' => session()->only(['success', 'error'])
        ]);
    }

    public function solicitar(Request $request)
    {
        $resultado = $this->facturacionApi->solicitarCuis();
        
        if (!$resultado['success']) {
            return redirect()->back()->with('error', $resultado['message']);
        }
        
        return redirect()->back()->with('success', $resultado['message']);
    }
}