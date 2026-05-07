<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\LugarVenta;
use App\Models\Gestion\Impuestos\Comisionista;
use App\Models\Gestion\Inventario\VentaGrupo;
use App\Models\Gestion\Inventario\ProductoVenta;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Impuestos\VentaDetalle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FormularioVentaController extends Controller
{
    public function create()
    {
        $lugarId = session('venta_lugar_id');
        $comisionistaId = session('venta_comisionista_id');
        
        $lugarVenta = LugarVenta::find($lugarId, ['IdLugar as id', 'Lugar as nombre']);
        $comisionista = Comisionista::with('identificador')->find($comisionistaId);
        
        // 🔥 RECUPERAR PRODUCTOS GUARDADOS DE LA BASE DE DATOS
        $ventaId = session('venta_actual_id');
        $productosGuardados = [];
        
        if ($ventaId) {
            $venta = Venta::find($ventaId);
            if ($venta) {
                $detalles = VentaDetalle::where('idventas', $ventaId)->get();
                foreach ($detalles as $detalle) {
                    $productoInfo = ProductoVenta::find($detalle->idrelacionventainventario);
                    $productosGuardados[] = [
                        'id' => $detalle->idventasdetalle,
                        'idVentaGrupo' => $detalle->IdVentaGrupo,
                        'idRelacionVentaInventario' => $detalle->idrelacionventainventario,
                        'descripcion' => $productoInfo ? $productoInfo->Detalle : 'Producto',
                        'unidades' => (float)$detalle->unidades,
                        'precioUnitario' => (float)$detalle->preciounidades,
                        'total' => (float)$detalle->totalbolivianos,
                        'editando' => false,
                        'confirmado' => true
                    ];
                }
            }
        }
        
        return Inertia::render('PuntoVenta/FormularioVenta', [
            'lugarVenta' => $lugarVenta ? ['id' => $lugarVenta->id, 'nombre' => $lugarVenta->nombre] : null,
            'comisionista' => $comisionista ? [
                'id' => $comisionista->IdComisionista,
                'nombre' => $comisionista->identificador->Nombre ?? 'Sin nombre'
            ] : null,
            'sucursalNombre' => session('cliente_sucursal_nombre'),
            'productosGuardados' => $productosGuardados,  // 🔥 PRODUCTOS RECUPERADOS
            'tieneVentaActiva' => $ventaId && count($productosGuardados) > 0
        ]);
    }
    
    public function getGrupos()
    {
        $grupos = VentaGrupo::where('IdCliente', session('cliente_id'))
            ->orderBy('Orden')
            ->get(['IdVentaGrupo as id', 'Detalle as detalle']);
        return response()->json($grupos);
    }
    
    public function getProductos($idVentaGrupo)
    {
        $productos = ProductoVenta::where('IdCliente', session('cliente_id'))
            ->where('IdVentaGrupo', $idVentaGrupo)
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto as id', 'Detalle as detalle', 'PrecioVenta as precioVenta']);
        return response()->json($productos);
    }
    
    public function getPrecioProducto($idProducto)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        $identificadorId = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->where('IdOperador', $operadorId)
            ->value('IdIdentificador');
        
        $precioMayorista = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario_preciomayorista')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdIdentificador', $identificadorId)
            ->where('IdProducto', $idProducto)
            ->value('Precio');
        
        if ($precioMayorista) {
            return response()->json(['precio' => $precioMayorista, 'tipo' => 'mayorista']);
        }
        
        $precioSucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario_preciosucursal')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdProducto', $idProducto)
            ->where('PrecioDiferenciadoA', 'Sucursal')
            ->value('Precio');
        
        if ($precioSucursal) {
            return response()->json(['precio' => $precioSucursal, 'tipo' => 'sucursal']);
        }
        
        $precioVenta = ProductoVenta::where('IdDetalleProducto', $idProducto)->value('PrecioVenta');
        return response()->json(['precio' => $precioVenta, 'tipo' => 'default']);
    }
    
    public function store(Request $request)
    {
        try {
            $request->validate([
                'productos' => 'required|array|min:1',
                'productos.*.unidades' => 'required|numeric|gt:0',
                'productos.*.totalBolivianos' => 'required|numeric',
            ]);
            
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            $nitPorDefecto = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('CI_NIT', 0)
                ->value('IdIdentificador');
            
            if (!$nitPorDefecto) {
                $nitPorDefecto = 1;
            }
            
            $ventaId = session('venta_actual_id');
            
            if ($ventaId) {
                // ELIMINAR detalles antiguos
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->where('idventas', $ventaId)
                    ->delete();
                
                // ACTUALIZAR venta
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas')
                    ->where('IdVentas', $ventaId)
                    ->update([
                        'ImporteVenta' => collect($request->productos)->sum('totalBolivianos'),
                        'FechaVenta' => now(),
                    ]);
            } else {
                // CREAR nueva venta
                $ventaId = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas')
                    ->insertGetId([
                        'IdCliente' => session('cliente_id'),
                        'IdClienteSucursal' => session('cliente_sucursal_id'),
                        'IdOperadorIngresa' => session('operador_id'),
                        'FechaVenta' => now(),
                        'NumeroFactura' => 0,
                        'NumeroAutorizacion' => '0',
                        'IdEstado' => 0,
                        'IdNIT' => $nitPorDefecto,
                        'ImporteVenta' => collect($request->productos)->sum('totalBolivianos'),
                        'ImporteExcento' => 0,
                        'ImporteExportaciones' => 0,
                        'ImporteTasaCero' => 0,
                        'ImporteDescuentos' => 0,
                        'CodigoControl' => '0',
                        'ActivoInactivo' => 0,
                        'IdOperadorActualiza' => session('operador_id'),
                        'Entrega' => 0,
                        'TicketDia' => 0,
                        'LiquidadoVendedor' => 0,
                        'LugarVenta' => $request->lugar_venta_id ?? '',
                        'IdComisionista' => $request->comisionista_id ?? 0,
                        'Observacion' => '',
                    ]);
            }
            
            // CREAR nuevos detalles
            foreach ($request->productos as $producto) {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->insert([
                        'idventas' => $ventaId,
                        'IdVentaGrupo' => $producto['idVentaGrupo'] ?? 1,
                        'idrelacionventainventario' => $producto['idRelacionVentaInventario'] ?? 1,
                        'unidades' => $producto['unidades'],
                        'preciounidades' => $producto['precioUnitario'] ?? 0,
                        'totalbolivianos' => $producto['totalBolivianos'],
                        'PorcentajeDescuento' => 0,
                        'Descuento' => 0,
                        'TotalBolivianosFacturado' => 0,
                        'entregado' => 0,
                    ]);
            }
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            session(['venta_actual_id' => $ventaId]);
            
            return response()->json([
                'success' => true,
                'venta_id' => $ventaId,
                'message' => 'Venta guardada correctamente'
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    // 🔥 ELIMINAR venta actual (cuando se completa el pago o se cancela)
    public function limpiarVenta()
    {
        $ventaId = session('venta_actual_id');
        if ($ventaId) {
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->delete();
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->delete();
            session()->forget('venta_actual_id');
        }
        return redirect()->route('ventas.formulario');
    }
}