<?php
// app/Http/Controllers/Facturacion/EmpresasHomeController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Inertia\Inertia;

class EmpresasHomeController extends Controller
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    public function index()
    {
        $empresas = $this->facturacionApi->getEmpresas();
        
        return Inertia::render('Facturacion/Empresas/Home', [
            'empresas' => $empresas['data'] ?? [],
            'flash' => session()->only(['success', 'error'])
        ]);
    }
}