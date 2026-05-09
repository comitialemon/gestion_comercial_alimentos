<?php
// app/Http/Controllers/Facturacion/SucursalesHomeController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Inertia\Inertia;

class SucursalesHomeController extends Controller
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    public function index()
    {
        $empresaId = session('empresa_id_facturacion');
        
        $sucursales = [];
        if ($empresaId) {
            $resultado = $this->facturacionApi->getSucursales($empresaId);
            $sucursales = $resultado['data'] ?? [];
        }
        
        return Inertia::render('Facturacion/Sucursales/Home', [
            'sucursales' => $sucursales,
            'empresaId' => $empresaId,
            'flash' => session()->only(['success', 'error'])
        ]);
    }
}