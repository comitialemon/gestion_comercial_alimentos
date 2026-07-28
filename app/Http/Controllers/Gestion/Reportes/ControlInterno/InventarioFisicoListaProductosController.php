<?php

namespace App\Http\Controllers\Gestion\Reportes\ControlInterno;

use App\Http\Controllers\Controller;
use App\Services\Reportes\InventarioFisicoExcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventarioFisicoListaProductosController extends Controller
{
    /**
     * Muestra el formulario del reporte
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        
        // Obtener sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);
        
        return Inertia::render('Gestion/Reportes/ControlInterno/InventarioFisicoListaProductos', [
            'sucursales' => $sucursales,
        ]);
    }

    /**
     * Genera el Excel con la lista de productos para inventario físico
     */
    public function generarExcel(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|integer|exists:todos_cliente_sucursal,IdClienteSucursal',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = $request->sucursal_id;

        try {
            $excelService = new InventarioFisicoExcelService();
            return $excelService->generar($clienteId, $sucursalId);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar el archivo: ' . $e->getMessage());
        }
    }
}