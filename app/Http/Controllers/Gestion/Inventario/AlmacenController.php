<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlmacenController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // Obtener todas las sucursales del cliente
        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);
        
        // Sucursal seleccionada (por defecto la primera o la que viene en la URL)
        $sucursalId = $request->get('sucursal_id', $sucursales->first()->id ?? null);
        
        // 🔥 Obtener almacenes de la sucursal seleccionada CON LA RELACIÓN
        $almacenes = Almacen::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->with('sucursal')  // 👈 CARGAR LA RELACIÓN
            ->orderBy('Almacen')
            ->paginate(20)
            ->withQueryString();
        
        return Inertia::render('Gestion/Inventario/Almacen/Index', [
            'almacenes' => $almacenes,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalId,
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Almacen' => 'required|string',
            'AlmacenPrincipal' => 'required|boolean',
        ]);
        
        $clienteId = session('cliente_id');
        
        // Si es principal, desmarcar otros principales de la misma sucursal
        if ($request->AlmacenPrincipal) {
            Almacen::where('IdCliente', $clienteId)
                ->where('IdSucursal', $request->sucursal_id)
                ->update(['AlmacenPrincipal' => 0]);
        }
        
        Almacen::create([
            'IdCliente' => $clienteId,
            'IdSucursal' => $request->sucursal_id,
            'Almacen' => $request->Almacen,
            'AlmacenPrincipal' => $request->AlmacenPrincipal,
        ]);
        
        return redirect()->back()->with('success', 'Almacén creado correctamente');
    }
    
    public function update(Request $request, $id)
    {
        $request->validate([
            'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Almacen' => 'required|string',
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
            'AlmacenPrincipal' => $request->AlmacenPrincipal,
        ]);
        
        return redirect()->back()->with('success', 'Almacén actualizado correctamente');
    }
    
    public function destroy($id)
    {
        $clienteId = session('cliente_id');
        $almacen = Almacen::where('IdCliente', $clienteId)
            ->where('IdAlmacen', $id)
            ->firstOrFail();
        
        $almacen->delete();
        
        return redirect()->back()->with('success', 'Almacén eliminado correctamente');
    }
}