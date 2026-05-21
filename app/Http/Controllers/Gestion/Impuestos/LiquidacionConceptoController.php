<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\VentaLiquidacionConcepto;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LiquidacionConceptoController extends Controller
{
    /**
     * Listado de conceptos de liquidación para el cliente (global)
     */
    public function index()
    {
        $conceptos = VentaLiquidacionConcepto::porContexto()
            ->with('cuentaContable')
            ->get();

        $cuentasContables = ContaCuenta::porContexto()
            ->orderBy('Cuenta')
            ->get(['IdCuenta as id', 'Cuenta as nombre', 'Descripcion as descripcion']);

        return Inertia::render('Gestion/Impuestos/LiquidacionConcepto/Index', [
            'conceptos' => $conceptos,
            'cuentasContables' => $cuentasContables,
        ]);
    }

    /**
     * Guardar nuevo concepto (sin sucursal)
     */
    public function store(Request $request)
    {
        $request->validate([
            'Concepto' => 'required|string|max:50',
            'IdCuenta' => 'required|integer|exists:conta_cuenta,IdCuenta',
            'activo' => 'boolean',
        ]);

        VentaLiquidacionConcepto::create([
            'Concepto' => $request->Concepto,
            'IdCuenta' => $request->IdCuenta,
            'IdCliente' => session('cliente_id'),
            'activo' => $request->activo ? 1 : 0,
        ]);

        return redirect()->back()->with('success', 'Concepto agregado correctamente');
    }

    /**
     * Actualizar concepto (sin sucursal)
     */
    public function update(Request $request, $id)
    {
        $concepto = VentaLiquidacionConcepto::porContexto()->findOrFail($id);

        $request->validate([
            'Concepto' => 'required|string|max:50',
            'IdCuenta' => 'required|integer|exists:conta_cuenta,IdCuenta',
            'activo' => 'boolean',
        ]);

        $concepto->update([
            'Concepto' => $request->Concepto,
            'IdCuenta' => $request->IdCuenta,
            'activo' => $request->activo ? 1 : 0,
        ]);

        return redirect()->back()->with('success', 'Concepto actualizado correctamente');
    }

    /**
     * Eliminar concepto
     */
    public function destroy($id)
    {
        try {
            $concepto = VentaLiquidacionConcepto::porContexto()->findOrFail($id);
            $concepto->delete();
            
            // ✅ Devolver JSON, no redirección
            return response()->json([
                'success' => true,
                'message' => 'Concepto eliminado correctamente'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Obtener conceptos para el cliente (sin facturación)
     * Ahora devuelve los conceptos globales del cliente
     */
    public function getConceptosPorCliente()
    {
        $conceptos = VentaLiquidacionConcepto::porContexto()
            ->activos()
            ->get(['IdConceptoLiquidacion as id', 'Concepto as nombre', 'IdCuenta']);

        return response()->json([
            'success' => true,
            'conceptos' => $conceptos
        ]);
    }
}