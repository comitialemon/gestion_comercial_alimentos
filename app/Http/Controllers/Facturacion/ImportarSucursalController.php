<?php
// app/Http/Controllers/Facturacion/ImportarSucursalController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Gestion\DatabaseService;
use App\Services\Facturacion\FacturacionApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportarSucursalController extends Controller
{
    protected $databaseService;
    protected $facturacionApi;

    public function __construct(
        DatabaseService $databaseService,
        FacturacionApiService $facturacionApi
    ) {
        $this->databaseService = $databaseService;
        $this->facturacionApi = $facturacionApi;
    }

    public function index()
    {
        $bases = $this->databaseService->listarBasesMysql();
        $municipios = $this->facturacionApi->getMunicipios();
        
        return Inertia::render('Facturacion/Sucursales/Importar', [
            'bases' => $bases,
            'municipios' => $municipios['data'] ?? [],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'db' => 'required|string',
            'idClienteSucursal' => 'required|integer',
            'idMunicipio' => 'nullable|integer',
        ]);

        try {
            // 1. Obtener datos de la sucursal desde gestión
            $sucursalGestion = $this->databaseService->obtenerSucursalPorId($data['db'], $data['idClienteSucursal']);
            
            if (!$sucursalGestion) {
                return redirect()->back()->with('error', 'Sucursal no encontrada');
            }

            // 2. Llamar a API de facturación para importar
            $resultado = $this->facturacionApi->importarSucursal([
                'idClienteGestion' => $sucursalGestion->IdCliente,
                'idSucursalGestion' => $sucursalGestion->IdClienteSucursal,
                'nombre' => $sucursalGestion->Nombre,
                'codigo' => $sucursalGestion->NumeroSucursal,
                'direccion' => $sucursalGestion->Direccion,
                'idMunicipio' => $data['idMunicipio'],
                'activo' => $sucursalGestion->ActivoInactivo === 0 ? 1 : 0,
                'nit_empresa' => $sucursalGestion->nit ?? null,
                'db' => $data['db'],
            ]);

            if ($resultado['success'] ?? false) {
                return redirect()->route('facturacion.sucursales.home')
                    ->with('success', 'Sucursal importada correctamente');
            }

            return redirect()->back()->with('error', $resultado['message'] ?? 'Error al importar');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // AJAX: Obtener clientes por base
    public function clientes(Request $request)
    {
        $request->validate(['db' => 'required|string']);
        
        $clientes = $this->databaseService->listarClientesEnBase($request->db);
        
        return response()->json(['clientes' => $clientes]);
    }

    // AJAX: Obtener sucursales de un cliente
    public function sucursales(Request $request)
    {
        $request->validate([
            'db' => 'required|string',
            'idCliente' => 'required|integer',
        ]);
        
        $sucursales = $this->databaseService->listarSucursalesClienteEnBase($request->db, $request->idCliente);
        
        return response()->json(['sucursales' => $sucursales]);
    }
}