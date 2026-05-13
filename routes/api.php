<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VentaTactilController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\PuntoVenta\PagoVentaController;

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
    // Inventario - endpoints API
    Route::prefix('inventario')->group(function () {
        Route::get('/stock/{idProducto}', [App\Http\Controllers\Gestion\Inventario\InventarioActualController::class, 'getStockProducto']);
        Route::get('/movimientos/{idProducto}', [App\Http\Controllers\Gestion\Inventario\InventarioActualController::class, 'getMovimientosProducto']);
    });
    // Reporte inventario - movimientos
    Route::get('/inventario/reporte-movimientos', [App\Http\Controllers\Gestion\Inventario\ReporteInventarioController::class, 'getMovimientos']);
});