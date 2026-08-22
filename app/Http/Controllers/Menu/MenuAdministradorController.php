<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Menu\MenuAdministrador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class MenuAdministradorController extends Controller
{
    private function getNextNodeOrder(int $parentId): int
    {
        $maxOrder = MenuAdministrador::where('Parent', $parentId)
            ->max('Node_Order');
        
        return ($maxOrder ?? 0) + 1;
    }

    public function index()
    {
        $menus = MenuAdministrador::orderBy('Parent')
            ->orderBy('Node_Order')
            ->get();

        $columnasPermisos = MenuAdministrador::getPermisoColumns();

        return Inertia::render('Gestion/Menu/MenuAdministrador', [
            'menus' => $menus,
            'columnasPermisos' => $columnasPermisos
        ]);
    }

    /**
     * Actualizar texto o enlace de un menú
     */
    public function update(Request $request, $id)
    {
        $menu = MenuAdministrador::findOrFail($id);

        // Si viene columna, es un toggle de permiso
        if ($request->has('columna')) {
            $request->validate([
                'columna' => 'required|string',
                'valor' => 'required|boolean'
            ]);

            // ✅ Verificar que la columna existe antes de actualizar
            $columnas = MenuAdministrador::getPermisoColumns();
            if (!in_array($request->columna, $columnas)) {
                return back()->with('error', "Columna '{$request->columna}' no existe");
            }
            
            $menu->update([
                $request->columna => $request->valor
            ]);
            
            Log::info('MENU.permiso_actualizado', [
                'menu_id' => $id,
                'columna' => $request->columna,
                'valor' => $request->valor
            ]);
            
            return back()->with('success', "Permiso '{$request->columna}' actualizado");
        } 
        
        // Si viene Node_Order, es actualización de orden
        if ($request->has('Node_Order')) {
            $request->validate([
                'Node_Order' => 'required|integer|min:0'
            ]);
            
            $menu->update([
                'Node_Order' => $request->Node_Order
            ]);
            
            return back()->with('success', "Orden actualizado a {$request->Node_Order}");
        }
        
        // Si no, es actualización de texto/enlace
        $request->validate([
            'Description' => 'required|string|max:255',
            'Link' => 'nullable|string|max:255'
        ]);
        
        $menu->update([
            'Description' => $request->Description,
            'Link' => $request->Link ?? ''
        ]);
        
        return back()->with('success', "Menú '{$menu->Description}' actualizado");
    }

    /**
     * Crear un nuevo menú
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'Description' => 'required|string|max:255',
                'Link' => 'nullable|string|max:255',
                'Parent' => 'nullable|integer',
            ]);

            $parentId = $request->Parent ?? 0;
            
            $siguienteOrden = $this->getNextNodeOrder($parentId);

            $columnasPermisos = MenuAdministrador::getPermisoColumns();
            
            $datos = [
                'Description' => $request->Description,
                'Link' => $request->Link ?? '',
                'Parent' => $parentId,
                'Node_Order' => $siguienteOrden,
            ];
            
            foreach ($columnasPermisos as $col) {
                $datos[$col] = $request->input($col, 0);
            }
            
            $menu = MenuAdministrador::create($datos);
            
            Log::info('MENU.creado', [
                'menu_id' => $menu->Id,
                'description' => $menu->Description,
                'parent' => $parentId
            ]);
            
            return back()->with('success', "Menú '{$menu->Description}' creado correctamente");
            
        } catch (\Exception $e) {
            Log::error('MENU.error_crear', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            return back()->with('error', 'Error al crear: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar un menú
     */
    public function destroy($id)
    {
        $menu = MenuAdministrador::findOrFail($id);
        
        $tieneHijos = MenuAdministrador::where('Parent', $id)->exists();
        if ($tieneHijos) {
            return back()->with('error', 'No se puede eliminar porque tiene submenús');
        }
        
        $nombre = $menu->Description;
        $menu->delete();
        
        Log::info('MENU.eliminado', [
            'menu_id' => $id,
            'description' => $nombre
        ]);
        
        return back()->with('success', "Menú '{$nombre}' eliminado correctamente");
    }
}