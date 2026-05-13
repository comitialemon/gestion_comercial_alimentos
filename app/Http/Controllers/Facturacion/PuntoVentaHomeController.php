<?php
// app/Http/Controllers/Facturacion/PuntoVentaHomeController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Inertia\Inertia;

class PuntoVentaHomeController extends Controller
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    public function index()
    {
        $empresaId = session('empresa_id_facturacion');
        $sucursalId = session('sucursal_id_facturacion');
        
        $puntos = [];
        if ($sucursalId) {
            $resultado = $this->facturacionApi->getPuntosVenta($sucursalId);
            $puntos = $resultado['data'] ?? [];
        }
        
        return Inertia::render('Facturacion/PuntoVenta/Home', [
            'puntos' => $puntos,
            'empresaId' => $empresaId,
            'sucursalId' => $sucursalId,
        ]);
    }
}