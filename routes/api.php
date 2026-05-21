<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VentaTactilController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\PuntoVenta\PagoVentaController;
use App\Models\Gestion\Inventario\ProductoAprobacionVoto;

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
    
    // 🔥 NOTIFICACIONES DE APROBACIÓN DE PRODUCTOS
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
});