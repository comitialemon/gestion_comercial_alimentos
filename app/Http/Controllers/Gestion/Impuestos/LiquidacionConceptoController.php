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
     * Listado de conceptos de liquidación para el cliente
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
     * Guardar nuevo concepto
     */
    public function store(Request $request)
    {
        $request->validate([
            'Concepto' => 'required|string|max:50',
            'IdCuenta' => 'required|integer|exists:conta_cuenta,IdCuenta',
            'activo' => 'boolean',
            'requiere_identificador' => 'boolean',
            'usa_identificador_factura' => 'boolean',
        ]);

        // Validar que no tenga ambos campos activos simultáneamente
        if ($request->requiere_identificador && $request->usa_identificador_factura) {
            return redirect()->back()->withErrors([
                'requiere_identificador' => 'No puede seleccionar ambos tipos de identificador simultáneamente'
            ])->withInput();
        }

        VentaLiquidacionConcepto::create([
            'Concepto' => $request->Concepto,
            'IdCuenta' => $request->IdCuenta,
            'IdCliente' => session('cliente_id'),
            'activo' => $request->activo ? 1 : 0,
            'requiere_identificador' => $request->requiere_identificador ? 1 : 0,
            'usa_identificador_factura' => $request->usa_identificador_factura ? 1 : 0,
        ]);

        return redirect()->back()->with('success', 'Concepto agregado correctamente');
    }

    /**
     * Actualizar concepto
     */
    public function update(Request $request, $id)
    {
        $concepto = VentaLiquidacionConcepto::porContexto()->findOrFail($id);

        $request->validate([
            'Concepto' => 'required|string|max:50',
            'IdCuenta' => 'required|integer|exists:conta_cuenta,IdCuenta',
            'activo' => 'boolean',
            'requiere_identificador' => 'boolean',
            'usa_identificador_factura' => 'boolean',
        ]);

        // Validar que no tenga ambos campos activos simultáneamente
        if ($request->requiere_identificador && $request->usa_identificador_factura) {
            return redirect()->back()->withErrors([
                'requiere_identificador' => 'No puede seleccionar ambos tipos de identificador simultáneamente'
            ])->withInput();
        }

        $concepto->update([
            'Concepto' => $request->Concepto,
            'IdCuenta' => $request->IdCuenta,
            'activo' => $request->activo ? 1 : 0,
            'requiere_identificador' => $request->requiere_identificador ? 1 : 0,
            'usa_identificador_factura' => $request->usa_identificador_factura ? 1 : 0,
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
     * API: Obtener conceptos para el cliente
     */
    public function getConceptosPorCliente()
    {
        $conceptos = VentaLiquidacionConcepto::porContexto()
            ->activos()
            ->get([
                'IdConceptoLiquidacion as id', 
                'Concepto as nombre', 
                'IdCuenta',
                'requiere_identificador',
                'usa_identificador_factura'
            ]);

        return response()->json([
            'success' => true,
            'conceptos' => $conceptos
        ]);
    }
}