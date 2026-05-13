<?php
// app/Http/Controllers/Facturacion/SiatCatalogoController.php - GESTIÓN (CORREGIDO)
// SOLO ESTE ARCHIVO, NO TOQUES EL SERVICE

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class SiatCatalogoController extends Controller
{
    protected $facturacionApi;

    public function __construct(FacturacionApiService $facturacionApi)
    {
        $this->facturacionApi = $facturacionApi;
    }

    /**
     * Mostrar panel de sincronización de catálogos
     */
    public function index()
    {
        // ✅ Contexto para la vista
        $contexto = [
            'nit' => session('cliente_nit'),
            'sucursal' => session('sucursal_numero'),
            'punto_venta' => session('punto_venta_codigo'),
            'cuis' => session('cuis_vigente'),
            'ambiente' => session('ambiente_facturacion'),
            'modalidad' => session('modalidad_facturacion'),
        ];
        
        Log::info('=== SIAT CATÁLOGOS INDEX ===', $contexto);
        
        // ✅ Llamar al método que YA EXISTE en tu FacturacionApiService
        $statusResult = $this->facturacionApi->getCatalogosStatus();
        
        Log::info('Respuesta de getCatalogosStatus', $statusResult);
        
        return Inertia::render('Facturacion/SiatCatalogos/Index', [
            'contexto' => $contexto,
            'status' => $statusResult['data'] ?? [],
            'flash' => session()->only(['ok', 'error', 'success'])
        ]);
    }

    /**
     * Sincronizar TODOS los catálogos
     */
    public function syncAll()
    {
        Log::info('=== Sincronizando TODOS los catálogos ===');
        
        // ✅ Llamar al método que YA EXISTE en tu FacturacionApiService
        $result = $this->facturacionApi->sincronizarCatalogos();
        
        Log::info('Resultado syncAll', $result);
        
        if (isset($result['error']) && $result['error'] === true) {
            return redirect()->back()->with('error', $result['message'] ?? 'Error al sincronizar catálogos');
        }
        
        return redirect()->back()->with('success', '✅ Catálogos sincronizados correctamente');
    }

    /**
     * Sincronizar UN catálogo específico
     */
    public function syncOne($key)
    {
        Log::info("=== Sincronizando catálogo: {$key} ===");
        
        // ✅ Llamar al método que YA EXISTE en tu FacturacionApiService
        $result = $this->facturacionApi->sincronizarCatalogo($key);
        
        Log::info("Resultado syncOne {$key}", $result);
        
        if (isset($result['error']) && $result['error'] === true) {
            return redirect()->back()->with('error', $result['message'] ?? "Error al sincronizar {$key}");
        }
        
        return redirect()->back()->with('success', "✅ Catálogo {$key} sincronizado correctamente");
    }

    /**
     * Probar fecha/hora con SIAT
     */
    public function pingFechaHora()
    {
        Log::info('=== Probando fecha/hora con SIAT ===');
        
        // ✅ Llamar al método que YA EXISTE en tu FacturacionApiService
        $result = $this->facturacionApi->pingFechaHora();
        
        Log::info('Resultado pingFechaHora', $result);
        
        if (isset($result['error']) && $result['error'] === true) {
            return redirect()->back()->with('error', $result['message'] ?? 'Error al probar fecha/hora');
        }
        
        $fechaHora = $result['fechaHora'] ?? null;
        return redirect()->back()
            ->with('ok', 'Fecha/hora obtenida correctamente')
            ->with('ping', ['fechaHora' => $fechaHora]);
    }
}