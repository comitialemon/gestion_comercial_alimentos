<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Facturacion\FacturacionApiService;
use App\Services\Facturacion\MetodoPagoMapeoService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MetodoPagoMapeoController extends Controller
{
    protected $facturacionApi;
    protected $mapeoService;

    public function __construct(
        FacturacionApiService $facturacionApi,
        MetodoPagoMapeoService $mapeoService
    ) {
        $this->facturacionApi = $facturacionApi;
        $this->mapeoService = $mapeoService;
    }

    public function index()
    {
        $metodosPago = $this->facturacionApi->getMetodosPago();
        $cuentasContables = $this->mapeoService->getCuentasContables();
        $mapeos = $this->mapeoService->getMapeosExistentes();
        
        $mapeosPorMetodo = [];
        foreach ($mapeos as $m) {
            if (!isset($mapeosPorMetodo[$m->codigo_siat])) {
                $mapeosPorMetodo[$m->codigo_siat] = [];
            }
            if (!in_array($m->idContaCuenta, $mapeosPorMetodo[$m->codigo_siat])) {
                $mapeosPorMetodo[$m->codigo_siat][] = $m->idContaCuenta;
            }
        }
        
        return Inertia::render('Facturacion/MetodoPagoMapeo', [
            'metodosPago' => $metodosPago,
            'cuentasContables' => $cuentasContables,
            'mapeosPorMetodo' => $mapeosPorMetodo,
            'clienteId' => session('cliente_id'),
            'sucursalId' => session('cliente_sucursal_id'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'codigo_siat' => 'required|integer',
            'idContaCuenta' => 'required|integer',
            'idCliente' => 'required|integer',
            'idSucursal' => 'required|integer',
            'activo' => 'required|boolean',
        ]);

        $this->mapeoService->guardarMapeo(
            $request->codigo_siat,
            $request->idContaCuenta,
            $request->activo,
            $request->idCliente,
            $request->idSucursal
        );

        return response()->json(['success' => true]);
    }
}