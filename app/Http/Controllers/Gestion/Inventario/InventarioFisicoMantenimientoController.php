<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\InventarioFisico;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventarioFisicoMantenimientoController extends Controller
{
    /**
     * Vista para gestión de estados (Activar/Inactivar inventarios físicos) - CON SUCURSALES
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        Log::info('=== GESTION ESTADO INVENTARIOS FISICOS ===');
        Log::info('Cliente ID: ' . $clienteId);
        Log::info('Sucursal actual: ' . $sucursalId);
        Log::info('Sucursal seleccionada en filtro: ' . $request->sucursal_id);
        
        // =============================================
        // OBTENER TODAS LAS SUCURSALES DEL CLIENTE
        // =============================================
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        Log::info('Sucursales encontradas: ' . $sucursales->count());
        
        // =============================================
        // CONSULTA PRINCIPAL - SIN usar scope porContexto()
        // =============================================
        $query = InventarioFisico::where('IdCliente', $clienteId)
            ->with(['sucursal', 'realizadoPor', 'encargadoSucursal', 'fecha']);
        
        // 🔥 FILTRO POR SUCURSAL
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('IdSucursal', $request->sucursal_id);
            Log::info('Filtrando por sucursal: ' . $request->sucursal_id);
        } else {
            // Por defecto, mostrar la sucursal logueada
            $query->where('IdSucursal', $sucursalId);
            Log::info('Filtrando por sucursal actual: ' . $sucursalId);
        }
        
        // 🔥 FILTRO POR ESTADO
        if ($request->filled('estado')) {
            if ($request->estado === 'activos') {
                $query->where('ActivoInactivo', 1);
                Log::info('Filtrando por activos');
            } elseif ($request->estado === 'inactivos') {
                $query->where('ActivoInactivo', 0);
                Log::info('Filtrando por inactivos');
            }
        }
        
        // 🔥 BUSCADOR por número de inventario
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $query->where('NumeroCorrelativo', 'LIKE', "%{$buscar}%");
            Log::info('Buscando: ' . $buscar);
        }
        
        $inventarios = $query->orderBy('IdFisico', 'desc')->paginate(20);
        
        // 🔥🔥🔥 IMPORTANTE: MANTENER LOS FILTROS EN LA PAGINACIÓN 🔥🔥🔥
        $inventarios->appends($request->all());
        
        Log::info('Inventarios encontrados: ' . $inventarios->count());
        
        // Enriquecer datos
        $inventarios->getCollection()->transform(function ($inventario) {
            // 🔥 Número de diario (si existe)
            $numeroDiario = null;
            if ($inventario->IdDiario && $inventario->IdDiario > 0) {
                $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario')
                    ->where('IdDiario', $inventario->IdDiario)
                    ->value('NumeroDiario');
            }
            $inventario->numero_diario = $numeroDiario ?? '-';
            
            // ✅ Fecha formateada desde la relación 'fecha'
            if ($inventario->fecha && $inventario->fecha->Fecha) {
                $fechaObj = new \DateTime($inventario->fecha->Fecha);
                $inventario->fecha_formateada = $fechaObj->format('d/m/Y');
            } else {
                $inventario->fecha_formateada = '-';
            }
            
            // 🔥 Agregar nombre de sucursal
            $inventario->sucursal_nombre = $inventario->sucursal?->Nombre ?? 'Sin sucursal';
            $inventario->sucursal_numero = $inventario->sucursal?->NumeroSucursal ?? null;
            
            return $inventario;
        });
        
        return Inertia::render('Gestion/Inventario/InventarioFisicoMantenimiento/Index', [
            'inventarios' => $inventarios,
            'sucursales' => $sucursales,
            'sucursalActual' => $sucursalId,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
            'sucursalSeleccionada' => $request->sucursal_id,
        ]);
    }

    /**
     * Cambiar estado (SOLO DESACTIVAR - Activo → Borrador)
     */
    public function updateEstado(Request $request, $id)
    {
        try {
            $inventario = InventarioFisico::where('IdCliente', session('cliente_id'))
                ->where('IdFisico', $id)
                ->firstOrFail();
            
            if ($inventario->ActivoInactivo == 1) {
                $inventario->update(['ActivoInactivo' => 0]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Inventario desactivado correctamente (pasó a Borrador)',
                    'nuevo_estado' => 0
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Este inventario ya está en estado BORRADOR. Solo se activa al editarlo y guardarlo.'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de inventario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado: ' . $e->getMessage()
            ], 500);
        }
    }
}