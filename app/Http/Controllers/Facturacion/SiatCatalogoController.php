<?php
// app/Http/Controllers/Facturacion/SiatCatalogoController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SiatCatalogoController extends Controller
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    public function index()
    {
        $ctx = [
            'nit' => session('cliente_nit'),
            'sucursal' => session('sucursal_codigo'),
            'punto_venta' => session('punto_venta_codigo'),
            'cuis' => session('cuis_vigente'),
            'ambiente' => session('ambiente_facturacion'),
            'modalidad' => session('modalidad_facturacion'),
        ];
        
        $status = $this->facturacionApi->getCatalogosStatus();
        
        return Inertia::render('Facturacion/SiatCatalogos/Index', [
            'contexto' => $ctx,
            'status' => $status['data'] ?? [],
            'flash' => session()->only(['ok', 'error', 'success'])
        ]);
    }

    public function syncAll()
    {
        $result = $this->facturacionApi->sincronizarCatalogos();
        
        if (isset($result['error']) && $result['error'] === true) {
            return redirect()->back()->with('error', $result['message'] ?? 'Error al sincronizar catálogos');
        }
        
        return redirect()->back()->with('success', 'Catálogos sincronizados correctamente');
    }

    public function syncOne($key)
    {
        $result = $this->facturacionApi->sincronizarCatalogo($key);
        
        if (isset($result['error']) && $result['error'] === true) {
            return redirect()->back()->with('error', $result['message'] ?? "Error al sincronizar {$key}");
        }
        
        return redirect()->back()->with('success', "Catálogo {$key} sincronizado correctamente");
    }

    public function pingFechaHora()
    {
        $result = $this->facturacionApi->pingFechaHora();
        
        if (isset($result['error']) && $result['error'] === true) {
            return redirect()->back()->with('error', $result['message'] ?? 'Error al probar fecha/hora');
        }
        
        return redirect()->back()->with('ok', 'Fecha/hora obtenida correctamente')->with('ping', $result);
    }
}