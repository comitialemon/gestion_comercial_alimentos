<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Gestion\Todos\Operador\LoginController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\OficialController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\Gestion\Menu\AsignarMenuController;
use App\Http\Controllers\Gestion\Todos\IdentificadorController;
use App\Http\Controllers\PuntoVenta\NuevaVentaController;
use App\Http\Controllers\PuntoVenta\FormularioVentaController;
use App\Http\Controllers\PuntoVenta\PagoVentaController;
use App\Http\Controllers\Facturacion\MetodoPagoMapeoController;
use App\Http\Controllers\Gestion\Impuestos\LugarVenta;
// ============================================
// RUTAS PÚBLICAS
// ============================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login.show');
    Route::post('/login', [LoginController::class, 'do'])->middleware('throttle:login')->name('login.do');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ============================================
// RUTAS PROTEGIDAS
// ============================================
Route::middleware(['auth.operador'])->group(function () {

    Route::get('/', fn() => redirect()->route('contexto.index'))->name('home');

    // CONTEXTO
    Route::get('/contexto', [ContextoController::class, 'index'])->name('contexto.index');
    Route::get('/contexto/sucursales/{empresa}', [ContextoController::class, 'sucursales'])->name('contexto.sucursales');
    Route::post('/contexto', [ContextoController::class, 'store'])->name('contexto.store');

    // ==================== CONTEXTO · PDV (SOLO para empresas con facturación) ====================
    Route::get('/contexto/pdv', [\App\Http\Controllers\ContextoPdvController::class, 'index'])->name('contexto.pdv.index');
    Route::post('/contexto/pdv', [\App\Http\Controllers\ContextoPdvController::class, 'store'])->name('contexto.pdv.store');
    Route::get('/contexto/pdv/lista', [\App\Http\Controllers\ContextoPdvController::class, 'lista'])->name('contexto.pdv.lista');

    // GESTIÓN DE MENÚS
    Route::get('/gestion/menu/asignar', [AsignarMenuController::class, 'index'])->name('gestion.menu.asignar');
    Route::get('/gestion/menu/asignar/{operadorId}', [AsignarMenuController::class, 'getAsignados'])->name('gestion.menu.asignar.get');
    Route::post('/gestion/menu/asignar', [AsignarMenuController::class, 'store'])->name('gestion.menu.asignar.store');

    // IDENTIFICADOR
    // ==================== IDENTIFICADORES ====================
    Route::prefix('gestion/todos/identificador')->group(function () {
        Route::get('/', [\App\Http\Controllers\Gestion\Todos\IdentificadorController::class, 'index'])->name('gestion.todos.identificador.index');
        Route::post('/', [\App\Http\Controllers\Gestion\Todos\IdentificadorController::class, 'store'])->name('gestion.todos.identificador.store');
        Route::put('/{id}', [\App\Http\Controllers\Gestion\Todos\IdentificadorController::class, 'update'])->name('gestion.todos.identificador.update');
        Route::delete('/{id}', [\App\Http\Controllers\Gestion\Todos\IdentificadorController::class, 'destroy'])->name('gestion.todos.identificador.destroy');
    });
    // OFICIAL
    Route::get('/oficial', [OficialController::class, 'index'])->name('oficial.index');

    // API MENÚ
    Route::get('/api/menu', [MenuController::class, 'index'])->name('api.menu');

    // ==================== PUNTO DE VENTA ====================
    
    // Paso 1: Seleccionar lugar de venta y comisionista
    Route::get('/venta-factura/crear', [NuevaVentaController::class, 'create'])->name('ventas.crear');
    Route::post('/venta-factura/store', [NuevaVentaController::class, 'store'])->name('ventas.store');
    
    // Paso 2: Formulario de venta (productos)
    Route::get('/venta-factura/nueva', [FormularioVentaController::class, 'create'])->name('ventas.formulario');
    Route::post('/venta-factura/guardar', [FormularioVentaController::class, 'store'])->name('ventas.guardar');
    
    // APIs para productos
    Route::get('/api/venta/grupos', [FormularioVentaController::class, 'getGrupos']);
    Route::get('/api/venta/productos/{idVentaGrupo}', [FormularioVentaController::class, 'getProductos']);
    Route::get('/api/venta/precio-producto/{idProducto}', [FormularioVentaController::class, 'getPrecioProducto']);
    
    // Paso 3: Pago
    Route::get('/venta-factura/pago', [PagoVentaController::class, 'create'])->name('ventas.pago');
    Route::post('/venta-factura/buscar-cliente', [PagoVentaController::class, 'buscarCliente'])->name('ventas.buscar-cliente');
    Route::post('/venta-factura/procesar-pago', [PagoVentaController::class, 'store'])->name('ventas.procesar-pago');
    
    // ==================== FACTURACIÓN - MAPEO MÉTODOS DE PAGO ====================
    Route::get('/facturacion/metodos-pago/mapeo', [MetodoPagoMapeoController::class, 'index'])->name('facturacion.metodos-pago.mapeo');
    Route::post('/facturacion/metodos-pago/mapeo', [MetodoPagoMapeoController::class, 'store'])->name('facturacion.metodos-pago.mapeo.store');
    
    // ==================== API PARA CATÁLOGOS DE FACTURACIÓN ====================
    
    // Tipos de documento
    Route::get('/api/catalogos/tipos-documento', function () {
        try {
            $response = Illuminate\Support\Facades\Http::timeout(10)->get('http://siat-app:80/api/v1/tipos-documento');
            if ($response->successful()) {
                return response()->json($response->json());
            }
            return response()->json([]);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    });

    // Monedas - Filtrar solo Bolivianos
    Route::get('/api/catalogos/monedas', function () {
        try {
            $response = Illuminate\Support\Facades\Http::timeout(10)->get('http://siat-app:80/api/v1/monedas');
            if ($response->successful()) {
                $data = $response->json();
                $monedas = isset($data['data']) ? $data['data'] : $data;
                
                // 🔥 Filtrar solo Bolivianos (código 1 o sigla BOB)
                $bolivianos = array_filter($monedas, function($m) {
                    return $m['codigo'] == 1 || $m['sigla'] == 'BOB' || str_contains($m['descripcion'], 'Boliviano');
                });
                
                $resultado = array_values($bolivianos);
                
                // Si no encontró, devolver un Boliviano por defecto
                if (empty($resultado)) {
                    return response()->json([
                        ['id' => 1, 'codigo' => 1, 'sigla' => 'BOB', 'descripcion' => 'Boliviano']
                    ]);
                }
                
                return response()->json($resultado);
            }
            return response()->json([['id' => 1, 'sigla' => 'BOB', 'descripcion' => 'Boliviano']]);
        } catch (\Exception $e) {
            \Log::error('Error cargando monedas: ' . $e->getMessage());
            return response()->json([['id' => 1, 'sigla' => 'BOB', 'descripcion' => 'Boliviano']]);
        }
    });
    
    // 🔥 MÉTODOS DE PAGO - Usa el controlador que filtra por mapeo
    Route::get('/api/catalogos/metodos-pago', [PagoVentaController::class, 'getMetodosPago']);
    
    // API para mapeos de pago
    Route::get('/api/venta/mapeos-metodos-pago', function () {
        $mapeos = App\Models\Gestion\Contabilidad\MetodoPagoMapeo::where('idCliente', session('cliente_id'))
            ->where('idSucursal', session('cliente_sucursal_id'))
            ->where('activo', 1)
            ->get();
        
        $resultado = [];
        foreach ($mapeos as $m) {
            if (!isset($resultado[$m->codigo_siat])) {
                $resultado[$m->codigo_siat] = [];
            }
            if (!in_array($m->idContaCuenta, $resultado[$m->codigo_siat])) {
                $resultado[$m->codigo_siat][] = $m->idContaCuenta;
            }
        }
        
        return response()->json($resultado);
    });
    
    // Limpiar sesión de venta
    Route::post('/venta-factura/limpiar-sesion', function () {
        session()->forget('venta_actual_id');
        return response()->json(['success' => true]);
    });
    
    // Debug
    Route::get('/debug/mapeo-codigos', function () {
        $mapeos = App\Models\Gestion\Contabilidad\MetodoPagoMapeo::where('idCliente', session('cliente_id'))
            ->where('idSucursal', session('cliente_sucursal_id'))
            ->where('activo', 1)
            ->pluck('codigo_siat')
            ->unique()
            ->values();
        
        return response()->json([
            'codigos_en_mapeo' => $mapeos,
            'cliente_id' => session('cliente_id'),
            'sucursal_id' => session('cliente_sucursal_id')
        ]);
    });
    // ==================== LUGARES DE VENTA ====================
    Route::prefix('gestion/lugar-venta')->group(function () {
        Route::get('/', [\App\Http\Controllers\Gestion\Impuestos\LugarVentaController::class, 'index'])->name('gestion.lugar-venta.index');
        Route::get('/create', [\App\Http\Controllers\Gestion\Impuestos\LugarVentaController::class, 'create'])->name('gestion.lugar-venta.create');
        Route::post('/', [\App\Http\Controllers\Gestion\Impuestos\LugarVentaController::class, 'store'])->name('gestion.lugar-venta.store');
        Route::get('/{id}/edit', [\App\Http\Controllers\Gestion\Impuestos\LugarVentaController::class, 'edit'])->name('gestion.lugar-venta.edit');
        Route::put('/{id}', [\App\Http\Controllers\Gestion\Impuestos\LugarVentaController::class, 'update'])->name('gestion.lugar-venta.update');
        Route::delete('/{id}', [\App\Http\Controllers\Gestion\Impuestos\LugarVentaController::class, 'destroy'])->name('gestion.lugar-venta.destroy');
        Route::get('/sucursales/{clienteId}', [\App\Http\Controllers\Gestion\Impuestos\LugarVentaController::class, 'getSucursales'])->name('gestion.lugar-venta.sucursales');
    });
    // ==================== COMISIONISTAS ====================
    Route::prefix('gestion/comisionista')->group(function () {
        Route::get('/', [\App\Http\Controllers\Gestion\Impuestos\ComisionistaController::class, 'index'])->name('gestion.comisionista.index');
        Route::get('/create', [\App\Http\Controllers\Gestion\Impuestos\ComisionistaController::class, 'create'])->name('gestion.comisionista.create');
        Route::post('/', [\App\Http\Controllers\Gestion\Impuestos\ComisionistaController::class, 'store'])->name('gestion.comisionista.store');
        Route::get('/{id}/edit', [\App\Http\Controllers\Gestion\Impuestos\ComisionistaController::class, 'edit'])->name('gestion.comisionista.edit');
        Route::put('/{id}', [\App\Http\Controllers\Gestion\Impuestos\ComisionistaController::class, 'update'])->name('gestion.comisionista.update');
        Route::delete('/{id}', [\App\Http\Controllers\Gestion\Impuestos\ComisionistaController::class, 'destroy'])->name('gestion.comisionista.destroy');
        Route::get('/buscar-identificador', [\App\Http\Controllers\Gestion\Impuestos\ComisionistaController::class, 'buscarIdentificador'])->name('gestion.comisionista.buscar-identificador');
    });

    // ==================== INVENTARIO - CATÁLOGOS ====================
    Route::prefix('gestion/inventario')->group(function () {
        // Estados de Producto
        Route::resource('producto-estado', \App\Http\Controllers\Gestion\Inventario\ProductoEstadoController::class)->except(['show', 'create', 'edit']);
        
        // Líneas de Producto
        Route::resource('producto-linea', \App\Http\Controllers\Gestion\Inventario\ProductoLineaController::class)->except(['show', 'create', 'edit']);
        
        // Grupos de Producto
        Route::resource('producto-grupo', \App\Http\Controllers\Gestion\Inventario\ProductoGrupoController::class)->except(['show', 'create', 'edit']);
        
        // Grupos de Análisis
        Route::resource('producto-grupo-analisis', \App\Http\Controllers\Gestion\Inventario\ProductoGrupoAnalisisController::class)->except(['show', 'create', 'edit']);
        
        // Tipos de Operación
        Route::resource('tipo-operacion', \App\Http\Controllers\Gestion\Inventario\TipoOperacionController::class)->except(['show', 'create', 'edit']);
        
        // Unidades de Medida
        Route::resource('unidad-medida', \App\Http\Controllers\Gestion\Inventario\UnidadMedidaController::class)->except(['show', 'create', 'edit']);
        
        // Almacenes
        Route::resource('almacen', \App\Http\Controllers\Gestion\Inventario\AlmacenController::class)->except(['show', 'create', 'edit']);
});
});