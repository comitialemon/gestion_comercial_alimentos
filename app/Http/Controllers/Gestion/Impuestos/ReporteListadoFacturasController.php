<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PuntoVenta\PagoVentaController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReporteListadoFacturasController extends Controller
{
    /**
     * Listado de facturas del vendedor
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->leftJoin('todos_identificador as i', 'v.IdNIT', '=', 'i.IdIdentificador')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.ActivoInactivo', 1)
            ->where('v.IdOperadorIngresa', $operadorId);

        // Filtro por fecha
        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $query->whereDate('v.FechaVenta', '>=', $request->fecha_desde)
                  ->whereDate('v.FechaVenta', '<=', $request->fecha_hasta);
        } elseif ($request->filled('fecha')) {
            $query->whereDate('v.FechaVenta', $request->fecha);
        }

        // Filtro por estado
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('v.IdEstado', $request->estado);
        }

        // Filtro por número de factura
        if ($request->filled('numero_factura')) {
            $query->where('v.NumeroFactura', $request->numero_factura);
        }

        $facturas = $query->select(
                'v.IdVentas',
                'v.FechaVenta',
                'v.NumeroFactura',
                'v.NumeroAutorizacion',
                'v.IdEstado',
                'v.IdNIT',
                DB::raw("CASE WHEN v.IdEstado = 2 THEN 0 ELSE v.ImporteVenta END as ImporteVenta"),
                'v.CodigoControl',
                'v.IdOperadorIngresa',
                'v.LiquidadoVendedor',
                'v.Observacion',
                'i.CI_NIT as NITCliente',
                'i.Nombre as NombreCliente'
            )
            ->orderBy('v.FechaVenta', 'desc')
            ->orderBy('v.NumeroFactura', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Obtener operadores para filtro
        $operadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('todos_operador as op', 'v.IdOperadorIngresa', '=', 'op.IdOperador')
            ->join('todos_identificador as i', 'op.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.ActivoInactivo', 1)
            ->select('v.IdOperadorIngresa as id', 'i.Nombre as nombre')
            ->distinct()
            ->get();

        return Inertia::render('Gestion/Impuestos/ReporteListadoFacturas/Index', [
            'facturas' => $facturas,
            'operadores' => $operadores,
            'filtros' => [
                'fecha' => $request->fecha,
                'fecha_desde' => $request->fecha_desde,
                'fecha_hasta' => $request->fecha_hasta,
                'estado' => $request->estado,
                'numero_factura' => $request->numero_factura,
            ],
            'tieneFiltros' => $request->filled('fecha') || $request->filled('fecha_desde') || $request->filled('fecha_hasta') || $request->filled('estado') || $request->filled('numero_factura'),
        ]);
    }

    /**
     * Reimprimir factura
     */
    public function reimprimir($id)
    {
        // Crear una instancia del controlador de pago y llamar al método facturaPdf
        $pagoController = app()->make(PagoVentaController::class);
        return $pagoController->facturaPdf($id);
    }
}