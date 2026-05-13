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
     * Listado de conceptos de liquidación para la sucursal
     */
    public function index()
    {
        $conceptos = VentaLiquidacionConcepto::porContexto()
            ->with('cuentaContable')
            ->get();  // ❌ Eliminado ->orderBy('orden')

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
        ]);

        VentaLiquidacionConcepto::create([
            'Concepto' => $request->Concepto,
            'IdCuenta' => $request->IdCuenta,
            'IdCliente' => session('cliente_id'),
            'IdSucursal' => session('cliente_sucursal_id'),
            'activo' => $request->activo ? 1 : 0,
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
        $concepto = VentaLiquidacionConcepto::porContexto()->findOrFail($id);
        $concepto->delete();

        return redirect()->back()->with('success', 'Concepto eliminado correctamente');
    }

    /**
     * API: Obtener conceptos para la sucursal (sin facturación)
     */
    public function getConceptosPorSucursal()
    {
        $conceptos = VentaLiquidacionConcepto::porContexto()
            ->activos()
            ->get(['IdConceptoLiquidacion as id', 'Concepto as nombre']);

        return response()->json([
            'success' => true,
            'conceptos' => $conceptos
        ]);
    }
}