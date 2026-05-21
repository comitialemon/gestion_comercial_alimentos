<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnularFacturaController extends Controller
{
    /**
     * Muestra el selector de facturas no liquidadas para anular
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('IdOperadorIngresa', $operadorId)
            ->where('IdEstado', 1)           // Activa (no anulada)
            ->where('ActivoInactivo', 1)     // Activa
            ->where('LiquidadoVendedor', 0); // No liquidada

        // Aplicar filtro de fecha si se seleccionó
        if ($request->filled('fecha')) {
            $query->whereDate('FechaVenta', $request->fecha);
        }

        $facturas = $query->orderBy('FechaVenta', 'desc')
            ->orderBy('NumeroFactura', 'desc')
            ->get(['IdVentas', 'NumeroFactura', 'ImporteVenta', 'FechaVenta']);

        return Inertia::render('Gestion/Impuestos/AnularFactura/Index', [
            'facturas' => $facturas,
            'filtroFecha' => $request->fecha,
        ]);
    }

    /**
     * Anula una factura
     */
    public function anular(Request $request)
    {
        $request->validate([
            'IdVentas' => 'required|exists:impuestos_ventas,IdVentas',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // Verificar que la factura existe y cumple las condiciones
        $factura = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $request->IdVentas)
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('IdOperadorIngresa', $operadorId)
            ->where('IdEstado', 1)
            ->where('ActivoInactivo', 1)
            ->where('LiquidadoVendedor', 0)
            ->first();

        if (!$factura) {
            return response()->json([
                'success' => false,
                'message' => 'La factura no existe o ya fue liquidada/anulada.'
            ], 422);
        }

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // 1. Cambiar estado de la factura a anulada (IdEstado = 2)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $request->IdVentas)
                ->update([
                    'IdEstado' => 2,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaUltimaActualizcion' => now(),
                ]);

            // 2. Eliminar movimientos de inventario (TipoOperacion = 2 = Venta)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->where('IdTipoDeOperacion', 2)
                ->where('IdDocumento', $request->IdVentas)
                ->delete();

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => "La factura N° {$factura->NumeroFactura} fue anulada correctamente.",
                'numero_factura' => $factura->NumeroFactura,
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al anular factura: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al anular la factura: ' . $e->getMessage(),
            ], 500);
        }
    }
}