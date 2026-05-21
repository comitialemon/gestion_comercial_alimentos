<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
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
     * Reimprimir comprobante (Factura/Recibo)
     */
    public function reimprimir($id)
    {
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->leftJoin('todos_identificador as i', 'v.IdNIT', '=', 'i.IdIdentificador')
            ->leftJoin('impuestos_ventas_comisionitas as c', 'v.IdComisionista', '=', 'c.IdComisionista')
            ->leftJoin('todos_identificador as ic', 'c.IdIdentificador', '=', 'ic.IdIdentificador')
            ->where('v.IdVentas', $id)
            ->select(
                'v.*',
                'i.CI_NIT as NITCliente',
                'i.Nombre as NombreCliente',
                'ic.Nombre as NombreComisionista'
            )
            ->first();

        if (!$venta) {
            return redirect()->back()->with('error', 'Factura no encontrada');
        }

        // Obtener detalles de la venta
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle as vd')
            ->join('inventario_relacion_ventainventario as rvi', 'vd.idrelacionventainventario', '=', 'rvi.IdDetalleProducto')
            ->where('vd.idventas', $id)
            ->select(
                'rvi.NombreCortoFactura as producto',
                'vd.unidades',
                'vd.preciounidades',
                'vd.totalbolivianos'
            )
            ->get();

        // Obtener métodos de pago
        $metodosPago = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_liquidacion as vl')
            ->join('conta_cuenta as cc', 'vl.IdCuenta', '=', 'cc.IdCuenta')
            ->where('vl.IdVentas', $id)
            ->select('cc.Descripcion', 'vl.Bolivianos')
            ->get();

        // Obtener datos de la empresa
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $venta->IdCliente)
            ->first();

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $venta->IdClienteSucursal)
            ->first();

        // Obtener información de la dosificación
        $dosificacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_dosificacion')
            ->where('IdCliente', $venta->IdCliente)
            ->where('IdSucursal', $venta->IdClienteSucursal)
            ->where('Autorizacion', $venta->NumeroAutorizacion)
            ->first();

        // Obtener operador que realizó la venta
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as op')
            ->join('todos_identificador as i', 'op.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('op.IdOperador', $venta->IdOperadorIngresa)
            ->first();

        $tipoDocumento = 'FACTURA';
        $autorizacion = $venta->NumeroAutorizacion;
        
        if ($dosificacion && $dosificacion->IdTipoDeDosificacion == 3) {
            $tipoDocumento = 'RECIBO';
            $autorizacion = 'SIN AUTORIZACION';
        }

        return view('pdfs.factura_ticket', [
            'venta' => $venta,
            'detalles' => $detalles,
            'metodosPago' => $metodosPago,
            'empresa' => $empresa,
            'sucursal' => $sucursal,
            'dosificacion' => $dosificacion,
            'operador' => $operador,
            'tipoDocumento' => $tipoDocumento,
            'autorizacion' => $autorizacion,
        ]);
    }
}