<?php
// app/Http/Controllers/Facturacion/SucursalController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Gestion\DatabaseService;
use App\Services\Facturacion\FacturacionApiService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class SucursalController extends Controller
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

    public function create()
    {
        $bases = $this->databaseService->listarBasesMysql();
        $municipios = $this->facturacionApi->getMunicipios();
        
        return Inertia::render('Facturacion/Sucursales/Create', [
            'bases' => $bases,
            'municipios' => $municipios['data'] ?? [],
        ]);
    }

    public function store(Request $request)
    {
        Log::info('=== CREANDO SUCURSAL DESDE GESTIÓN ===', $request->all());
        
        $data = $request->validate([
            'db' => 'required|string',
            'idCliente' => 'required|integer',
            'numero' => 'required|integer',
            'nombre' => 'required|string|max:255',
            'direccion' => 'required|string|max:255',
            'idMunicipio' => 'nullable|integer',
            'idPlaza' => 'required|integer',
            'telefono' => 'required|string|max:30',
            'celular' => 'required|string|max:30',
            'categoria' => 'required|string|max:20',
            'activoInactivo' => 'required|boolean',
            'orden' => 'nullable|integer',
        ]);

        try {
            // 1️⃣ Obtener datos del cliente desde gestión
            $cliente = $this->databaseService->obtenerClientePorId($data['db'], $data['idCliente']);
            
            if (!$cliente) {
                return redirect()->back()->withInput()->with('error', 'Cliente no encontrado');
            }

            // 2️⃣ Crear sucursal en gestión
            $sucursalGestion = $this->databaseService->crearSucursalEnGestion($data);
            
            if (!$sucursalGestion['success']) {
                return redirect()->back()->withInput()->with('error', 'Error en gestión: ' . $sucursalGestion['message']);
            }

            // 3️⃣ Preparar payload para facturación
            $idMunicipio = !empty($data['idMunicipio']) ? (int) $data['idMunicipio'] : null;
            
            $payloadFacturacion = [
                'idClienteGestion' => (int) $data['idCliente'],
                'idSucursalGestion' => (int) $sucursalGestion['id'],
                'nombre' => $data['nombre'],
                'codigo' => (int) $data['numero'],
                'direccion' => $data['direccion'],
                'idMunicipio' => $idMunicipio,
                'activo' => (bool) $data['activoInactivo'],
                'nit_empresa' => (string) $cliente->NIT,
                'db' => $data['db'],
            ];
            
            Log::info('Enviando a facturación', $payloadFacturacion);
            
            // 4️⃣ Llamar a API de facturación
            $resultado = $this->facturacionApi->crearSucursal($payloadFacturacion);
            
            Log::info('Respuesta de facturación', $resultado);

            if (isset($resultado['success']) && $resultado['success'] === true) {
                return redirect()->route('facturacion.sucursales.home')
                    ->with('success', '✅ Sucursal creada correctamente en ambas bases');
            }

            $errorMsg = $resultado['message'] ?? 'Error desconocido al crear en facturación';
            return redirect()->back()->withInput()->with('error', '❌ ' . $errorMsg);

        } catch (\Exception $e) {
            Log::error('Error creando sucursal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->withInput()->with('error', '❌ Error: ' . $e->getMessage());
        }
    }

    // AJAX: Obtener clientes por base
    public function clientes(Request $request)
    {
        $request->validate(['db' => 'required|string']);
        
        $clientes = $this->databaseService->listarClientesEnBase($request->db);
        
        return response()->json(['clientes' => $clientes]);
    }

    // AJAX: Obtener plazas por base
    public function plazas(Request $request)
    {
        $request->validate(['db' => 'required|string']);
        
        $plazas = $this->databaseService->listarPlazasEnBase($request->db);
        
        return response()->json(['plazas' => $plazas]);
    }

    // AJAX: Obtener empresa por cliente
    public function empresaPorCliente(Request $request)
    {
        $request->validate([
            'db' => 'required|string',
            'idCliente' => 'required|integer',
        ]);
        
        $empresa = $this->databaseService->obtenerEmpresaPorCliente($request->db, $request->idCliente);
        
        return response()->json(['empresa' => $empresa]);
    }
}