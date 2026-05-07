<?php
// app/Http/Controllers/Gestion/Impuestos/LugarVentaController.php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\LugarVenta;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LugarVentaController extends Controller
{
    /**
     * Listado de lugares de venta
     */
    public function index(Request $request)
    {
        $query = LugarVenta::with(['cliente', 'sucursal']);

        if ($request->filled('cliente_id')) {
            $query->where('IdCliente', $request->cliente_id);
        }

        if ($request->filled('sucursal_id')) {
            $query->where('IdSucursal', $request->sucursal_id);
        }

        $lugares = $query->orderBy('Orden')
            ->orderBy('Lugar')
            ->paginate(20)
            ->withQueryString();

        $empresas = Cliente::orderBy('Nombre')->get(['IdCliente as id', 'Nombre as nombre']);
        
        $sucursales = [];
        if ($request->filled('cliente_id')) {
            $sucursales = ClienteSucursal::where('IdCliente', $request->cliente_id)
                ->orderBy('Nombre')
                ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        }

        return Inertia::render('Gestion/Impuestos/LugarVenta/Index', [
            'lugares' => $lugares,
            'filtros' => [
                'cliente_id' => $request->cliente_id,
                'sucursal_id' => $request->sucursal_id,
            ],
            'empresas' => $empresas,
            'sucursales' => $sucursales,
            'contexto_actual' => [
                'cliente_id' => session('cliente_id'),
                'cliente_nombre' => session('cliente_nombre'),
                'sucursal_id' => session('cliente_sucursal_id'),
                'sucursal_nombre' => session('cliente_sucursal_nombre'),
            ],
        ]);
    }

    /**
     * Obtener sucursales por empresa (AJAX)
     */
    public function getSucursales($clienteId)
    {
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        return response()->json($sucursales);
    }

    /**
     * Mostrar formulario para crear
     */
    public function create()
    {
        $empresas = Cliente::orderBy('Nombre')->get(['IdCliente as id', 'Nombre as nombre']);
        
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $sucursales = [];
        if ($clienteId) {
            $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
                ->orderBy('Nombre')
                ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        }

        return Inertia::render('Gestion/Impuestos/LugarVenta/Create', [
            'empresas' => $empresas,
            'sucursales' => $sucursales,
            'defaults' => [
                'IdCliente' => $clienteId,
                'IdSucursal' => $sucursalId,
            ],
            'editando' => false,
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('=== DATOS RECIBIDOS EN STORE ===');
        \Log::info($request->all());
        
        $request->validate([
            'Orden' => 'required|integer|min:0',
            'Lugar' => 'required|string|max:50',
            'IdCliente' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_cliente,IdCliente',
            'IdSucursal' => 'required|integer|exists:mysql_gestion_comercial_alimentos.todos_cliente_sucursal,IdClienteSucursal',
        ]);

        try {
            $lugar = LugarVenta::create([
                'Orden' => $request->Orden,
                'Lugar' => $request->Lugar,
                'IdCliente' => $request->IdCliente,
                'IdSucursal' => $request->IdSucursal,
            ]);
            
            \Log::info('✅ CREADO CON ID: ' . $lugar->IdLugar);
            
            return redirect()->route('gestion.lugar-venta.index')
                ->with('success', 'Lugar de venta creado correctamente.');
                
        } catch (\Exception $e) {
            \Log::error('❌ ERROR AL CREAR: ' . $e->getMessage());
            return back()->with('error', 'Error al crear: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Mostrar formulario para editar
     */
    public function edit($id)
    {
        $lugar = LugarVenta::findOrFail($id);
        
        $empresas = Cliente::orderBy('Nombre')->get(['IdCliente as id', 'Nombre as nombre']);
        
        $sucursales = ClienteSucursal::where('IdCliente', $lugar->IdCliente)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);

        return Inertia::render('Gestion/Impuestos/LugarVenta/Create', [
            'lugar' => $lugar,
            'empresas' => $empresas,
            'sucursales' => $sucursales,
            'editando' => true,
        ]);
    }

    public function update(Request $request, $id)
    {
        \Log::info('=== UPDATE LUGAR VENTA ===', ['id' => $id, 'data' => $request->all()]);
        
        $request->validate([
            'Orden' => 'required|integer|min:0',
            'Lugar' => 'required|string|max:50',
            'IdCliente' => 'required|integer',
            'IdSucursal' => 'required|integer',
        ]);

        $lugar = LugarVenta::findOrFail($id);
        
        $lugar->update([
            'Orden' => $request->Orden,
            'Lugar' => $request->Lugar,
            'IdCliente' => $request->IdCliente,
            'IdSucursal' => $request->IdSucursal,
        ]);

        return redirect()->route('gestion.lugar-venta.index')
            ->with('success', 'Lugar de venta actualizado correctamente.');
    }

    /**
     * Eliminar lugar
     */
    public function destroy($id)
    {
        $lugar = LugarVenta::findOrFail($id);
        $lugar->delete();

        return redirect()->route('gestion.lugar-venta.index')
            ->with('success', 'Lugar de venta eliminado correctamente.');
    }
}