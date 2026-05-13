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
use App\Http\Controllers\Facturacion\EmpresasHomeController;
use App\Http\Controllers\Facturacion\EmpresaController;           // ← AGREGAR ESTE
use App\Http\Controllers\Facturacion\ImportarEmpresaController;    // ← AGREGAR ESTE
use App\Http\Controllers\ContextoPdvController;                   // ← AGREGAR ESTE
use App\Http\Controllers\Facturacion\SiatCuisController;          // ← AGREGAR ESTE
use App\Http\Controllers\Facturacion\SiatCufdController;          // ← AGREGAR ESTE
use App\Http\Controllers\Facturacion\SiatCatalogoController;      // ← AGREGAR ESTE
// ========== NUEVOS CONTROLADORES PARA MENÚ TÁCTIL ==========
use App\Http\Controllers\Gestion\Inventario\CategoriaProductoController;
use App\Http\Controllers\Gestion\Inventario\AsignarProductoCategoriaController;
use App\Http\Controllers\PuntoVenta\MenuTactilController;
use App\Http\Controllers\PuntoVenta\NuevaVentaTactilController;
use App\Http\Controllers\PuntoVenta\CarritoTactilController;
use App\Http\Controllers\Facturacion\SucursalesHomeController;
use App\Http\Controllers\Facturacion\SucursalController;
use App\Http\Controllers\Facturacion\ImportarSucursalController;
use App\Http\Controllers\Facturacion\PuntoVentaHomeController;
use App\Http\Controllers\Facturacion\PuntoVentaController;
use App\Http\Controllers\Facturacion\SiatOperacionesController;
use App\Http\Controllers\Gestion\Impuestos\ComisionistaController;
use App\Http\Controllers\Gestion\Inventario\ProductoEstadoController;
use App\Http\Controllers\Gestion\Inventario\ProductoLineaController;
use App\Http\Controllers\Gestion\Inventario\ProductoGrupoController;
use App\Http\Controllers\Gestion\Inventario\ProductoGrupoAnalisisController;
use App\Http\Controllers\Gestion\Inventario\TipoOperacionController;
use App\Http\Controllers\Gestion\Inventario\UnidadMedidaController;
use App\Http\Controllers\Gestion\Inventario\AlmacenController;      
use App\Http\Controllers\Gestion\Impuestos\LugarVentaController;
use App\Http\Controllers\Gestion\Impuestos\LiquidacionConceptoController;
use App\Http\Controllers\Gestion\Inventario\InventarioActualController;
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

    // Agrega una ruta que verifique el contexto
    Route::get('/', function () {
        // Si ya tiene contexto, ir a venta táctil
        if (session('cliente_id') && session('cliente_sucursal_id')) {
            return redirect()->route('venta-tactil.nueva');
        }
        return redirect()->route('contexto.index');
    })->name('home');
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

    // ==================== FACTURACIÓN - SIAT ====================
    Route::prefix('facturacion/siat')->group(function () {
        // CUIS
        Route::get('/cuis/vigente', [\App\Http\Controllers\Facturacion\SiatCuisController::class, 'vigente'])->name('facturacion.siat.cuis.vigente');
        Route::post('/cuis/solicitar', [\App\Http\Controllers\Facturacion\SiatCuisController::class, 'solicitar'])->name('facturacion.siat.cuis.solicitar');
        
        // CUFD
        Route::get('/cufd/vigente', [\App\Http\Controllers\Facturacion\SiatCufdController::class, 'vigente'])->name('facturacion.siat.cufd.vigente');
        Route::post('/cufd/solicitar', [\App\Http\Controllers\Facturacion\SiatCufdController::class, 'solicitar'])->name('facturacion.siat.cufd.solicitar');
        
        // Catálogos
        Route::get('/catalogos', [\App\Http\Controllers\Facturacion\SiatCatalogoController::class, 'index'])->name('facturacion.siat.catalogos.index');
        Route::post('/catalogos/sync', [\App\Http\Controllers\Facturacion\SiatCatalogoController::class, 'syncAll'])->name('facturacion.siat.catalogos.sync');
        Route::post('/catalogos/sync/{key}', [\App\Http\Controllers\Facturacion\SiatCatalogoController::class, 'syncOne'])->name('facturacion.siat.catalogos.syncOne');
        Route::post('/catalogos/ping', [\App\Http\Controllers\Facturacion\SiatCatalogoController::class, 'pingFechaHora'])->name('facturacion.siat.catalogos.ping');
    });
    // ==================== FACTURACIÓN - EMPRESAS ====================
    Route::prefix('facturacion/empresas')->group(function () {
        Route::get('/', [\App\Http\Controllers\Facturacion\EmpresasHomeController::class, 'index'])->name('facturacion.empresas.home');
        Route::get('/crear', [\App\Http\Controllers\Facturacion\EmpresaController::class, 'create'])->name('facturacion.empresas.create');
        Route::post('/crear', [\App\Http\Controllers\Facturacion\EmpresaController::class, 'store'])->name('facturacion.empresas.store');
        Route::get('/importar', [\App\Http\Controllers\Facturacion\ImportarEmpresaController::class, 'index'])->name('facturacion.empresas.importar');
        Route::post('/importar', [\App\Http\Controllers\Facturacion\ImportarEmpresaController::class, 'store'])->name('facturacion.empresas.importar.store');
        Route::get('/clientes', [\App\Http\Controllers\Facturacion\ImportarEmpresaController::class, 'clientes'])->name('facturacion.empresas.clientes');
        Route::get('/ultimo-id-fecha', [\App\Http\Controllers\Facturacion\EmpresaController::class, 'ultimoIdFecha'])->name('facturacion.empresas.ultimo-id-fecha');
    });
    // ==================== FACTURACIÓN - SUCURSALES ====================
    Route::prefix('facturacion/sucursales')->group(function () {
        Route::get('/', [App\Http\Controllers\Facturacion\SucursalesHomeController::class, 'index'])->name('facturacion.sucursales.home');
        Route::get('/create', [App\Http\Controllers\Facturacion\SucursalController::class, 'create'])->name('facturacion.sucursales.create');
        Route::post('/', [App\Http\Controllers\Facturacion\SucursalController::class, 'store'])->name('facturacion.sucursales.store');
        
        // AJAX
        Route::get('/clientes', [App\Http\Controllers\Facturacion\SucursalController::class, 'clientes'])->name('facturacion.sucursales.clientes');
        Route::get('/plazas', [App\Http\Controllers\Facturacion\SucursalController::class, 'plazas'])->name('facturacion.sucursales.plazas');
        Route::get('/empresa-por-cliente', [App\Http\Controllers\Facturacion\SucursalController::class, 'empresaPorCliente'])->name('facturacion.sucursales.empresaPorCliente');
    });

    // Importar sucursales
    Route::prefix('facturacion/importar/sucursales')->group(function () {
        Route::get('/', [App\Http\Controllers\Facturacion\ImportarSucursalController::class, 'index'])->name('facturacion.importar.sucursales.index');
        Route::post('/', [App\Http\Controllers\Facturacion\ImportarSucursalController::class, 'store'])->name('facturacion.importar.sucursales.store');
        
        // AJAX
        Route::get('/clientes', [App\Http\Controllers\Facturacion\ImportarSucursalController::class, 'clientes'])->name('facturacion.importar.sucursales.clientes');
        Route::get('/sucursales', [App\Http\Controllers\Facturacion\ImportarSucursalController::class, 'sucursales'])->name('facturacion.importar.sucursales.lista');
    });


    // ==================== FACTURACIÓN - PUNTOS DE VENTA ====================
    Route::prefix('facturacion/puntos-venta')->group(function () {
        Route::get('/', [App\Http\Controllers\Facturacion\PuntoVentaHomeController::class, 'index'])->name('facturacion.puntos-venta.home');
        Route::get('/crear', [App\Http\Controllers\Facturacion\PuntoVentaController::class, 'create'])->name('facturacion.puntos-venta.create');
        Route::post('/', [App\Http\Controllers\Facturacion\PuntoVentaController::class, 'store'])->name('facturacion.puntos-venta.store');
        
        // AJAX
        Route::get('/sucursales', [App\Http\Controllers\Facturacion\PuntoVentaController::class, 'sucursales'])->name('facturacion.puntos-venta.sucursales');
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
    // ==================== SIAT - OPERACIONES ====================
    Route::prefix('facturacion/siat/operaciones')->group(function () {
        Route::get('/cierre', [App\Http\Controllers\Facturacion\SiatOperacionesController::class, 'showCierre'])->name('facturacion.siat.operaciones.cierre');
        Route::post('/cierre', [App\Http\Controllers\Facturacion\SiatOperacionesController::class, 'cierre'])->name('facturacion.siat.operaciones.cierre.post');
    });
    // CRUD Categorías de productos (menú táctil)
    Route::prefix('gestion/inventario/categorias-producto')->group(function () {
        Route::get('/', [App\Http\Controllers\Gestion\Inventario\CategoriaProductoController::class, 'index'])
            ->name('gestion.inventario.categorias-producto.index');
        Route::post('/', [App\Http\Controllers\Gestion\Inventario\CategoriaProductoController::class, 'store'])
            ->name('gestion.inventario.categorias-producto.store');
        Route::put('/{id}', [App\Http\Controllers\Gestion\Inventario\CategoriaProductoController::class, 'update'])
            ->name('gestion.inventario.categorias-producto.update');
        Route::delete('/{id}', [App\Http\Controllers\Gestion\Inventario\CategoriaProductoController::class, 'destroy'])
            ->name('gestion.inventario.categorias-producto.destroy');
    });
    // Asignar productos a categorías (menú táctil)
    Route::get('/gestion/inventario/asignar-productos-categoria', 
        [App\Http\Controllers\Gestion\Inventario\AsignarProductoCategoriaController::class, 'index'])
        ->name('gestion.inventario.asignar-productos-categoria.index');

    Route::post('/gestion/inventario/asignar-productos-categoria', 
        [App\Http\Controllers\Gestion\Inventario\AsignarProductoCategoriaController::class, 'store'])
        ->name('gestion.inventario.asignar-productos-categoria.store');
    // Menú Táctil (para vendedores)
    Route::prefix('venta-tactil')->group(function () {
        Route::get('/', [App\Http\Controllers\PuntoVenta\MenuTactilController::class, 'index'])
            ->name('venta-tactil.index');
        Route::get('/categoria/{id}', [App\Http\Controllers\PuntoVenta\MenuTactilController::class, 'verCategoria'])
            ->name('venta-tactil.categoria');
    });
    // Venta Táctil - Formulario de inicio
    Route::get('/venta-tactil/nueva', [App\Http\Controllers\PuntoVenta\NuevaVentaTactilController::class, 'create'])
        ->name('venta-tactil.nueva');
    Route::post('/venta-tactil/nueva', [App\Http\Controllers\PuntoVenta\NuevaVentaTactilController::class, 'store'])
        ->name('venta-tactil.nueva.store');

    // Venta Táctil - Menú de productos
    Route::prefix('venta-tactil')->group(function () {
        Route::get('/', [App\Http\Controllers\PuntoVenta\MenuTactilController::class, 'index'])
            ->name('venta-tactil.index');
        Route::get('/categoria/{id}', [App\Http\Controllers\PuntoVenta\MenuTactilController::class, 'verCategoria'])
            ->name('venta-tactil.categoria');
    });

    // Venta Táctil - Carrito
    Route::get('/venta-tactil/carrito', [App\Http\Controllers\PuntoVenta\CarritoTactilController::class, 'index'])
        ->name('venta-tactil.carrito');
    // Venta Táctil - Pago
    Route::get('/venta-tactil/pago', [PagoVentaController::class, 'createTactil'])
        ->name('venta-tactil.pago');

    // CRUD Conceptos de Liquidación (sin facturación)
    Route::prefix('gestion/impuestos/liquidacion-concepto')->group(function () {
        Route::get('/', [App\Http\Controllers\Gestion\Impuestos\LiquidacionConceptoController::class, 'index'])
            ->name('gestion.impuestos.liquidacion-concepto.index');
        Route::post('/', [App\Http\Controllers\Gestion\Impuestos\LiquidacionConceptoController::class, 'store'])
            ->name('gestion.impuestos.liquidacion-concepto.store');
        Route::put('/{id}', [App\Http\Controllers\Gestion\Impuestos\LiquidacionConceptoController::class, 'update'])
            ->name('gestion.impuestos.liquidacion-concepto.update');  // 👈 Asegurar que es PUT
        Route::delete('/{id}', [App\Http\Controllers\Gestion\Impuestos\LiquidacionConceptoController::class, 'destroy'])
            ->name('gestion.impuestos.liquidacion-concepto.destroy');
    });
    // Inventario Actual
    Route::get('/gestion/inventario/inventario-actual', [App\Http\Controllers\Gestion\Inventario\InventarioActualController::class, 'index'])
        ->name('gestion.inventario.inventario-actual.index');
    // Reporte de Inventario
    Route::get('/gestion/inventario/reporte-inventario', [App\Http\Controllers\Gestion\Inventario\ReporteInventarioController::class, 'index'])
        ->name('gestion.inventario.reporte-inventario.index');
});