<?php
// app/Http/Controllers/Facturacion/ImportarEmpresaController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Gestion\DatabaseService;
use App\Services\Facturacion\FacturacionApiService;
use App\Models\Gestion\Todos\Cliente;  // ← Usar el modelo Cliente de gestión
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportarEmpresaController extends Controller
{
    protected $databaseService;
    protected $facturacionApi;

    public function __construct(DatabaseService $databaseService, FacturacionApiService $facturacionApi)
    {
        $this->databaseService = $databaseService;
        $this->facturacionApi = $facturacionApi;
    }

    public function index()
    {
        // Obtener empresas de facturación desde la API
        $empresasResult = $this->facturacionApi->getEmpresas();
        $empresas = $empresasResult['data'] ?? [];
        
        return Inertia::render('Facturacion/Empresas/Importar', [
            'bases' => $this->databaseService->listarBasesMysql(),
            'empresas' => $empresas,  // Empresas desde facturación
        ]);
    }

    public function clientes(Request $request)
    {
        $request->validate(['db' => 'required|string']);
        
        $clientes = $this->databaseService->listarClientesEnBase($request->db);
        
        return response()->json([
            'clientes' => $clientes
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'db' => 'required|string',
            'idCliente' => 'required|integer',
            'modalidad' => 'required|integer',
            'ambiente' => 'required|integer',
            'token' => 'required|string',
            'codigo_sistema' => 'required|string',
        ]);
        
        Log::info('=== IMPORTANDO EMPRESA ===', $data);
        
        try {
            // 1. Obtener los datos del cliente desde gestión
            $cliente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table(DB::raw("`{$data['db']}`.`todos_cliente`"))
                ->where('IdCliente', $data['idCliente'])
                ->first();
                
            if (!$cliente) {
                return redirect()->back()->with('error', 'Cliente no encontrado en la base seleccionada');
            }
            
            Log::info('Cliente encontrado en gestión', [
                'id' => $cliente->IdCliente,
                'nombre' => $cliente->Nombre,
                'nit' => $cliente->NIT
            ]);
            
            // 2. Llamar a API de facturación para IMPORTAR (no crear)
            $resultado = $this->facturacionApi->importarEmpresa([
                'nombre' => $cliente->Nombre,
                'nit' => (string) $cliente->NIT,
                'modalidad' => (int) $data['modalidad'],
                'ambiente' => (int) $data['ambiente'],
                'token' => $data['token'],
                'codigo_sistema' => $data['codigo_sistema'],
                'idClienteGestion' => $cliente->IdCliente,
                'db' => $data['db'],
            ]);
            
            Log::info('Resultado de facturación', ['resultado' => $resultado]);
            
            if ($resultado['success'] ?? false) {
                return redirect()->route('facturacion.empresas.home')
                    ->with('success', 'Empresa importada/actualizada correctamente');
            }
            
            return redirect()->back()->with('error', $resultado['message'] ?? 'Error al importar');
            
        } catch (\Exception $e) {
            Log::error('Error importando empresa', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}