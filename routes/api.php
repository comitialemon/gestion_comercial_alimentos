<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VentaTactilController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\PuntoVenta\PagoVentaController;
use App\Models\Gestion\Inventario\ProductoAprobacionVoto;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Models\Gestion\Contabilidad\FactorCambio;
use App\Http\Controllers\Gestion\Inventario\ProductoVentaController;

// Middleware web + auth para tener sesión
Route::middleware(['web', 'auth.operador'])->group(function () {
    
    // ==================== VENTA TÁCTIL - ENDPOINTS ====================
    Route::prefix('venta-tactil')->group(function () {
        Route::get('/precio/{idProducto}', [VentaTactilController::class, 'getPrecio']);
        Route::post('/agregar', [VentaTactilController::class, 'agregarProducto']);
        Route::get('/carrito', [VentaTactilController::class, 'getCarrito']);
        Route::put('/carrito/{itemId}', [VentaTactilController::class, 'actualizarCantidad']);
        Route::delete('/carrito/{itemId}', [VentaTactilController::class, 'eliminarProducto']);
        Route::delete('/cancelar', [VentaTactilController::class, 'cancelarVenta']);
    });
    
    // ==================== PAGO - ENDPOINTS COMPARTIDOS ====================
    Route::prefix('pago')->group(function () {
        // Con facturación
        Route::get('/metodos-con-facturacion', [PagoController::class, 'getMetodosPagoConFacturacion']);
        Route::post('/procesar-con-facturacion', [PagoController::class, 'procesarPagoConFacturacion']);
        
        // Sin facturación
        Route::get('/conceptos-sin-facturacion', [PagoController::class, 'getConceptosSinFacturacion']);
        Route::post('/procesar-sin-facturacion', [PagoVentaController::class, 'procesarPagoSinFacturacion']);
        
        // Común
        Route::post('/verificar-nit', [PagoController::class, 'verificarNit']);
        Route::get('/buscar-identificador', [PagoController::class, 'buscarIdentificador']);
    });
    
    // ==================== INVENTARIO - ENDPOINTS API ====================
    Route::prefix('inventario')->group(function () {
        Route::get('/stock/{idProducto}', [App\Http\Controllers\Gestion\Inventario\InventarioActualController::class, 'getStockProducto']);
        Route::get('/movimientos/{idProducto}', [App\Http\Controllers\Gestion\Inventario\InventarioActualController::class, 'getMovimientosProducto']);
    });
    
    // ==================== REPORTE INVENTARIO ====================
    Route::get('/inventario/reporte-movimientos', [App\Http\Controllers\Gestion\Inventario\ReporteInventarioController::class, 'getMovimientos']);
    
    // ==================== NOTIFICACIONES DE APROBACIÓN DE PRODUCTOS ====================
    Route::get('/notificaciones/pendientes', function () {
        $operadorId = session('operador_id');
        
        if (!$operadorId) {
            return response()->json([]);
        }
        
        $pendientes = ProductoAprobacionVoto::where('IdOperadorAprobador', $operadorId)
            ->where('Estado', 'pendiente')
            ->with(['solicitud.producto', 'solicitud.solicitante.identificador'])
            ->get()
            ->map(function($voto) {
                return [
                    'IdProductoAprobacionSolicitud' => $voto->IdProductoAprobacionSolicitud,
                    'IdProductoAprobacionVoto' => $voto->IdProductoAprobacionVoto,
                    'producto' => $voto->solicitud->producto,
                    'solicitante' => $voto->solicitud->solicitante,
                    'FechaSolicitud' => $voto->solicitud->FechaSolicitud,
                    'voto' => $voto,
                ];
            });
        
        return response()->json($pendientes);
    })->name('api.notificaciones.pendientes');
    
    // ==================== CONCEPTOS DE LIQUIDACIÓN (SIN FACTURACIÓN) ====================
    Route::get('/conceptos-liquidacion', [App\Http\Controllers\Gestion\Impuestos\LiquidacionConceptoController::class, 'getConceptosPorCliente'])
        ->name('api.conceptos-liquidacion');
    
    // ==================== FACTOR DE CAMBIO PARA DIARIOS ====================
    Route::get('/factor-cambio/{fechaId}/{cuentaId}', function ($fechaId, $cuentaId) {
        try {
            $cuenta = ContaCuenta::find($cuentaId);
            
            if (!$cuenta) {
                return response()->json([
                    'success' => true,
                    'tipoCambio' => 1,
                    'montoOtraMoneda' => 0,
                    'idMoneda' => 1
                ]);
            }
            
            if (!$cuenta->IdMoneda || $cuenta->IdMoneda == 1) {
                return response()->json([
                    'success' => true,
                    'tipoCambio' => 1,
                    'montoOtraMoneda' => 0,
                    'idMoneda' => 1
                ]);
            }
            
            $factor = FactorCambio::where('IdFecha', $fechaId)
                ->where('IdMoneda', $cuenta->IdMoneda)
                ->first();
            
            $tipoCambio = $factor ? (float) $factor->FactorCambio : 1;
            
            return response()->json([
                'success' => true,
                'tipoCambio' => $tipoCambio,
                'montoOtraMoneda' => 0,
                'idMoneda' => (int) $cuenta->IdMoneda
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error en factor-cambio: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'tipoCambio' => 1,
                'montoOtraMoneda' => 0,
                'idMoneda' => 1
            ]);
        }
    })->name('api.factor-cambio');
    
    // ==================== IDENTIFICADORES (para selects) ====================
    Route::get('/identificadores', function () {
        $identificadores = App\Models\Gestion\Todos\Identificador::orderBy('Nombre')
            ->get(['IdIdentificador as id', 'CI_NIT as ci', 'Nombre as nombre']);
        
        return response()->json($identificadores);
    })->name('api.identificadores');
    
    // ==================== CUENTAS CONTABLES (para selects) ====================
    Route::get('/cuentas-contables', function () {
        $clienteId = session('cliente_id');
        
        $cuentas = App\Models\Gestion\Contabilidad\ContaCuenta::where('AbiertoCerrado', 0)
            ->where('IdCliente', $clienteId)
            ->orderBy('Cuenta')
            ->get(['IdCuenta as id', 'Cuenta', 'Descripcion', 'TipoDeCuenta', 'IdMoneda', 'ActivoFijo']);
        
        return response()->json($cuentas);
    })->name('api.cuentas-contables');
    
    // ==================== ACTIVIDADES (para selects) ====================
    Route::get('/actividades', function () {
        $clienteId = session('cliente_id');
        
        $actividades = App\Models\Gestion\Todos\ClienteActividad::where('IdCliente', $clienteId)
            ->orderBy('Actividad')
            ->get(['IdActividad as id', 'Actividad as nombre']);
        
        return response()->json($actividades);
    })->name('api.actividades');

    // ==================== VERIFICAR COMPOSICIÓN DE PRODUCTO ====================
    Route::post('/productos-venta/verificar-composicion', [ProductoVentaController::class, 'verificarComposicion']);
    
    // ==================== NIT PREDEFINIDO PARA VENTA ====================
    Route::get('/venta/{ventaId}/nit-predefinido', function ($ventaId) {
        try {
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->first();
            
            if (!$venta) {
                return response()->json(['success' => false, 'message' => 'Venta no encontrada']);
            }
            
            $identificador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('IdIdentificador', $venta->IdNIT)
                ->first();
            
            return response()->json([
                'success' => true,
                'nit' => $identificador ? $identificador->CI_NIT : 0,
                'nombre' => $identificador ? $identificador->Nombre : '',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en nit-predefinido: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    })->name('api.venta.nit-predefinido');
});