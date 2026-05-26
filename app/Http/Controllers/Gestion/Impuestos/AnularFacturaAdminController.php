<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class AnularFacturaAdminController extends Controller
{
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // 🔥 Obtener TODAS las sucursales del cliente (sin filtro inicial)
        $todasSucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        // 🔥 FILTRAR SUCURSALES por búsqueda
        $sucursales = $todasSucursales;
        $buscarSucursal = $request->buscar_sucursal;
        
        if ($buscarSucursal) {
            $sucursales = $todasSucursales->filter(function($s) use ($buscarSucursal) {
                return stripos($s->nombre, $buscarSucursal) !== false;
            });
        }

        // 🔥 Obtener operadores filtrados por sucursal seleccionada y búsqueda
        $operadores = collect();
        $buscarOperador = $request->buscar_operador;
        $sucursalId = $request->sucursal_id;
        
        if ($sucursalId) {
            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->join('todos_operador_sucursaldb as os', 'o.IdOperador', '=', 'os.IdOperador')
                ->where('os.IdCliente', $clienteId)
                ->where('os.IdSucursal', $sucursalId)
                ->where('o.ActivoInactivo', 0)
                ->select('o.IdOperador as id', 'i.Nombre as nombre', 'i.CI_NIT as ci')
                ->distinct();
            
            if ($buscarOperador) {
                $query->where(function($q) use ($buscarOperador) {
                    $q->where('i.Nombre', 'LIKE', "%{$buscarOperador}%")
                      ->orWhere('i.CI_NIT', 'LIKE', "%{$buscarOperador}%");
                });
            }
            
            $operadores = $query->orderBy('i.Nombre')->limit(50)->get();
        }

        // Construir query de facturas
        $queryFacturas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('todos_cliente_sucursal as s', 'v.IdClienteSucursal', '=', 's.IdClienteSucursal')
            ->join('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdEstado', 1)
            ->where('v.ActivoInactivo', 1)
            ->where('v.LiquidadoVendedor', 0);

        // Filtro por sucursal
        if ($sucursalId) {
            $queryFacturas->where('v.IdClienteSucursal', $sucursalId);
        }

        // Filtro por operador
        if ($request->filled('operador_id')) {
            $queryFacturas->where('v.IdOperadorIngresa', $request->operador_id);
        }

        // Filtro por fecha (opcional)
        if ($request->filled('fecha')) {
            $queryFacturas->whereDate('v.FechaVenta', $request->fecha);
        }

        $facturas = $queryFacturas->select(
                'v.IdVentas', 
                'v.NumeroFactura', 
                'v.ImporteVenta', 
                'v.FechaVenta',
                'v.IdClienteSucursal',
                's.Nombre as sucursal_nombre',
                'i.Nombre as operador_nombre'
            )
            ->orderBy('v.FechaVenta', 'desc')
            ->orderBy('v.NumeroFactura', 'desc')
            ->get();

        return Inertia::render('Gestion/Impuestos/AnularFactura/AdminIndex', [
            'facturas' => $facturas,
            'sucursales' => $sucursales,
            'todasSucursales' => $todasSucursales,
            'operadores' => $operadores,
            'filtros' => [
                'sucursal_id' => $sucursalId,
                'operador_id' => $request->operador_id,
                'fecha' => $request->fecha,
                'buscar_sucursal' => $buscarSucursal,
                'buscar_operador' => $buscarOperador,
            ]
        ]);
    }

    /**
     * Anular factura
     */
    public function anular(Request $request)
    {
        $request->validate([
            'IdVentas' => 'required|exists:impuestos_ventas,IdVentas',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        $factura = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $request->IdVentas)
            ->where('IdCliente', $clienteId)
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
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $request->IdVentas)
                ->update([
                    'IdEstado' => 2,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaUltimaActualizcion' => now(),
                ]);

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
            return response()->json([
                'success' => false,
                'message' => 'Error al anular la factura: ' . $e->getMessage(),
            ], 500);
        }
    }
}