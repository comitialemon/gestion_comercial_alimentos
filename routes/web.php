<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Gestion\Todos\Operador\LoginController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\ContextoPdvController;
use App\Http\Controllers\OficialController;
use App\Http\Controllers\Menu\MenuController;
use App\Http\Controllers\Gestion\Menu\AsignarMenuController;
use App\Http\Controllers\Gestion\Todos\IdentificadorController;
use App\Http\Controllers\Gestion\Impuestos\LugarVentaController;
use App\Http\Controllers\Gestion\Impuestos\ComisionistaController;
use App\Http\Controllers\Gestion\Impuestos\LiquidacionConceptoController;
use App\Http\Controllers\Gestion\Impuestos\CompraController;
use App\Http\Controllers\Gestion\Contabilidad\EgresoController;
use App\Http\Controllers\Gestion\Inventario\ProductoEstadoController;
use App\Http\Controllers\Gestion\Inventario\ProductoLineaController;
use App\Http\Controllers\Gestion\Inventario\ProductoGrupoController;
use App\Http\Controllers\Gestion\Inventario\ProductoGrupoAnalisisController;
use App\Http\Controllers\Gestion\Inventario\TipoOperacionController;
use App\Http\Controllers\Gestion\Inventario\UnidadMedidaController;
use App\Http\Controllers\Gestion\Inventario\AlmacenController;
use App\Http\Controllers\Gestion\Inventario\CategoriaProductoController;
use App\Http\Controllers\Gestion\Inventario\AsignarProductoCategoriaController;
use App\Http\Controllers\Gestion\Inventario\InventarioActualController;
use App\Http\Controllers\Gestion\Inventario\ReporteInventarioController;
use App\Http\Controllers\Gestion\Inventario\AjusteInventarioController;
use App\Http\Controllers\PuntoVenta\NuevaVentaController;
use App\Http\Controllers\PuntoVenta\FormularioVentaController;
use App\Http\Controllers\PuntoVenta\PagoVentaController;
use App\Http\Controllers\PuntoVenta\MenuTactilController;
use App\Http\Controllers\PuntoVenta\NuevaVentaTactilController;
use App\Http\Controllers\PuntoVenta\CarritoTactilController;
use App\Http\Controllers\Facturacion\MetodoPagoMapeoController;
use App\Http\Controllers\Facturacion\EmpresasHomeController;
use App\Http\Controllers\Facturacion\EmpresaController;
use App\Http\Controllers\Facturacion\ImportarEmpresaController;
use App\Http\Controllers\Facturacion\SucursalesHomeController;
use App\Http\Controllers\Facturacion\SucursalController;
use App\Http\Controllers\Facturacion\ImportarSucursalController;
use App\Http\Controllers\Facturacion\PuntoVentaHomeController;
use App\Http\Controllers\Facturacion\PuntoVentaController;
use App\Http\Controllers\Facturacion\SiatCuisController;
use App\Http\Controllers\Facturacion\SiatCufdController;
use App\Http\Controllers\Facturacion\SiatCatalogoController;
use App\Http\Controllers\Facturacion\SiatOperacionesController;

// ============================================
// RUTAS PÚBLICAS (Sin autenticación)
// ============================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login.show');
    Route::post('/login', [LoginController::class, 'do'])->middleware('throttle:login')->name('login.do');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ============================================
