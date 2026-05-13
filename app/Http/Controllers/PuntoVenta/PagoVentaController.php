<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Services\Impuestos\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PagoVentaController extends Controller
{
    protected $ventaService;

    public function __construct(VentaService $ventaService)
    {
        $this->ventaService = $ventaService;
    }

    /**
     * Mostrar formulario de pago para venta NORMAL
     */
    public function create()
    {
        $ventaId = session('venta_actual_id');
        $tieneFacturacion = session('tiene_facturacion', false);
        
        if (!$ventaId) {
            return redirect()->route('ventas.formulario')->with('error', 'No hay una venta activa');
        }
        
        $venta = Venta::with('detalles')->findOrFail($ventaId);
        $deuda = (float) $this->ventaService->getDeuda($ventaId);
        
        $productos = [];
        foreach ($venta->detalles as $detalle) {
            $productos[] = [
                'descripcionLibre' => 'Producto',
                'unidades' => (float) $detalle->unidades,
                'precioUnitario' => (float) $detalle->preciounidades,
                'total' => (float) $detalle->totalbolivianos,
            ];
        }
        
        return $this->renderPagoView($tieneFacturacion, [
            'venta' => $venta,
            'deuda' => $deuda,
            'productos' => $productos,
            'ventaId' => $ventaId,
            'tipoVenta' => 'normal',
            'volverRuta' => '/venta-factura/nueva'
        ]);
    }

    /**
     * Mostrar formulario de pago para venta TÁCTIL
     */
    public function createTactil()
    {
        $ventaId = session('venta_tactil_id');
        $tieneFacturacion = session('tiene_facturacion', false);
        
        if (!$ventaId) {
            return redirect()->route('venta-tactil.nueva')->with('error', 'No hay una venta activa');
        }
        
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $ventaId)
            ->first();
        
        if (!$venta || $venta->ActivoInactivo == 1) {
            return redirect()->route('venta-tactil.nueva')->with('error', 'La venta ya fue finalizada');
        }
        
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle as d')
            ->join('inventario_relacion_ventainventario as p', 'd.idrelacionventainventario', '=', 'p.IdDetalleProducto')
            ->where('d.idventas', $ventaId)
            ->select(
                DB::raw("'Producto' as descripcionLibre"),
                'd.unidades',
                'd.preciounidades as precioUnitario',
                'd.totalbolivianos as total'
            )
            ->get();
        
        $deuda = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle')
            ->where('idventas', $ventaId)
            ->sum('totalbolivianos');
        
        return $this->renderPagoView($tieneFacturacion, [
            'venta' => (object) $venta,
            'deuda' => (float) $deuda,
            'productos' => $productos,
            'ventaId' => $ventaId,
            'tipoVenta' => 'tactil',
            'volverRuta' => '/venta-tactil/carrito'
        ]);
    }

    /**
     * Renderizar la vista de pago correcta según facturación
     */
    private function renderPagoView($tieneFacturacion, $data)
    {
        if ($tieneFacturacion) {
            return Inertia::render('PuntoVenta/PagoVentaFacturacion', $data);
        } else {
            return Inertia::render('PuntoVenta/PagoVentaSinFacturacion', $data);
        }
    }

    /**
     * Procesar pago SIN facturación (completo con inventario)
     */
    public function procesarPagoSinFacturacion(Request $request)
    {
        try {
            $request->validate([
                'venta_id' => 'required|exists:impuestos_ventas,IdVentas',
                'montos' => 'required|array',
                'tipo_venta' => 'required|string|in:normal,tactil',
                'id_identificador_cliente' => 'nullable|exists:todos_identificador,IdIdentificador'
            ]);

            DB::beginTransaction();

            $ventaId = $request->venta_id;
            
            // Usar el cliente seleccionado o el operador por defecto
            $identificadorId = $request->id_identificador_cliente;
            
            if (!$identificadorId) {
                $identificadorId = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_operador')
                    ->where('IdOperador', session('operador_id'))
                    ->value('IdIdentificador');
            }
            
            if (!$identificadorId) {
                $identificadorId = 1;
            }

            // =============================================
            // 1. OBTENER EL NOMBRE DEL LUGAR DE VENTA
            // =============================================
            $ventaActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->first();
            
            $nombreLugarVenta = null;
            if ($ventaActual && $ventaActual->LugarVenta) {
                $lugarVenta = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_lugar_venta')
                    ->where('IdLugar', $ventaActual->LugarVenta)
                    ->first();
                $nombreLugarVenta = $lugarVenta ? $lugarVenta->Lugar : null;
            }

            // =============================================
            // 2. REGISTRAR LIQUIDACIÓN (métodos de pago)
            // =============================================
            foreach ($request->montos as $conceptoId => $monto) {
                if ($monto > 0) {
                    // Obtener el IdCuenta real desde el concepto
                    $concepto = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion_concepto')
                        ->where('IdConceptoLiquidacion', $conceptoId)
                        ->first();
                    
                    $idCuentaReal = $concepto ? $concepto->IdCuenta : $conceptoId;
                    
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->insert([
                            'IdVentas' => $ventaId,
                            'IdDiario' => 0,
                            'IdIdentificador' => $identificadorId,
                            'IdCuenta' => $idCuentaReal,
                            'Bolivianos' => $monto,
                        ]);
                }
            }

            // =============================================
            // 3. OBTENER NUEVO NÚMERO DE FACTURA
            // =============================================
            $ultimoNumeroFactura = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', session('cliente_id'))
                ->where('IdClienteSucursal', session('cliente_sucursal_id'))
                ->max('NumeroFactura');

            $nuevoNumeroFactura = ($ultimoNumeroFactura ?? 0) + 1;

            // =============================================
            // 4. ACTUALIZAR VENTA COMO FINALIZADA
            // =============================================
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update([
                    'ActivoInactivo' => 1,
                    'FechaUltimaActualizcion' => now(),
                    'IdNIT' => $identificadorId,
                    'NumeroFactura' => $nuevoNumeroFactura,
                    'IdEstado' => 1,  // 1 = Válida
                    'LugarVenta' => $nombreLugarVenta ?? $ventaActual->LugarVenta
                ]);

            // =============================================
            // 5. REGISTRAR MOVIMIENTO DE INVENTARIO (SALIDA)
            // =============================================
            $this->registrarSalidaInventario($ventaId);

            DB::commit();

            $this->limpiarSesionVenta($request->tipo_venta);

            return response()->json(['success' => true, 'message' => 'Venta completada']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error procesando pago sin facturación: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Registrar salida de inventario (descontar productos)
     */
    private function registrarSalidaInventario($ventaId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // Obtener la venta y su fecha
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $ventaId)
            ->first();
        
        if (!$venta) {
            throw new \Exception('Venta no encontrada');
        }
        
        // Obtener o crear la fecha en todos_fecha
        $fechaVenta = date('Y-m-d', strtotime($venta->FechaVenta));
        $idFecha = $this->obtenerIdFecha($fechaVenta);
        
        // Obtener almacén principal
        $idAlmacen = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_almacen')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('AlmacenPrincipal', 1)
            ->value('IdAlmacen');
        
        if (!$idAlmacen) {
            $idAlmacen = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_almacen')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->value('IdAlmacen');
        }
        
        // Tipo de operación: 2 = Venta
        $idTipoOperacion = 2;
        
        // Obtener los detalles de la venta
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle')
            ->where('idventas', $ventaId)
            ->get();
        
        foreach ($detalles as $detalle) {
            // Obtener los productos reales (porciones) de la relación
            $productosPorcion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->get();
            
            foreach ($productosPorcion as $porcion) {
                $cantidad = $porcion->Porcion * $detalle->unidades;
                
                // Obtener precio de costo del producto
                $precioCosto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle_precio_costo')
                    ->where('IdProducto', $porcion->IdProducto)
                    ->orderBy('IdPrecioCosto', 'DESC')
                    ->value('PrecioCosto');
                
                $costoTotal = $cantidad * ($precioCosto ?? 0);
                
                // Registrar movimiento en inventario_propiamente
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->insert([
                        'IdTipoDeOperacion' => $idTipoOperacion,
                        'IdDocumento' => $ventaId,
                        'IdFecha' => $idFecha,
                        'IdAlmacen' => $idAlmacen,
                        'IdProducto' => $porcion->IdProducto,
                        'Glosa' => "Venta Factura No {$venta->NumeroAutorizacion} - {$venta->NumeroFactura}",
                        'D_H' => 'H', // Haber (salida)
                        'Unidades' => $cantidad,
                        'Bolivianos' => $costoTotal,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                    ]);
            }
        }
    }

    /**
     * Obtener o crear IdFecha en todos_fecha
     */
    private function obtenerIdFecha($fecha)
    {
        $fechaObj = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', $fecha)
            ->first();
        
        if ($fechaObj) {
            return $fechaObj->IdFecha;
        }
        
        return DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->insertGetId([
                'Fecha' => $fecha,
                'ActivoInactivo' => 1,
                'CierreSucursal' => 0,
                'CierrePermanente' => 0,
            ]);
    }

    /**
     * Limpiar sesión según tipo de venta
     */
    private function limpiarSesionVenta($tipoVenta)
    {
        if ($tipoVenta === 'tactil') {
            session()->forget('venta_tactil_id');
            session()->forget('venta_tactil_lugar_id');
            session()->forget('venta_tactil_comisionista_id');
            session()->forget('venta_tactil_comisionista_identificador');
        } else {
            session()->forget('venta_actual_id');
        }
    }
}