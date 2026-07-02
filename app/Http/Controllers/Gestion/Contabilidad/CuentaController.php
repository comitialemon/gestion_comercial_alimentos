<?php

namespace App\Http\Controllers\Gestion\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Models\Gestion\Contabilidad\Moneda;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CuentaController extends Controller
{
    /**
     * Vista de solo lectura (listado visual sin opciones de modificar)
     * Esta es la que ya tenías funcionando
     */
    public function index()
    {
        $cuentas = ContaCuenta::porContexto()
            ->with('moneda')
            ->orderBy('Cuenta')
            ->get();

        return Inertia::render('Gestion/Contabilidad/Cuentas/Index', [
            'cuentas' => $cuentas,
            'soloLectura' => true,
        ]);
    }
    
    /**
     * Vista de administración (crear, editar, listar con acciones)
     */
    public function admin(Request $request)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        // Obtener cuentas del cliente
        $cuentas = ContaCuenta::porContexto()
            ->with('moneda')
            ->orderBy('Cuenta')
            ->get();
        
        // Obtener monedas (sin filtro de cliente porque la tabla no tiene IdCliente)
        $monedas = Moneda::orderBy('Abreviacion')
            ->get(['IdMoneda', 'Abreviacion', 'Moneda as Descripcion']);
        
        // Obtener el ID de la cuenta a editar (si viene en la URL)
        $editId = $request->input('edit');
        $cuentaEditar = null;
        
        if ($editId) {
            $cuentaEditar = ContaCuenta::porContexto()
                ->with('moneda')
                ->where('IdCuenta', $editId)
                ->first();
        }
        
        return Inertia::render('Gestion/Contabilidad/Cuentas/Admin', [
            'cuentas' => $cuentas,
            'monedas' => $monedas,
            'cuentaEditar' => $cuentaEditar,
            'editId' => $editId,
        ]);
    }
    
    /**
     * Crear una nueva cuenta
     */
    public function store(Request $request)
    {
        $request->validate([
            'Cuenta' => 'required|string|max:255',
            'Descripcion' => 'required|string|max:500',
            'TipoDeCuenta' => 'required|in:B,P',
            'IdMoneda' => 'required|integer|exists:conta_moneda,IdMoneda',
            'ActivoFijo' => 'boolean',
            'AbiertoCerrado' => 'boolean',
        ]);
        
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        try {
            $cuenta = ContaCuenta::create([
                'Cuenta' => $request->Cuenta,
                'Descripcion' => $request->Descripcion,
                'TipoDeCuenta' => $request->TipoDeCuenta,
                'IdMoneda' => $request->IdMoneda,
                'ActivoFijo' => $request->ActivoFijo ? 1 : 0,
                'AbiertoCerrado' => $request->AbiertoCerrado ? 1 : 0,
                'IdCliente' => $clienteId,
                'IdOperadorIngreso' => $operadorId,
                'IdOperadorEdita' => $operadorId,
                'FechaIngreso' => now(),
                'FechaActualiza' => now(),
            ]);
            
            return redirect()->route('gestion.contabilidad.cuentas.admin')
                ->with('success', 'Cuenta creada exitosamente');
                
        } catch (\Exception $e) {
            Log::error('Error al crear cuenta: ' . $e->getMessage());
            return back()->with('error', 'Error al crear la cuenta: ' . $e->getMessage());
        }
    }
    
    /**
     * Actualizar una cuenta existente
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'Cuenta' => 'required|string|max:255',
            'Descripcion' => 'required|string|max:500',
            'TipoDeCuenta' => 'required|in:B,P',
            'IdMoneda' => 'required|integer|exists:conta_moneda,IdMoneda',
            'ActivoFijo' => 'boolean',
            'AbiertoCerrado' => 'boolean',
        ]);
        
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        try {
            $cuenta = ContaCuenta::porContexto()
                ->where('IdCuenta', $id)
                ->firstOrFail();
            
            $cuenta->update([
                'Cuenta' => $request->Cuenta,
                'Descripcion' => $request->Descripcion,
                'TipoDeCuenta' => $request->TipoDeCuenta,
                'IdMoneda' => $request->IdMoneda,
                'ActivoFijo' => $request->ActivoFijo ? 1 : 0,
                'AbiertoCerrado' => $request->AbiertoCerrado ? 1 : 0,
                'IdOperadorEdita' => $operadorId,
                'FechaActualiza' => now(),
            ]);
            
            return redirect()->route('gestion.contabilidad.cuentas.admin')
                ->with('success', 'Cuenta actualizada exitosamente');
                
        } catch (\Exception $e) {
            Log::error('Error al actualizar cuenta: ' . $e->getMessage());
            return back()->with('error', 'Error al actualizar la cuenta: ' . $e->getMessage());
        }
    }
    
    /**
     * Eliminar una cuenta
     */
    public function destroy($id)
    {
        try {
            $cuenta = ContaCuenta::porContexto()
                ->where('IdCuenta', $id)
                ->firstOrFail();
            
            $cuenta->delete();
            
            return redirect()->route('gestion.contabilidad.cuentas.admin')
                ->with('success', 'Cuenta eliminada exitosamente');
                
        } catch (\Exception $e) {
            Log::error('Error al eliminar cuenta: ' . $e->getMessage());
            return back()->with('error', 'Error al eliminar la cuenta: ' . $e->getMessage());
        }
    }
    
    /**
     * Cambiar estado de cuenta (abrir/cerrar)
     */
    public function toggleEstado($id)
    {
        try {
            $cuenta = ContaCuenta::porContexto()
                ->where('IdCuenta', $id)
                ->firstOrFail();
            
            $nuevoEstado = $cuenta->AbiertoCerrado == 0 ? 1 : 0;
            $cuenta->update([
                'AbiertoCerrado' => $nuevoEstado,
                'IdOperadorEdita' => session('operador_id'),
                'FechaActualiza' => now(),
            ]);
            
            return redirect()->route('gestion.contabilidad.cuentas.admin')
                ->with('success', 'Estado de la cuenta actualizado exitosamente');
                
        } catch (\Exception $e) {
            Log::error('Error al cambiar estado de cuenta: ' . $e->getMessage());
            return back()->with('error', 'Error al cambiar el estado de la cuenta');
        }
    }
}