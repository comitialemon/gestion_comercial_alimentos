<?php
// app/Http/Controllers/Gestion/Impuestos/LugarVentaController.php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\LugarVenta;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class LugarVentaController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debes seleccionar una empresa primero');
        }

        // Obtener todas las sucursales del cliente
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        // Sucursal seleccionada (SOLO si viene en la URL)
        $sucursalId = $request->get('sucursal_id');

        // Construir query - SIEMPRE filtrar por cliente
        $query = LugarVenta::where('IdCliente', $clienteId)
            ->with(['sucursal']); // No necesitamos cliente porque ya es el mismo

        // Si hay filtro por sucursal
        if ($sucursalId) {
            $query->where('IdSucursal', $sucursalId);
        }

        $lugares = $query->orderBy('IdSucursal')
            ->orderBy('Orden')
            ->orderBy('Lugar')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Gestion/Impuestos/LugarVenta/Index', [
            'lugares' => $lugares,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalId,
            'contexto_actual' => [
                'cliente_id' => $clienteId,
                'cliente_nombre' => session('cliente_nombre'),
            ],
            'flash' => session()->only(['success', 'error']),
        ]);
    }

    public function getMaxOrden(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = $request->get('sucursal_id');
        
        if (!$clienteId || !$sucursalId) {
            return response()->json(['max_orden' => 0]);
        }
        
        $maxOrden = LugarVenta::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->max('Orden');
        
        return response()->json(['max_orden' => $maxOrden ?? 0]);
    }

    public function store(Request $request)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'sucursal_id' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_cliente_sucursal,IdClienteSucursal',
                'Lugar' => 'required|string|max:50',
                'Orden' => 'nullable|integer|min:0',
            ]);

            $sucursal = ClienteSucursal::where('IdClienteSucursal', $request->sucursal_id)
                ->where('IdCliente', $clienteId)
                ->first();
            
            if (!$sucursal) {
                return redirect()->back()->with('error', 'La sucursal seleccionada no pertenece a tu empresa');
            }

            $orden = $request->Orden;
            if ($orden === null || $orden === '' || $orden == 0) {
                $maxOrden = LugarVenta::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $request->sucursal_id)
                    ->max('Orden');
                $orden = ($maxOrden ?? 0) + 1;
            }

            LugarVenta::create([
                'Orden' => $orden,
                'Lugar' => $request->Lugar,
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->sucursal_id,
            ]);

            return redirect()->back()->with('success', 'Lugar de venta creado correctamente.');

        } catch (\Exception $e) {
            Log::error('Error creando lugar de venta: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }

        try {
            $validated = $request->validate([
                'sucursal_id' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_cliente_sucursal,IdClienteSucursal',
                'Lugar' => 'required|string|max:50',
                'Orden' => 'required|integer|min:0',
            ]);

            $lugar = LugarVenta::where('IdLugar', $id)
                ->where('IdCliente', $clienteId)
                ->firstOrFail();
            
            $sucursal = ClienteSucursal::where('IdClienteSucursal', $request->sucursal_id)
                ->where('IdCliente', $clienteId)
                ->first();
            
            if (!$sucursal) {
                return redirect()->back()->with('error', 'La sucursal seleccionada no pertenece a tu empresa');
            }
            
            $lugar->update([
                'Orden' => $request->Orden,
                'Lugar' => $request->Lugar,
                'IdSucursal' => $request->sucursal_id,
            ]);

            return redirect()->back()->with('success', 'Lugar de venta actualizado correctamente.');

        } catch (\Exception $e) {
            Log::error('Error actualizando lugar de venta: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        
        if (!$clienteId) {
            return redirect()->back()->with('error', 'No hay cliente seleccionado');
        }
        
        try {
            $lugar = LugarVenta::where('IdLugar', $id)
                ->where('IdCliente', $clienteId)
                ->firstOrFail();
            
            $nombre = $lugar->Lugar;
            $lugar->delete();

            return redirect()->back()->with('success', "Lugar de venta '{$nombre}' eliminado correctamente.");

        } catch (\Exception $e) {
            Log::error('Error eliminando lugar de venta: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }
}