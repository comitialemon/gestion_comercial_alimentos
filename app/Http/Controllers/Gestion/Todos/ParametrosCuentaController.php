<?php

namespace App\Http\Controllers\Gestion\Todos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Todos\ParametrosCuentas;  // ✅ CON "s" al final
use App\Models\Gestion\Contabilidad\ContaCuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ParametrosCuentaController extends Controller
{
    /**
     * Mostrar formulario de parámetros de cuentas
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');

        // 🔥 Obtener los parámetros del cliente usando scope
        $parametros = ParametrosCuentas::porCliente($clienteId)->first();

        // 🔥 Si no existen, crear un registro vacío para este cliente
        if (!$parametros) {
            $parametros = ParametrosCuentas::create([
                'IdCliente' => $clienteId,
            ]);
        }

        // 🔥 Obtener todas las cuentas para los selects
        $cuentas = ContaCuenta::where('IdCliente', $clienteId)
            ->orderBy('Cuenta')
            ->get(['IdCuenta', 'Cuenta', 'Descripcion']);

        return Inertia::render('Gestion/Todos/ParametrosCuenta/Index', [
            'parametros' => $parametros,
            'cuentas' => $cuentas,
            'secciones' => $this->getSecciones(),
        ]);
    }

    /**
     * Guardar o actualizar parámetros
     */
    public function store(Request $request)
    {
        $clienteId = session('cliente_id');

        $validated = $request->validate([
            'VentasFacturadas' => 'nullable|exists:conta_cuenta,IdCuenta',
            'VentasNoFacturadas' => 'nullable|exists:conta_cuenta,IdCuenta',
            'DebitoFiscalIVA' => 'nullable|exists:conta_cuenta,IdCuenta',
            'ITPagados' => 'nullable|exists:conta_cuenta,IdCuenta',
            'ITxPagar' => 'nullable|exists:conta_cuenta,IdCuenta',
            'ControlDFIVA' => 'nullable|exists:conta_cuenta,IdCuenta',
            'ComprasFacturadas' => 'nullable|exists:conta_cuenta,IdCuenta',
            'ComprasNoFacturadas' => 'nullable|exists:conta_cuenta,IdCuenta',
            'CreditoFiscalIVA' => 'nullable|exists:conta_cuenta,IdCuenta',
            'CuentaPersonalVendedor' => 'nullable|exists:conta_cuenta,IdCuenta',
            'Inventario' => 'nullable|exists:conta_cuenta,IdCuenta',
            'Proveedores' => 'nullable|exists:conta_cuenta,IdCuenta',
            'DiferenciaInventarioSobrante' => 'nullable|exists:conta_cuenta,IdCuenta',
            'DiferenciaInventarioFaltante' => 'nullable|exists:conta_cuenta,IdCuenta',
            'AnticipoProveedores' => 'nullable|exists:conta_cuenta,IdCuenta',
            'DiferenciaDeCambioIngreso' => 'nullable|exists:conta_cuenta,IdCuenta',
            'DiferenciaDeCambioGasto' => 'nullable|exists:conta_cuenta,IdCuenta',
            'AporteLaboralPasivo' => 'nullable|exists:conta_cuenta,IdCuenta',
            'AportePatronalPasivo' => 'nullable|exists:conta_cuenta,IdCuenta',
            'CajaSaludPasivo' => 'nullable|exists:conta_cuenta,IdCuenta',
            'AguinaldoPorPagarPasivo' => 'nullable|exists:conta_cuenta,IdCuenta',
            'IndemnizacionPasivo' => 'nullable|exists:conta_cuenta,IdCuenta',
            'SueldosGasto' => 'nullable|exists:conta_cuenta,IdCuenta',
            'AportesPatronalesGasto' => 'nullable|exists:conta_cuenta,IdCuenta',
            'CajaSaludGasto' => 'nullable|exists:conta_cuenta,IdCuenta',
            'AguinaldoGasto' => 'nullable|exists:conta_cuenta,IdCuenta',
            'IndemnizacionGasto' => 'nullable|exists:conta_cuenta,IdCuenta',
            'DescuentosDiciplinarios' => 'nullable|exists:conta_cuenta,IdCuenta',
            'CajaBolivianos' => 'nullable|exists:conta_cuenta,IdCuenta',
            'CajaChica' => 'nullable|exists:conta_cuenta,IdCuenta',
        ]);

        // 🔥 Buscar o crear usando el modelo correcto
        $parametros = ParametrosCuentas::firstOrNew(['IdCliente' => $clienteId]);
        $parametros->fill($validated);
        $parametros->save();

        return redirect()->back()->with('success', '✅ Parámetros guardados correctamente.');
    }

    /**
     * Obtener las secciones para los tabs
     */
    private function getSecciones()
    {
        return [
            'ventas' => [
                'titulo' => 'VENTAS',
                'icono' => 'fas fa-chart-line',
                'campos' => [
                    'VentasFacturadas' => 'Ventas Facturadas',
                    'VentasNoFacturadas' => 'Ventas No Facturadas',
                    'DebitoFiscalIVA' => 'Débito Fiscal IVA',
                    'ITPagados' => 'IT Pagados',
                    'ITxPagar' => 'IT x Pagar',
                    'ControlDFIVA' => 'Control DF IVA',
                ]
            ],
            'compras' => [
                'titulo' => 'COMPRAS',
                'icono' => 'fas fa-shopping-cart',
                'campos' => [
                    'ComprasFacturadas' => 'Compras Facturadas',
                    'ComprasNoFacturadas' => 'Compras No Facturadas',
                    'CreditoFiscalIVA' => 'Crédito Fiscal IVA',
                    'Inventario' => 'Inventario',
                    'Proveedores' => 'Proveedores',
                ]
            ],
            'personal' => [
                'titulo' => 'PERSONAL',
                'icono' => 'fas fa-users',
                'campos' => [
                    'CuentaPersonalVendedor' => 'Cuenta Personal Vendedor',
                    'AporteLaboralPasivo' => 'Aporte Laboral Pasivo',
                    'AportePatronalPasivo' => 'Aporte Patronal Pasivo',
                    'CajaSaludPasivo' => 'Caja Salud Pasivo',
                    'AguinaldoPorPagarPasivo' => 'Aguinaldo por Pagar Pasivo',
                    'IndemnizacionPasivo' => 'Indemnización Pasivo',
                    'SueldosGasto' => 'Sueldos Gasto',
                    'AportesPatronalesGasto' => 'Aportes Patronales Gasto',
                    'CajaSaludGasto' => 'Caja Salud Gasto',
                    'AguinaldoGasto' => 'Aguinaldo Gasto',
                    'IndemnizacionGasto' => 'Indemnización Gasto',
                    'DescuentosDiciplinarios' => 'Descuentos Disciplinarios',
                ]
            ],
            'ajustes' => [
                'titulo' => 'AJUSTES',
                'icono' => 'fas fa-adjust',
                'campos' => [
                    'DiferenciaInventarioSobrante' => 'Diferencia Inventario Sobrante',
                    'DiferenciaInventarioFaltante' => 'Diferencia Inventario Faltante',
                    'AnticipoProveedores' => 'Anticipo Proveedores',
                    'DiferenciaDeCambioIngreso' => 'Diferencia de Cambio Ingreso',
                    'DiferenciaDeCambioGasto' => 'Diferencia de Cambio Gasto',
                ]
            ],
            'efectivo' => [
                'titulo' => 'EFECTIVO',
                'icono' => 'fas fa-money-bill-wave',
                'campos' => [
                    'CajaBolivianos' => 'Caja Bolivianos',
                    'CajaChica' => 'Caja Chica',
                ]
            ],
        ];
    }

    /**
     * 🔥 API: Obtener cuentas para selects (usado por Vue)
     */
    public function getCuentas(Request $request)
    {
        $clienteId = session('cliente_id');
        $search = $request->get('search');

        $query = ContaCuenta::where('IdCliente', $clienteId)
            ->orderBy('Cuenta');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('Cuenta', 'LIKE', "%{$search}%")
                  ->orWhere('Descripcion', 'LIKE', "%{$search}%");
            });
        }

        return response()->json($query->limit(20)->get(['IdCuenta', 'Cuenta', 'Descripcion']));
    }
}