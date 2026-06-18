<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class AlmacenController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // Obtener todas las sucursales del cliente
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);
        
        // 🔥 Sucursal seleccionada (SOLO si viene en la URL)
        $sucursalId = $request->get('sucursal_id');
        
        // Construir query base
        $query = Almacen::where('IdCliente', $clienteId)
            ->with('sucursal');
        
        // 🔥 SI hay sucursal_id en la URL, filtrar, sino mostrar TODAS
        if ($sucursalId) {
            $query->where('IdSucursal', $sucursalId);
        }
        
        $almacenes = $query->orderBy('IdSucursal')
            ->orderBy('Almacen')
            ->paginate(20)
            ->withQueryString();
        
        return Inertia::render('Gestion/Inventario/Almacen/Index', [
            'almacenes' => $almacenes,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalId, // Puede ser null
            'flash' => session()->only(['success', 'error']),
        ]);
    }
    
    public function store(Request $request)
    {
        Log::info('=== DATOS RECIBIDOS EN ALMACEN STORE ===', $request->all());
        
        try {
            $validated = $request->validate([
                'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
                'Almacen' => 'required|string|max:100',
                'AlmacenPrincipal' => 'required|boolean',
            ]);
            
            Log::info('✅ Validación pasada', $validated);
            
            $clienteId = session('cliente_id');
            
            if (!$clienteId) {
                return redirect()->back()->with('error', 'No hay cliente seleccionado en sesión');
            }
            
            // Si es principal, desmarcar otros principales de la misma sucursal
            if ($request->AlmacenPrincipal) {
                Almacen::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $request->sucursal_id)
                    ->update(['AlmacenPrincipal' => 0]);
            }
            
            $almacen = Almacen::create([
                'IdCliente' => $clienteId,
                'IdSucursal' => $request->sucursal_id,
                'Almacen' => $request->Almacen,
                'AlmacenPrincipal' => $request->AlmacenPrincipal ? 1 : 0,
            ]);
            
            Log::info('✅ Almacén creado con ID: ' . $almacen->IdAlmacen);
            
            return redirect()->back()->with('success', 'Almacén creado correctamente');
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Error de validación: ', $e->errors());
            return redirect()->back()->withErrors($e->errors())->with('error', 'Error en los datos del formulario');
        } catch (\Exception $e) {
            Log::error('❌ Error al crear almacén: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }
    
    public function update(Request $request, $id)
    {
        Log::info('=== DATOS RECIBIDOS EN ALMACEN UPDATE ===', $request->all());
        
        try {
            $validated = $request->validate([
                'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
                'Almacen' => 'required|string|max:100',
                'AlmacenPrincipal' => 'required|boolean',
            ]);
            
            $clienteId = session('cliente_id');
            
            $almacen = Almacen::where('IdCliente', $clienteId)
                ->where('IdAlmacen', $id)
                ->firstOrFail();
            
            // Si es principal, desmarcar otros principales de la misma sucursal
            if ($request->AlmacenPrincipal) {
                Almacen::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $request->sucursal_id)
                    ->where('IdAlmacen', '!=', $id)
                    ->update(['AlmacenPrincipal' => 0]);
            }
            
            $almacen->update([
                'IdSucursal' => $request->sucursal_id,
                'Almacen' => $request->Almacen,
                'AlmacenPrincipal' => $request->AlmacenPrincipal ? 1 : 0,
            ]);
            
            Log::info('✅ Almacén actualizado ID: ' . $id);
            
            return redirect()->back()->with('success', 'Almacén actualizado correctamente');
            
        } catch (\Exception $e) {
            Log::error('❌ Error al actualizar almacén: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al actualizar: ' . $e->getMessage());
        }
    }
    
    public function destroy($id)
    {
        try {
            $clienteId = session('cliente_id');
            $almacen = Almacen::where('IdCliente', $clienteId)
                ->where('IdAlmacen', $id)
                ->firstOrFail();
            
            $nombre = $almacen->Almacen;
            $almacen->delete();
            
            Log::info('✅ Almacén eliminado: ' . $nombre);
            
            return redirect()->back()->with('success', 'Almacén eliminado correctamente');
            
        } catch (\Exception $e) {
            Log::error('❌ Error al eliminar almacén: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }
}