<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\InventarioFisico;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventarioFisicoMantenimientoController extends Controller
{
    /**
     * Listado de inventarios físicos con filtros
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        $query = InventarioFisico::where('IdCliente', $clienteId)
            ->with(['sucursal', 'realizadoPor', 'fecha']);
        
        // Filtro por sucursales múltiples (checkboxes)
        if ($request->filled('sucursales')) {
            $sucursalesArray = explode(',', $request->sucursales);
            $query->whereIn('IdSucursal', $sucursalesArray);
        }
        
        // Filtro por realizado por (múltiples)
        if ($request->filled('realizados_por')) {
            $realizadosArray = explode(',', $request->realizados_por);
            $query->whereIn('IdRealizadoPor', $realizadosArray);
        }
        
        // Filtro por estado (ActivoInactivo)
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('ActivoInactivo', $request->estado);
        }
        
        // Búsqueda por número correlativo
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('NumeroCorrelativo', 'like', "%{$search}%");
        }
        
        $inventarios = $query->orderBy('NumeroCorrelativo', 'desc')
            ->paginate(20)
            ->withQueryString();
        
        // 🔥 DATOS PARA FILTROS (checkboxes)
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero'])
            ->map(function($s) use ($clienteId, $request) {
                // Contar inventarios por sucursal
                $count = InventarioFisico::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $s->id)
                    ->count();
                $s->inventarios_count = $count;
                return $s;
            });
        
        // Obtener realizados por (identificadores que han hecho inventarios)
        $realizadosPor = Identificador::whereIn('IdIdentificador', function($q) use ($clienteId) {
                $q->select('IdRealizadoPor')
                  ->from('inventario_fisicorealizado')
                  ->where('IdCliente', $clienteId)
                  ->groupBy('IdRealizadoPor');
            })
            ->orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT', 'Nombre'])
            ->map(function($i) use ($clienteId, $request) {
                $count = InventarioFisico::where('IdCliente', $clienteId)
                    ->where('IdRealizadoPor', $i->id)
                    ->count();
                $i->inventarios_count = $count;
                $i->display = "{$i->CI_NIT} - {$i->Nombre}";
                return $i;
            });
        
        // Estadísticas
        $totalActivos = InventarioFisico::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->count();
        $totalInactivos = InventarioFisico::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->count();
        
        return Inertia::render('Gestion/Inventario/InventarioFisicoMantenimiento/Index', [
            'inventarios' => $inventarios,
            'sucursales' => $sucursales,
            'realizadosPor' => $realizadosPor,
            'totalActivos' => $totalActivos,
            'totalInactivos' => $totalInactivos,
            'filtros' => [
                'sucursales' => $request->sucursales,
                'realizados_por' => $request->realizados_por,
                'estado' => $request->estado,
                'search' => $request->search,
            ],
        ]);
    }
    
    /**
     * Actualizar estado (ActivoInactivo) de un inventario
     */
    public function updateEstado(Request $request, $id)
    {
        $request->validate([
            'ActivoInactivo' => 'required|in:0,1',
        ]);
        
        $clienteId = session('cliente_id');
        
        $inventario = InventarioFisico::where('IdCliente', $clienteId)
            ->findOrFail($id);
        
        $inventario->update([
            'ActivoInactivo' => $request->ActivoInactivo,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => $request->ActivoInactivo == 1 ? 'Inventario activado correctamente' : 'Inventario desactivado correctamente'
        ]);
    }
}