// RUTAS PROTEGIDAS (Con autenticación)
// ============================================
Route::middleware(['auth.operador'])->group(function () {

    // ==================== HOME / REDIRECCIONES ====================
    Route::get('/', function () {
        if (session('cliente_id') && session('cliente_sucursal_id')) {
            return redirect()->route('venta-tactil.nueva');
        }
        return redirect()->route('contexto.index');
    })->name('home');

    Route::get('/oficial', [OficialController::class, 'index'])->name('oficial.index');

    // ==================== CONTEXTO ====================
    Route::prefix('contexto')->middleware(['evitar.contexto.duplicado'])->group(function () {
        Route::get('/', [ContextoController::class, 'index'])->name('contexto.index');
        Route::get('/sucursales/{empresa}', [ContextoController::class, 'sucursales'])->name('contexto.sucursales');
        Route::post('/', [ContextoController::class, 'store'])->name('contexto.store');
    });

    // ==================== CONTEXTO · PDV ====================
    Route::prefix('contexto/pdv')->middleware(['evitar.contexto.duplicado'])->group(function () {
        Route::get('/', [ContextoPdvController::class, 'index'])->name('contexto.pdv.index');
        Route::post('/', [ContextoPdvController::class, 'store'])->name('contexto.pdv.store');
        Route::get('/lista', [ContextoPdvController::class, 'lista'])->name('contexto.pdv.lista');
    });

    // ==================== MENÚS ====================
    Route::prefix('gestion/menu')->group(function () {
        Route::get('/asignar', [AsignarMenuController::class, 'index'])->name('gestion.menu.asignar');
        Route::get('/asignar/{operadorId}', [AsignarMenuController::class, 'getAsignados'])->name('gestion.menu.asignar.get');
        Route::post('/asignar', [AsignarMenuController::class, 'store'])->name('gestion.menu.asignar.store');
    });

    Route::get('/api/menu', [MenuController::class, 'index'])->name('api.menu');

    // ==================== IDENTIFICADORES ====================
    Route::prefix('gestion/todos/identificador')->group(function () {
        Route::get('/', [IdentificadorController::class, 'index'])->name('gestion.todos.identificador.index');
        Route::post('/', [IdentificadorController::class, 'store'])->name('gestion.todos.identificador.store');
        Route::put('/{id}', [IdentificadorController::class, 'update'])->name('gestion.todos.identificador.update');
        Route::delete('/{id}', [IdentificadorController::class, 'destroy'])->name('gestion.todos.identificador.destroy');
    });

    // ==================== IMPUESTOS - LUGARES DE VENTA ====================
    Route::prefix('gestion/lugar-venta')->group(function () {
        Route::get('/', [LugarVentaController::class, 'index'])->name('gestion.lugar-venta.index');
        Route::get('/create', [LugarVentaController::class, 'create'])->name('gestion.lugar-venta.create');
        Route::post('/', [LugarVentaController::class, 'store'])->name('gestion.lugar-venta.store');
        Route::get('/{id}/edit', [LugarVentaController::class, 'edit'])->name('gestion.lugar-venta.edit');
        Route::put('/{id}', [LugarVentaController::class, 'update'])->name('gestion.lugar-venta.update');
        Route::delete('/{id}', [LugarVentaController::class, 'destroy'])->name('gestion.lugar-venta.destroy');
        Route::get('/sucursales/{clienteId}', [LugarVentaController::class, 'getSucursales'])->name('gestion.lugar-venta.sucursales');
    });

    // ==================== IMPUESTOS - COMISIONISTAS ====================
    Route::prefix('gestion/comisionista')->group(function () {
        Route::get('/', [ComisionistaController::class, 'index'])->name('gestion.comisionista.index');
        Route::get('/create', [ComisionistaController::class, 'create'])->name('gestion.comisionista.create');
        Route::post('/', [ComisionistaController::class, 'store'])->name('gestion.comisionista.store');
        Route::get('/{id}/edit', [ComisionistaController::class, 'edit'])->name('gestion.comisionista.edit');
        Route::put('/{id}', [ComisionistaController::class, 'update'])->name('gestion.comisionista.update');
        Route::delete('/{id}', [ComisionistaController::class, 'destroy'])->name('gestion.comisionista.destroy');
        Route::get('/buscar-identificador', [ComisionistaController::class, 'buscarIdentificador'])->name('gestion.comisionista.buscar-identificador');
    });

    // ==================== IMPUESTOS - LIQUIDACIÓN CONCEPTOS ====================
    Route::prefix('gestion/impuestos/liquidacion-concepto')->group(function () {
        Route::get('/', [LiquidacionConceptoController::class, 'index'])->name('gestion.impuestos.liquidacion-concepto.index');
        Route::post('/', [LiquidacionConceptoController::class, 'store'])->name('gestion.impuestos.liquidacion-concepto.store');
        Route::put('/{id}', [LiquidacionConceptoController::class, 'update'])->name('gestion.impuestos.liquidacion-concepto.update');
        Route::delete('/{id}', [LiquidacionConceptoController::class, 'destroy'])->name('gestion.impuestos.liquidacion-concepto.destroy');
    });

    // ==================== COMPRAS ====================
    Route::prefix('gestion/compras')->group(function () {
        Route::get('/', [CompraController::class, 'index'])->name('compras.index');
        Route::get('/create', [CompraController::class, 'create'])->name('compras.create');
        Route::put('/actualizar-cabecera/{id}', [CompraController::class, 'actualizarCabecera'])->name('compras.actualizar-cabecera');
        Route::post('/agregar-detalle', [CompraController::class, 'agregarDetalle'])->name('compras.agregar-detalle');
        Route::delete('/eliminar-detalle/{id}', [CompraController::class, 'eliminarDetalle'])->name('compras.eliminar-detalle');
        Route::post('/contabilizar/{id}', [CompraController::class, 'contabilizar'])->name('compras.contabilizar');
        Route::get('/{id}', [CompraController::class, 'show'])->name('compras.show');
        Route::get('/{id}/pdf', [CompraController::class, 'pdf'])->name('compras.pdf');
    });

    // ==================== CONTABILIDAD - EGRESOS ====================
    Route::prefix('gestion/egresos')->group(function () {
        Route::get('/', [EgresoController::class, 'index'])->name('egresos.index');
        Route::get('/create', [EgresoController::class, 'create'])->name('egresos.create');
        Route::post('/', [EgresoController::class, 'store'])->name('egresos.store');
        Route::get('/{id}/edit', [EgresoController::class, 'edit'])->name('egresos.edit');
        Route::put('/{id}', [EgresoController::class, 'update'])->name('egresos.update');
        Route::get('/{id}/pdf', [EgresoController::class, 'pdf'])->name('egresos.pdf');
    });

    // ==================== INVENTARIO - CATÁLOGOS ====================
    Route::prefix('gestion/inventario')->group(function () {
        Route::resource('producto-estado', ProductoEstadoController::class)->except(['show', 'create', 'edit']);
        Route::resource('producto-linea', ProductoLineaController::class)->except(['show', 'create', 'edit']);
        Route::resource('producto-grupo', ProductoGrupoController::class)->except(['show', 'create', 'edit']);
        Route::resource('producto-grupo-analisis', ProductoGrupoAnalisisController::class)->except(['show', 'create', 'edit']);
        Route::resource('tipo-operacion', TipoOperacionController::class)->except(['show', 'create', 'edit']);
        Route::resource('unidad-medida', UnidadMedidaController::class)->except(['show', 'create', 'edit']);
        Route::resource('almacen', AlmacenController::class)->except(['show', 'create', 'edit']);
    });

    // ==================== INVENTARIO - CATEGORÍAS MENÚ TÁCTIL ====================
    Route::prefix('gestion/inventario/categorias-producto')->group(function () {
        Route::get('/', [CategoriaProductoController::class, 'index'])->name('gestion.inventario.categorias-producto.index');
        Route::post('/', [CategoriaProductoController::class, 'store'])->name('gestion.inventario.categorias-producto.store');
        Route::put('/{id}', [CategoriaProductoController::class, 'update'])->name('gestion.inventario.categorias-producto.update');
        Route::delete('/{id}', [CategoriaProductoController::class, 'destroy'])->name('gestion.inventario.categorias-producto.destroy');
    });

    // ==================== INVENTARIO - ASIGNAR PRODUCTOS A CATEGORÍAS ====================
    Route::get('/gestion/inventario/asignar-productos-categoria', [AsignarProductoCategoriaController::class, 'index'])->name('gestion.inventario.asignar-productos-categoria.index');
    Route::post('/gestion/inventario/asignar-productos-categoria', [AsignarProductoCategoriaController::class, 'store'])->name('gestion.inventario.asignar-productos-categoria.store');

    // ==================== INVENTARIO - ACTUAL Y REPORTES ====================
    Route::get('/gestion/inventario/inventario-actual', [InventarioActualController::class, 'index'])->name('gestion.inventario.inventario-actual.index');
    Route::get('/gestion/inventario/reporte-inventario', [ReporteInventarioController::class, 'index'])->name('gestion.inventario.reporte-inventario.index');

    // ==================== INVENTARIO - AJUSTES ====================
    Route::prefix('gestion/inventario/ajustes')->group(function () {
        Route::get('/', [AjusteInventarioController::class, 'index'])->name('ajustes-inventario.index');
        Route::get('/create', [AjusteInventarioController::class, 'create'])->name('ajustes-inventario.create');
        Route::put('/cabecera/{id}', [AjusteInventarioController::class, 'guardarCabecera'])->name('ajustes-inventario.cabecera');
        Route::post('/detalle', [AjusteInventarioController::class, 'agregarDetalle'])->name('ajustes-inventario.agregar-detalle');
        Route::delete('/detalle/{id}', [AjusteInventarioController::class, 'eliminarDetalle'])->name('ajustes-inventario.eliminar-detalle');
        Route::post('/contabilizar/{id}', [AjusteInventarioController::class, 'contabilizar'])->name('ajustes-inventario.contabilizar');
        Route::get('/{id}', [AjusteInventarioController::class, 'show'])->name('ajustes-inventario.show');
        Route::get('/{id}/pdf', [AjusteInventarioController::class, 'pdf'])->name('ajustes-inventario.pdf');
    });

    // ==================== PUNTO DE VENTA (VENTA NORMAL) ====================
    Route::prefix('venta-factura')->group(function () {
        // Paso 1: Seleccionar lugar y comisionista
        Route::get('/crear', [NuevaVentaController::class, 'create'])->name('ventas.crear');
        Route::post('/store', [NuevaVentaController::class, 'store'])->name('ventas.store');
        
        // Paso 2: Formulario de venta (productos)
        Route::get('/nueva', [FormularioVentaController::class, 'create'])->name('ventas.formulario');
        Route::post('/guardar', [FormularioVentaController::class, 'store'])->name('ventas.guardar');
        
        // Paso 3: Pago
        Route::get('/pago', [PagoVentaController::class, 'create'])->name('ventas.pago');
        Route::post('/buscar-cliente', [PagoVentaController::class, 'buscarCliente'])->name('ventas.buscar-cliente');
        Route::post('/procesar-pago', [PagoVentaController::class, 'store'])->name('ventas.procesar-pago');
    });

    // ==================== PUNTO DE VENTA (VENTA TÁCTIL) ====================
    Route::prefix('venta-tactil')->group(function () {
        // Formulario de inicio
        Route::get('/nueva', [NuevaVentaTactilController::class, 'create'])->name('venta-tactil.nueva');
        Route::post('/nueva', [NuevaVentaTactilController::class, 'store'])->name('venta-tactil.nueva.store');
        
        // Menú de categorías y productos
        Route::get('/', [MenuTactilController::class, 'index'])->name('venta-tactil.index');
        Route::get('/categoria/{id}', [MenuTactilController::class, 'verCategoria'])->name('venta-tactil.categoria');
        
        // Carrito
        Route::get('/carrito', [CarritoTactilController::class, 'index'])->name('venta-tactil.carrito');
        
        // Pago
        Route::get('/pago', [PagoVentaController::class, 'createTactil'])->name('venta-tactil.pago');
    });

    // ==================== APIs DE PRODUCTOS (VENTA NORMAL) ====================
    Route::prefix('api/venta')->group(function () {
        Route::get('/grupos', [FormularioVentaController::class, 'getGrupos']);
        Route::get('/productos/{idVentaGrupo}', [FormularioVentaController::class, 'getProductos']);
        Route::get('/precio-producto/{idProducto}', [FormularioVentaController::class, 'getPrecioProducto']);
    });

    // ==================== API PARA CATÁLOGOS DE FACTURACIÓN ====================
    Route::prefix('api/catalogos')->group(function () {
        // Tipos de documento
        Route::get('/tipos-documento', function () {
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

        // Monedas
        Route::get('/monedas', function () {
            try {
                $response = Illuminate\Support\Facades\Http::timeout(10)->get('http://siat-app:80/api/v1/monedas');
                if ($response->successful()) {
                    $data = $response->json();
                    $monedas = isset($data['data']) ? $data['data'] : $data;
                    $bolivianos = array_filter($monedas, function($m) {
                        return $m['codigo'] == 1 || $m['sigla'] == 'BOB' || str_contains($m['descripcion'], 'Boliviano');
                    });
                    $resultado = array_values($bolivianos);
                    if (empty($resultado)) {
                        return response()->json([['id' => 1, 'codigo' => 1, 'sigla' => 'BOB', 'descripcion' => 'Boliviano']]);
                    }
                    return response()->json($resultado);
                }
                return response()->json([['id' => 1, 'sigla' => 'BOB', 'descripcion' => 'Boliviano']]);
            } catch (\Exception $e) {
                \Log::error('Error cargando monedas: ' . $e->getMessage());
                return response()->json([['id' => 1, 'sigla' => 'BOB', 'descripcion' => 'Boliviano']]);
            }
        });

        // Métodos de pago
        Route::get('/metodos-pago', [PagoVentaController::class, 'getMetodosPago']);
    });

    // ==================== API PARA MAPEOS DE PAGO ====================
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

    // ==================== FACTURACIÓN - MAPEO MÉTODOS DE PAGO ====================
    Route::get('/facturacion/metodos-pago/mapeo', [MetodoPagoMapeoController::class, 'index'])->name('facturacion.metodos-pago.mapeo');
    Route::post('/facturacion/metodos-pago/mapeo', [MetodoPagoMapeoController::class, 'store'])->name('facturacion.metodos-pago.mapeo.store');

    // ==================== FACTURACIÓN - EMPRESAS ====================
    Route::prefix('facturacion/empresas')->group(function () {
        Route::get('/', [EmpresasHomeController::class, 'index'])->name('facturacion.empresas.home');
        Route::get('/crear', [EmpresaController::class, 'create'])->name('facturacion.empresas.create');
        Route::post('/crear', [EmpresaController::class, 'store'])->name('facturacion.empresas.store');
        Route::get('/importar', [ImportarEmpresaController::class, 'index'])->name('facturacion.empresas.importar');
        Route::post('/importar', [ImportarEmpresaController::class, 'store'])->name('facturacion.empresas.importar.store');
        Route::get('/clientes', [ImportarEmpresaController::class, 'clientes'])->name('facturacion.empresas.clientes');
        Route::get('/ultimo-id-fecha', [EmpresaController::class, 'ultimoIdFecha'])->name('facturacion.empresas.ultimo-id-fecha');
    });

    // ==================== FACTURACIÓN - SUCURSALES ====================
    Route::prefix('facturacion/sucursales')->group(function () {
        Route::get('/', [SucursalesHomeController::class, 'index'])->name('facturacion.sucursales.home');
        Route::get('/create', [SucursalController::class, 'create'])->name('facturacion.sucursales.create');
        Route::post('/', [SucursalController::class, 'store'])->name('facturacion.sucursales.store');
        Route::get('/clientes', [SucursalController::class, 'clientes'])->name('facturacion.sucursales.clientes');
        Route::get('/plazas', [SucursalController::class, 'plazas'])->name('facturacion.sucursales.plazas');
        Route::get('/empresa-por-cliente', [SucursalController::class, 'empresaPorCliente'])->name('facturacion.sucursales.empresaPorCliente');
    });

    // ==================== FACTURACIÓN - IMPORTAR SUCURSALES ====================
    Route::prefix('facturacion/importar/sucursales')->group(function () {
        Route::get('/', [ImportarSucursalController::class, 'index'])->name('facturacion.importar.sucursales.index');
        Route::post('/', [ImportarSucursalController::class, 'store'])->name('facturacion.importar.sucursales.store');
        Route::get('/clientes', [ImportarSucursalController::class, 'clientes'])->name('facturacion.importar.sucursales.clientes');
        Route::get('/sucursales', [ImportarSucursalController::class, 'sucursales'])->name('facturacion.importar.sucursales.lista');
    });

    // ==================== FACTURACIÓN - PUNTOS DE VENTA ====================
    Route::prefix('facturacion/puntos-venta')->group(function () {
        Route::get('/', [PuntoVentaHomeController::class, 'index'])->name('facturacion.puntos-venta.home');
        Route::get('/crear', [PuntoVentaController::class, 'create'])->name('facturacion.puntos-venta.create');
        Route::post('/', [PuntoVentaController::class, 'store'])->name('facturacion.puntos-venta.store');
        Route::get('/sucursales', [PuntoVentaController::class, 'sucursales'])->name('facturacion.puntos-venta.sucursales');
    });

    // ==================== FACTURACIÓN - SIAT ====================
    Route::prefix('facturacion/siat')->group(function () {
        // CUIS
        Route::get('/cuis/vigente', [SiatCuisController::class, 'vigente'])->name('facturacion.siat.cuis.vigente');
        Route::post('/cuis/solicitar', [SiatCuisController::class, 'solicitar'])->name('facturacion.siat.cuis.solicitar');
        
        // CUFD
        Route::get('/cufd/vigente', [SiatCufdController::class, 'vigente'])->name('facturacion.siat.cufd.vigente');
        Route::post('/cufd/solicitar', [SiatCufdController::class, 'solicitar'])->name('facturacion.siat.cufd.solicitar');
        
        // Catálogos
        Route::get('/catalogos', [SiatCatalogoController::class, 'index'])->name('facturacion.siat.catalogos.index');
        Route::post('/catalogos/sync', [SiatCatalogoController::class, 'syncAll'])->name('facturacion.siat.catalogos.sync');
        Route::post('/catalogos/sync/{key}', [SiatCatalogoController::class, 'syncOne'])->name('facturacion.siat.catalogos.syncOne');
        Route::post('/catalogos/ping', [SiatCatalogoController::class, 'pingFechaHora'])->name('facturacion.siat.catalogos.ping');
        
        // Operaciones
        Route::get('/operaciones/cierre', [SiatOperacionesController::class, 'showCierre'])->name('facturacion.siat.operaciones.cierre');
        Route::post('/operaciones/cierre', [SiatOperacionesController::class, 'cierre'])->name('facturacion.siat.operaciones.cierre.post');
    });
    // 🔥 Ruta para verificar sesión (sin autenticación)
    Route::get('/check-session', function () {
        return response()->json([
            'has_session' => session()->has('operador_id') && session('operador_id') > 0
        ]);
    });

});