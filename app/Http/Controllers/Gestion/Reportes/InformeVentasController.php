<?php

namespace App\Http\Controllers\Gestion\Reportes;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PuntoVenta\PagoVentaController;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Impuestos\VentaEstado;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class InformeVentasController extends Controller
{
    /**
     * 📋 GRID DE INFORME DE VENTAS
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // =============================================
        // CONSULTA PRINCIPAL
        // =============================================
        $query = Venta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->where('IdEstado', 1);
        
        // FILTROS
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('IdClienteSucursal', $request->sucursal_id);
        }
        
        if ($request->filled('vendedor_id') && $request->vendedor_id !== '') {
            $query->where('IdOperadorIngresa', $request->vendedor_id);
        }
        
        if ($request->filled('comisionista_id') && $request->comisionista_id !== '') {
            $query->where('IdComisionista', $request->comisionista_id);
        }
        
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('NumeroFactura', 'like', "%{$search}%")
                  ->orWhere('NumeroAutorizacion', 'like', "%{$search}%");
            });
        }
        
        $ventas = $query->orderBy('FechaVenta', 'desc')
            ->paginate(20)
            ->withQueryString();
        
        // =============================================
        // ENRIQUECER DATOS
        // =============================================
        $ventas->getCollection()->transform(function($venta) {
            $estado = VentaEstado::find($venta->IdEstado);
            $venta->estado_nombre = $estado ? $estado->Detalle : 'Desconocido';
            $venta->estado_abrev = $estado ? $estado->Abreviacion : '?';
            
            $cliente = Identificador::find($venta->IdNIT);
            $venta->cliente_nit = $cliente ? $cliente->CI_NIT : '0';
            
            $operador = Operador::with('identificador')->find($venta->IdOperadorIngresa);
            $venta->vendedor_nombre = $operador && $operador->identificador ? $operador->identificador->Nombre : 'Desconocido';
            
            $sucursal = ClienteSucursal::find($venta->IdClienteSucursal);
            $venta->sucursal_nombre = $sucursal ? $sucursal->Nombre : 'Sin sucursal';
            $venta->sucursal_numero = $sucursal ? $sucursal->NumeroSucursal : null;
            
            if ($venta->IdComisionista) {
                $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_comisionitas as c')
                    ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                    ->where('c.IdComisionista', $venta->IdComisionista)
                    ->first();
                $venta->comisionista_nombre = $comisionista ? $comisionista->Nombre : 'Sin comisionista';
            } else {
                $venta->comisionista_nombre = 'Sin comisionista';
            }
            
            return $venta;
        });
        
        // =============================================
        // CATÁLOGOS PARA FILTROS
        // =============================================
        
        // Sucursales
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // Vendedores (ActivoInactivo = 0 = ACTIVO)
        $vendedores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador_sucursaldb as tos')
            ->join('todos_operador as t', 'tos.IdOperador', '=', 't.IdOperador')
            ->join('todos_identificador as ti', 't.IdIdentificador', '=', 'ti.IdIdentificador')
            ->where('tos.IdCliente', $clienteId)
            ->where('t.ActivoInactivo', 0)
            ->select(
                't.IdOperador as id',
                DB::raw("CONCAT(ti.CI_NIT, ' - ', ti.Nombre) as nombre_completo"),
                'ti.IdIdentificador',
                'ti.Nombre'
            )
            ->distinct()
            ->orderBy('ti.Nombre', 'asc')
            ->get();
        
        // Fallback: si no hay en la relación, traer todos los activos
        if ($vendedores->isEmpty()) {
            $vendedores = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as t')
                ->join('todos_identificador as ti', 't.IdIdentificador', '=', 'ti.IdIdentificador')
                ->where('t.ActivoInactivo', 0)
                ->select(
                    't.IdOperador as id',
                    DB::raw("CONCAT(ti.CI_NIT, ' - ', ti.Nombre) as nombre_completo"),
                    'ti.IdIdentificador',
                    'ti.Nombre'
                )
                ->orderBy('ti.Nombre', 'asc')
                ->get();
        }
        
        // Comisionistas
        $comisionistas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_comisionitas as c')
            ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('c.IdCliente', $clienteId)
            ->select(
                'c.IdComisionista as id',
                'i.Nombre as nombre'
            )
            ->orderBy('i.Nombre', 'asc')
            ->get();
        
        // =============================================
        // ESTADÍSTICAS
        // =============================================
        $totalVentasGeneral = Venta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->where('IdEstado', 1)
            ->count();
        
        $totalImporteGeneral = Venta::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->where('IdEstado', 1)
            ->sum('ImporteVenta');
        
        $estadisticasPorSucursal = [];
        $sucursalSeleccionada = $request->sucursal_id;
        
        if ($sucursalSeleccionada) {
            $sucursal = $sucursales->firstWhere('id', $sucursalSeleccionada);
            $totalVentas = Venta::where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 1)
                ->where('IdEstado', 1)
                ->where('IdClienteSucursal', $sucursalSeleccionada)
                ->count();
            
            $totalImporte = Venta::where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 1)
                ->where('IdEstado', 1)
                ->where('IdClienteSucursal', $sucursalSeleccionada)
                ->sum('ImporteVenta');
            
            $estadisticasPorSucursal[] = [
                'sucursal_id' => $sucursalSeleccionada,
                'sucursal_nombre' => $sucursal ? $sucursal->nombre : 'Sucursal seleccionada',
                'total_ventas' => $totalVentas,
                'total_importe' => $totalImporte,
            ];
        } else {
            foreach ($sucursales as $sucursal) {
                $totalVentas = Venta::where('IdCliente', $clienteId)
                    ->where('ActivoInactivo', 1)
                    ->where('IdEstado', 1)
                    ->where('IdClienteSucursal', $sucursal->id)
                    ->count();
                
                $totalImporte = Venta::where('IdCliente', $clienteId)
                    ->where('ActivoInactivo', 1)
                    ->where('IdEstado', 1)
                    ->where('IdClienteSucursal', $sucursal->id)
                    ->sum('ImporteVenta');
                
                $estadisticasPorSucursal[] = [
                    'sucursal_id' => $sucursal->id,
                    'sucursal_nombre' => $sucursal->nombre,
                    'total_ventas' => $totalVentas,
                    'total_importe' => $totalImporte,
                ];
            }
        }
        
        return Inertia::render('Gestion/Reportes/InformeVentas/Index', [
            'ventas' => $ventas,
            'sucursales' => $sucursales,
            'vendedores' => $vendedores,
            'comisionistas' => $comisionistas,
            'estadisticas' => [
                'total_ventas' => $totalVentasGeneral,
                'total_importe' => $totalImporteGeneral,
                'por_sucursal' => $estadisticasPorSucursal,
                'sucursal_seleccionada' => $sucursalSeleccionada,
            ],
            'filtros' => [
                'sucursal_id' => $request->sucursal_id,
                'vendedor_id' => $request->vendedor_id,
                'comisionista_id' => $request->comisionista_id,
                'search' => $request->search,
            ],
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