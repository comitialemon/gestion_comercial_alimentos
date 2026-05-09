<?php
// app/Http/Controllers/Facturacion/EmpresaController.php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Services\Gestion\DatabaseService;
use App\Services\Facturacion\EmpresaCreationService;  // ← Importante
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class EmpresaController extends Controller
{
    protected $databaseService;
    protected $empresaCreationService;  // ← Declarar la propiedad

    // ← Asegurar que el constructor recibe ambos servicios
    public function __construct(
        DatabaseService $databaseService,
        EmpresaCreationService $empresaCreationService  // ← Inyectar aquí
    ) {
        $this->databaseService = $databaseService;
        $this->empresaCreationService = $empresaCreationService;
    }

    public function create()
    {
        $bases = $this->databaseService->listarBasesMysql();
        
        return Inertia::render('Facturacion/Empresas/Create', [
            'bases' => $bases,
        ]);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'db' => 'required|string',
            'nombre' => 'required|string|max:255',
            'nit' => 'required|string|max:20',
            'modalidad' => 'required|integer',      // ← Ahora REQUERIDO
            'ambiente' => 'required|integer',       // ← Ahora REQUERIDO
            'direccion' => 'nullable|string',
            'fono' => 'nullable|string',
            'celular' => 'nullable|string',
            'ci_rep' => 'nullable|string',
            'rep' => 'nullable|string',
            'token' => 'required|string',           // ← Ahora REQUERIDO
            'codigo_sistema' => 'required|string',  // ← Ahora REQUERIDO
        ]);
        
        // Obtener el ID de fecha
        [$idFecha, $fecha] = $this->databaseService->ultimoIdFechaEnBase($data['db']);
        if ($idFecha === 0) {
            return redirect()->back()->with('error', 'No se encontró fecha en todos_fecha');
        }
        
        $data['id_fecha'] = $idFecha;
        
        $resultado = $this->empresaCreationService->crearEmpresa($data);
        
        if ($resultado['success']) {
            return redirect()->route('facturacion.empresas.home')
                ->with('success', $resultado['message']);
        }
        
        return redirect()->back()->with('error', $resultado['message']);
    }   

    public function ultimoIdFecha(Request $request)
    {
        $request->validate(['db' => 'required|string']);
        [$id, $fecha] = $this->databaseService->ultimoIdFechaEnBase($request->db);
        return response()->json(['id_fecha' => $id, 'fecha' => $fecha]);
    }
}