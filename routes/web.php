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
use App\Http\Controllers\Gestion\Contabilidad\IngresoController;
use App\Http\Controllers\Gestion\Todos\SucursalGestionController;
use App\Http\Controllers\Gestion\Menu\ReporteMenuController;
use App\Http\Controllers\Gestion\Todos\OperadorController;
use App\Http\Controllers\Gestion\Todos\OperadorSucursalController;
use App\Http\Controllers\Gestion\Todos\PerfilController;
use App\Http\Controllers\Gestion\Todos\FechaController;
use App\Http\Controllers\Gestion\Todos\CierreFechaController;
use App\Http\Controllers\Gestion\Inventario\ProductoVentaController;
use App\Http\Controllers\Gestion\Inventario\ProductoAprobacionConfigController;
use App\Http\Controllers\Gestion\Impuestos\LiquidacionVendedorController;
use App\Http\Controllers\Gestion\Reportes\ReporteVentasVendedorController;
use App\Http\Controllers\Gestion\Reportes\ReporteVentasSucursalController;
use App\Http\Controllers\Gestion\Reportes\ReporteListadoFacturasController;
use App\Http\Controllers\Gestion\Impuestos\AnularFacturaController;
use App\Http\Controllers\Gestion\Impuestos\MantenimientoMetodosPagoController;
use App\Http\Controllers\Gestion\Contabilidad\DiarioIngresoController;
use App\Http\Controllers\Gestion\Contabilidad\AdministradorDiarioController;
use App\Http\Controllers\Gestion\Contabilidad\ContaCuentaSucursalController;
use App\Http\Controllers\Gestion\Contabilidad\BalanceGeneralA3Controller;
use App\Http\Controllers\Gestion\Contabilidad\EstadoResultadosA3Controller;
use App\Http\Controllers\Gestion\Contabilidad\AnalisisCuentaController;
use App\Http\Controllers\Gestion\Contabilidad\ImprimirDiarioController;
use App\Http\Controllers\Gestion\Impuestos\AnularFacturaAdminController;
use App\Http\Controllers\Menu\MenuAdministradorController;
use App\Http\Controllers\Gestion\Configuracion\TemaClienteController;
use App\Http\Controllers\Gestion\Reportes\ListaPreciosController;
use App\Http\Controllers\Gestion\Reportes\MayorCuentaController;
use App\Http\Controllers\Operacion\Produccion\CronogramaController;
use App\Http\Controllers\Gestion\Reportes\ResultadosComparativoController;
use App\Http\Controllers\Gestion\Reportes\ReporteUnidadesVentasController;
use App\Http\Controllers\Gestion\Reportes\ReporteVentasSupervisorPorOperadorController;
use App\Http\Controllers\Operacion\Pedidos\PedidoController;
use App\Http\Controllers\Operacion\Pedidos\HoraLimiteController;
use App\Http\Controllers\Api\VentaTactilController;
use App\Http\Controllers\Gestion\Inventario\ProductoPrecioCostoController;
use App\Http\Controllers\Gestion\Inventario\InventarioPropiamenteController;
use App\Http\Controllers\Gestion\Inventario\InventarioFisicoController;
use App\Http\Controllers\Gestion\Inventario\AjusteInventarioFisicoController;
use App\Http\Controllers\Gestion\Impuestos\LiquidacionComisionistaController;
use App\Http\Controllers\Gestion\Reportes\ControlInterno\InformeSucursalController;
use App\Http\Controllers\Gestion\Reportes\ControlInterno\InformeSucursalEntreFechasController;
use App\Http\Controllers\Gestion\Reportes\ControlInterno\InformeSucursalOperadorComisionistasController;
use App\Http\Controllers\Gestion\Reportes\ControlInterno\ArqueoCajaBolivianosController;
use App\Http\Controllers\Gestion\Reportes\ControlInterno\ArqueoCajaBolivianosCIOperadorController;
use App\Http\Controllers\Gestion\Contabilidad\CuentaController;
use App\Http\Controllers\Gestion\Todos\FechaAuxiliarSucursalController;
use App\Http\Controllers\Gestion\Inventario\InventarioFisicoMantenimientoController;
use App\Http\Controllers\PuntoVenta\PdvBorrarLiquidacionController;
use App\Http\Controllers\Gestion\Inventario\ComboOpcionController;
use App\Http\Controllers\Gestion\Reportes\ControlInterno\ArqueoCajaChicaCIController;
use App\Http\Controllers\Gestion\Reportes\ControlInterno\InventarioDetalleController;
use App\Http\Controllers\Gestion\Reportes\ControlInterno\InventarioFisicoReimprimeController;

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
            return redirect()->route('oficial.index');
        }
        return redirect()->route('contexto.index');
    })->name('home');

    Route::get('/oficial', [OficialController::class, 'index'])->name('oficial.index');

    // ============================================================
    // 1. PREFIJO: CONTEXTO
    // ============================================================
    Route::prefix('contexto')->middleware(['evitar.contexto.duplicado'])->group(function () {
        Route::get('/', [ContextoController::class, 'index'])->name('contexto.index');
        Route::get('/sucursales/{empresa}', [ContextoController::class, 'sucursales'])->name('contexto.sucursales');
        Route::post('/', [ContextoController::class, 'store'])->name('contexto.store');
    });

    // ============================================================
    // 2. PREFIJO: CONTEXTO PDV
    // ============================================================
    Route::prefix('contexto/pdv')->middleware(['evitar.contexto.duplicado'])->group(function () {
        Route::get('/', [ContextoPdvController::class, 'index'])->name('contexto.pdv.index');
        Route::post('/', [ContextoPdvController::class, 'store'])->name('contexto.pdv.store');
        Route::get('/lista', [ContextoPdvController::class, 'lista'])->name('contexto.pdv.lista');
    });

    // ============================================================
    // 3. PREFIJO: GESTIÓN
    // ============================================================
    Route::prefix('gestion')->group(function () {

        // ---------- 3.1 CONFIGURACIÓN - TEMA ----------
        Route::prefix('configuracion/tema')->group(function () {
            Route::get('/', [TemaClienteController::class, 'index'])->name('gestion.configuracion.tema.index');
            Route::post('/{clienteId}', [TemaClienteController::class, 'store'])->name('gestion.configuracion.tema.store');
            Route::delete('/{clienteId}/reset', [TemaClienteController::class, 'reset'])->name('gestion.configuracion.tema.reset');
        });

        // ---------- 3.2 MENÚ ADMINISTRADOR ----------
        Route::prefix('menu-administrador')->group(function () {
            Route::get('/', [MenuAdministradorController::class, 'index'])->name('gestion.menu-administrador.index');
            Route::post('/', [MenuAdministradorController::class, 'store'])->name('gestion.menu-administrador.store');
            Route::put('/{id}', [MenuAdministradorController::class, 'update'])->name('gestion.menu-administrador.update');
            Route::delete('/{id}', [MenuAdministradorController::class, 'destroy'])->name('gestion.menu-administrador.destroy');
        });

        // ---------- 3.3 MENÚ ----------
        Route::prefix('menu')->group(function () {
            Route::prefix('asignar')->group(function () {
                Route::get('/', [AsignarMenuController::class, 'index'])->name('gestion.menu.asignar');
                Route::get('/{operadorId}', [AsignarMenuController::class, 'getAsignados'])->name('gestion.menu.asignar.get');
                Route::post('/', [AsignarMenuController::class, 'store'])->name('gestion.menu.asignar.store');
            });
            
            Route::prefix('reporte')->group(function () {
                Route::get('/', [ReporteMenuController::class, 'index'])->name('gestion.menu.reporte');
                Route::get('/arbol/{operadorId}', [ReporteMenuController::class, 'getArbol'])->name('gestion.menu.reporte.arbol');
            });
        });

        // ---------- 3.4 SUCURSALES ----------
        Route::prefix('sucursales')->group(function () {
            Route::get('/', [SucursalGestionController::class, 'index'])->name('sucursales-gestion.index');
            Route::get('/create', [SucursalGestionController::class, 'create'])->name('sucursales-gestion.create');
            Route::post('/', [SucursalGestionController::class, 'store'])->name('sucursales-gestion.store');
            // ❌ ESTA RUTA ESTÁ MAL - tiene /gestion/sucursales/ adicional
            // Route::get('/gestion/sucursales/siguiente-numero', [SucursalGestionController::class, 'obtenerSiguienteNumero'])->name('sucursales.siguiente-numero');
            
            // ✅ CORREGIR ASÍ:
            Route::get('/siguiente-numero', [SucursalGestionController::class, 'obtenerSiguienteNumero'])->name('sucursales.siguiente-numero');
            Route::get('/{id}/edit', [SucursalGestionController::class, 'edit'])->name('sucursales-gestion.edit');
            Route::put('/{id}', [SucursalGestionController::class, 'update'])->name('sucursales-gestion.update');    
        });

        // ---------- 3.5 TODOS ----------
        Route::prefix('todos')->group(function () {
            Route::prefix('identificador')->group(function () {
                Route::get('/', [IdentificadorController::class, 'index'])->name('gestion.todos.identificador.index');
                Route::post('/', [IdentificadorController::class, 'store'])->name('gestion.todos.identificador.store');
                Route::put('/{id}', [IdentificadorController::class, 'update'])->name('gestion.todos.identificador.update');
                Route::delete('/{id}', [IdentificadorController::class, 'destroy'])->name('gestion.todos.identificador.destroy');
            });

            Route::prefix('fecha-auxiliar-sucursal')->group(function () {
                Route::get('/', [FechaAuxiliarSucursalController::class, 'index'])->name('gestion.fecha-auxiliar-sucursal.index');
                Route::post('/', [FechaAuxiliarSucursalController::class, 'store'])->name('gestion.fecha-auxiliar-sucursal.store');
                Route::delete('/{id}', [FechaAuxiliarSucursalController::class, 'destroy'])->name('gestion.fecha-auxiliar-sucursal.destroy');
                Route::get('/fecha/{id}', [FechaAuxiliarSucursalController::class, 'getFecha'])->name('gestion.fecha-auxiliar-sucursal.get-fecha');
                Route::get('/sucursal/{id}', [FechaAuxiliarSucursalController::class, 'getSucursal'])->name('gestion.fecha-auxiliar-sucursal.get-sucursal');
            });
        });

        // ---------- 3.6 OPERADORES ----------
        Route::prefix('operadores')->group(function () {
            Route::get('/', [OperadorController::class, 'index'])->name('gestion.operadores.index');
            Route::post('/', [OperadorController::class, 'store'])->name('gestion.operadores.store');
            Route::put('/{id}', [OperadorController::class, 'update'])->name('gestion.operadores.update');
            Route::delete('/{id}', [OperadorController::class, 'destroy'])->name('gestion.operadores.destroy');
            Route::post('/{id}/activar', [OperadorController::class, 'activar'])->name('gestion.operadores.activar');
        });

        // ---------- 3.7 PERFIL ----------
        Route::prefix('perfil')->group(function () {
            Route::get('/', [PerfilController::class, 'edit'])->name('gestion.perfil.edit');
            Route::put('/', [PerfilController::class, 'update'])->name('gestion.perfil.update');
        });

        // ---------- 3.8 OPERADOR - SUCURSAL ----------
        Route::prefix('operador-sucursal')->group(function () {
            Route::get('/', [OperadorSucursalController::class, 'index'])->name('gestion.operador-sucursal.index');
            Route::post('/', [OperadorSucursalController::class, 'store'])->name('gestion.operador-sucursal.store');
            Route::put('/{id}', [OperadorSucursalController::class, 'update'])->name('gestion.operador-sucursal.update');
            Route::delete('/{id}', [OperadorSucursalController::class, 'destroy'])->name('gestion.operador-sucursal.destroy');
        });

        // ---------- 3.9 FECHAS ----------
        Route::prefix('fechas')->group(function () {
            Route::get('/', [FechaController::class, 'index'])->name('gestion.fechas.index');
            Route::post('/', [FechaController::class, 'store'])->name('gestion.fechas.store');
            Route::put('/{id}', [FechaController::class, 'update'])->name('gestion.fechas.update');
            Route::delete('/{id}', [FechaController::class, 'destroy'])->name('gestion.fechas.destroy');
        });

        // ---------- 3.10 CIERRE DE FECHAS ----------
        Route::prefix('cierre-fechas')->group(function () {
            Route::get('/', [CierreFechaController::class, 'index'])->name('gestion.cierre-fechas.index');
            Route::put('/{id}', [CierreFechaController::class, 'update'])->name('gestion.cierre-fechas.update');
            Route::post('/update-multiple', [CierreFechaController::class, 'updateMultiple'])->name('gestion.cierre-fechas.update-multiple');
        });

        // ============================================================
        // 3.11 CONTABILIDAD (TODOS LOS CONTROLLERS DE LA CARPETA Contabilidad)
        // ============================================================
        Route::prefix('contabilidad')->group(function () {
            
            // Cuentas (Listado)
            Route::prefix('cuentas')->group(function () {
                Route::get('/', [CuentaController::class, 'index'])->name('gestion.contabilidad.cuentas.index');
            });

            // Balance General A3
            Route::prefix('balance-general-a3')->group(function () {
                Route::get('/', [BalanceGeneralA3Controller::class, 'index'])->name('gestion.balance-general-a3.index');
                Route::get('/generar', [BalanceGeneralA3Controller::class, 'generar'])->name('gestion.balance-general-a3.generar');
            });

            // Estado de Resultados A3
            Route::prefix('estado-resultados-a3')->group(function () {
                Route::get('/', [EstadoResultadosA3Controller::class, 'index'])->name('gestion.estado-resultados-a3.index');
                Route::get('/generar', [EstadoResultadosA3Controller::class, 'generar'])->name('gestion.estado-resultados-a3.generar');
            });

            // Administrador de Diarios
            Route::prefix('administrador-diario')->group(function () {
                Route::get('/', [AdministradorDiarioController::class, 'index'])->name('gestion.administrador-diario.index');
                Route::post('/{id}/reabrir', [AdministradorDiarioController::class, 'reabrir'])->name('gestion.administrador-diario.reabrir');
            });

            // Análisis de Cuenta
            Route::prefix('analisis-cuenta')->group(function () {
                Route::get('/', [AnalisisCuentaController::class, 'index'])->name('gestion.analisis-cuenta.index');
                Route::post('/excel', [AnalisisCuentaController::class, 'generarExcel'])->name('gestion.analisis-cuenta.excel');
            });

            // Cuentas por Sucursal
            Route::prefix('conta-cuenta-sucursal')->group(function () {
                Route::get('/', [ContaCuentaSucursalController::class, 'index'])->name('gestion.conta-cuenta-sucursal.index');
                Route::post('/', [ContaCuentaSucursalController::class, 'store'])->name('gestion.conta-cuenta-sucursal.store');
                Route::put('/{id}', [ContaCuentaSucursalController::class, 'update'])->name('gestion.conta-cuenta-sucursal.update');
                Route::delete('/{id}', [ContaCuentaSucursalController::class, 'destroy'])->name('gestion.conta-cuenta-sucursal.destroy');
            });
            // Imprimir Diario
            Route::prefix('imprimir-diario')->group(function () {
                Route::get('/', [ImprimirDiarioController::class, 'index'])->name('gestion.imprimir-diario.index');
                Route::get('/buscar', [ImprimirDiarioController::class, 'buscar'])->name('gestion.imprimir-diario.buscar');
                Route::get('/operadores/{sucursalId}', [ImprimirDiarioController::class, 'getOperadoresPorSucursal'])->name('gestion.imprimir-diario.operadores');
                Route::get('/pdf/{id}', [ImprimirDiarioController::class, 'pdf'])->name('gestion.imprimir-diario.pdf');
                Route::get('/por-sucursal', [ImprimirDiarioController::class, 'porSucursal'])->name('gestion.imprimir-diario.por-sucursal');
                // ✅ RUTA QUE FALTA
                Route::get('/diarios-por-sucursal', [ImprimirDiarioController::class, 'getDiariosPorSucursal'])->name('gestion.imprimir-diario.diarios-por-sucursal');
            });

            // Diario de Ingresos
            Route::prefix('diario-ingreso')->group(function () {
                Route::get('/', [DiarioIngresoController::class, 'index'])->name('contabilidad.diario-ingreso.index');
                Route::get('/create', [DiarioIngresoController::class, 'create'])->name('contabilidad.diario-ingreso.create');
                Route::post('/', [DiarioIngresoController::class, 'store'])->name('contabilidad.diario-ingreso.store');
                Route::get('/{id}/edit', [DiarioIngresoController::class, 'edit'])->name('contabilidad.diario-ingreso.edit');
                Route::put('/{id}', [DiarioIngresoController::class, 'update'])->name('contabilidad.diario-ingreso.update');
                Route::post('/asiento', [DiarioIngresoController::class, 'storeAsiento'])->name('contabilidad.diario-ingreso.asiento.store');
                Route::put('/asiento/{id}', [DiarioIngresoController::class, 'updateAsiento'])->name('contabilidad.diario-ingreso.asiento.update');
                Route::delete('/asiento/{id}', [DiarioIngresoController::class, 'destroyAsiento'])->name('contabilidad.diario-ingreso.asiento.destroy');
                Route::post('/{id}/contabilizar', [DiarioIngresoController::class, 'contabilizar'])->name('contabilidad.diario-ingreso.contabilizar');
                Route::get('/{id}/pdf', [DiarioIngresoController::class, 'pdf'])->name('contabilidad.diario-ingreso.pdf');
            });
        });

        // ============================================================
        // 3.12 IMPUESTOS (TODOS LOS CONTROLLERS DE LA CARPETA Impuestos)
        // ============================================================
        Route::prefix('impuestos')->group(function () {
            
            // Lugar de Venta
            Route::prefix('lugar-venta')->group(function () {
                Route::get('/', [LugarVentaController::class, 'index'])->name('gestion.lugar-venta.index');
                Route::get('/create', [LugarVentaController::class, 'create'])->name('gestion.lugar-venta.create');
                Route::post('/', [LugarVentaController::class, 'store'])->name('gestion.lugar-venta.store');
                Route::get('/{id}/edit', [LugarVentaController::class, 'edit'])->name('gestion.lugar-venta.edit');
                Route::put('/{id}', [LugarVentaController::class, 'update'])->name('gestion.lugar-venta.update');
                Route::delete('/{id}', [LugarVentaController::class, 'destroy'])->name('gestion.lugar-venta.destroy');
                Route::get('/sucursales/{clienteId}', [LugarVentaController::class, 'getSucursales'])->name('gestion.lugar-venta.sucursales');
            });

            // Comisionista
            Route::prefix('comisionista')->group(function () {
                Route::get('/', [ComisionistaController::class, 'index'])->name('gestion.comisionista.index');
                Route::get('/create', [ComisionistaController::class, 'create'])->name('gestion.comisionista.create');
                Route::post('/', [ComisionistaController::class, 'store'])->name('gestion.comisionista.store');
                Route::get('/{id}/edit', [ComisionistaController::class, 'edit'])->name('gestion.comisionista.edit');
                Route::put('/{id}', [ComisionistaController::class, 'update'])->name('gestion.comisionista.update');
                Route::delete('/{id}', [ComisionistaController::class, 'destroy'])->name('gestion.comisionista.destroy');
                Route::get('/buscar-identificador', [ComisionistaController::class, 'buscarIdentificador'])->name('gestion.comisionista.buscar-identificador');
            });

            // Liquidación Concepto
            Route::prefix('liquidacion-concepto')->group(function () {
                Route::get('/', [LiquidacionConceptoController::class, 'index'])->name('gestion.impuestos.liquidacion-concepto.index');
                Route::post('/', [LiquidacionConceptoController::class, 'store'])->name('gestion.impuestos.liquidacion-concepto.store');
                Route::put('/{id}', [LiquidacionConceptoController::class, 'update'])->name('gestion.impuestos.liquidacion-concepto.update');
                Route::delete('/{id}', [LiquidacionConceptoController::class, 'destroy'])->name('gestion.impuestos.liquidacion-concepto.destroy');
                Route::post('/{id}/eliminar', [LiquidacionConceptoController::class, 'destroy'])->name('gestion.impuestos.liquidacion-concepto.eliminar');
            });

            // Liquidación Vendedor
            Route::prefix('liquidacion-vendedor')->group(function () {
                Route::get('/', [LiquidacionVendedorController::class, 'index'])->name('liquidacion-vendedor.index');
                Route::get('/datos/{fechaId}', [LiquidacionVendedorController::class, 'getDatos'])->name('liquidacion-vendedor.datos');
                Route::post('/guardar', [LiquidacionVendedorController::class, 'guardar'])->name('liquidacion-vendedor.guardar');
                Route::get('/mis-liquidaciones', [LiquidacionVendedorController::class, 'liquidacionesPorOperador'])->name('liquidacion-vendedor.mis-liquidaciones');
                Route::get('/reimprimir/{id}', [LiquidacionVendedorController::class, 'reimprimir'])->name('liquidacion-vendedor.reimprimir');
                Route::get('/pdf/{id}', [LiquidacionVendedorController::class, 'pdf'])->name('liquidacion-vendedor.pdf');
            });

            // Mantenimiento Métodos de Pago
            Route::prefix('mantenimiento-metodos-pago')->group(function () {
                Route::get('/', [MantenimientoMetodosPagoController::class, 'index'])->name('gestion.mantenimiento-metodos-pago.index');
                Route::get('/{idVenta}/metodos-pago', [MantenimientoMetodosPagoController::class, 'getMetodosPago'])->name('gestion.mantenimiento-metodos-pago.get');
                Route::put('/{idVenta}/metodos-pago', [MantenimientoMetodosPagoController::class, 'updateMetodosPago'])->name('gestion.mantenimiento-metodos-pago.update');
            });

            // Anular Factura (Normal)
            Route::prefix('anular-factura')->group(function () {
                Route::get('/', [AnularFacturaController::class, 'index'])->name('gestion.anular-factura.index');
                Route::post('/anular', [AnularFacturaController::class, 'anular'])->name('gestion.anular-factura.anular');
            });

            // Anular Factura (Admin)
            Route::prefix('anular-factura/admin')->group(function () {
                Route::get('/', [AnularFacturaAdminController::class, 'index'])->name('gestion.anular-factura.admin');
                Route::get('/operadores/{sucursalId}', [AnularFacturaAdminController::class, 'getOperadoresBySucursal'])->name('gestion.anular-factura.operadores');
                Route::get('/pdf/{id}', [AnularFacturaAdminController::class, 'pdf'])->name('gestion.anular-factura.pdf');
            });
        });

        // ============================================================
        // 3.13 INVENTARIO (TODOS LOS CONTROLLERS DE LA CARPETA Inventario)
        // ============================================================
        Route::prefix('inventario')->group(function () {
            
            // Catálogos (resource)
            Route::resource('producto-estado', ProductoEstadoController::class)->except(['show', 'create', 'edit']);
            Route::resource('producto-linea', ProductoLineaController::class)->except(['show', 'create', 'edit']);
            Route::resource('producto-grupo-analisis', ProductoGrupoAnalisisController::class)->except(['show', 'create', 'edit']);
            Route::resource('tipo-operacion', TipoOperacionController::class)->except(['show', 'create', 'edit']);
            Route::resource('unidad-medida', UnidadMedidaController::class)->except(['show', 'create', 'edit']);

            // Almacén
            Route::prefix('almacen')->group(function () {
                Route::get('/', [AlmacenController::class, 'index'])->name('gestion.inventario.almacen.index');
                Route::post('/', [AlmacenController::class, 'store'])->name('gestion.inventario.almacen.store');
                Route::put('/{id}', [AlmacenController::class, 'update'])->name('gestion.inventario.almacen.update');
                Route::delete('/{id}', [AlmacenController::class, 'destroy'])->name('gestion.inventario.almacen.destroy');
            });

            // Categorías Producto
            Route::prefix('categorias-producto')->group(function () {
                Route::get('/', [CategoriaProductoController::class, 'index'])->name('gestion.inventario.categorias-producto.index');
                Route::post('/', [CategoriaProductoController::class, 'store'])->name('gestion.inventario.categorias-producto.store');
                Route::put('/{id}', [CategoriaProductoController::class, 'update'])->name('gestion.inventario.categorias-producto.update');
                Route::delete('/{id}', [CategoriaProductoController::class, 'destroy'])->name('gestion.inventario.categorias-producto.destroy');
                Route::post('/reordenar', [CategoriaProductoController::class, 'reordenarTodo'])->name('gestion.inventario.categorias-producto.reordenar');
            });

            // Asignar Productos a Categorías
            Route::prefix('asignar-productos-categoria')->group(function () {
                Route::get('/', [AsignarProductoCategoriaController::class, 'index'])->name('gestion.inventario.asignar-productos-categoria.index');
                Route::post('/', [AsignarProductoCategoriaController::class, 'store'])->name('gestion.inventario.asignar-productos-categoria.store');
            });

            // Reporte de Inventario
            Route::prefix('reporte-inventario')->group(function () {
                Route::get('/', [ReporteInventarioController::class, 'index'])->name('gestion.inventario.reporte-inventario.index');
                Route::get('/sucursal-actual', [ReporteInventarioController::class, 'porSucursal'])->name('gestion.inventario.reporte-inventario.sucursal-actual');
            });

            // Ajustes de Inventario
            Route::prefix('ajustes')->group(function () {
                Route::get('/', [AjusteInventarioController::class, 'index'])->name('ajustes-inventario.index');
                Route::get('/create', [AjusteInventarioController::class, 'create'])->name('ajustes-inventario.create');
                Route::post('/crear', [AjusteInventarioController::class, 'crearAjuste'])->name('ajustes-inventario.crear');
                Route::get('/gestion-estado', [AjusteInventarioController::class, 'gestionEstado'])->name('ajustes-inventario.gestion-estado');
                Route::post('/{id}/cambiar-estado', [AjusteInventarioController::class, 'cambiarEstado'])->name('ajustes-inventario.cambiar-estado');
                Route::put('/cabecera/{id}', [AjusteInventarioController::class, 'guardarCabecera'])->name('ajustes-inventario.cabecera');
                Route::post('/contabilizar/{id}', [AjusteInventarioController::class, 'contabilizar'])->name('ajustes-inventario.contabilizar');
                Route::post('/detalle', [AjusteInventarioController::class, 'agregarDetalle'])->name('ajustes-inventario.agregar-detalle');
                Route::put('/detalle/{id}', [AjusteInventarioController::class, 'actualizarDetalle'])->name('ajustes-inventario.detalle.update');
                Route::delete('/detalle/{id}', [AjusteInventarioController::class, 'eliminarDetalle'])->name('ajustes-inventario.eliminar-detalle');
                Route::get('/{id}', [AjusteInventarioController::class, 'show'])->name('ajustes-inventario.show');
                Route::get('/{id}/edit', [AjusteInventarioController::class, 'edit'])->name('ajustes-inventario.edit');
                Route::get('/{id}/pdf', [AjusteInventarioController::class, 'pdf'])->name('ajustes-inventario.pdf');
                Route::put('/{id}', [AjusteInventarioController::class, 'update'])->name('ajustes-inventario.update');
            });

            // Precio Costo
            Route::prefix('precio-costo')->group(function () {
                Route::get('/', [ProductoPrecioCostoController::class, 'index'])->name('gestion.inventario.precio-costo.index');
                Route::get('/{id}/historial', [ProductoPrecioCostoController::class, 'historial'])->name('gestion.inventario.precio-costo.historial');
            });

            // Inventario Físico
            Route::prefix('inventario-fisico')->group(function () {
                Route::get('/', [InventarioFisicoController::class, 'create'])->name('gestion.inventario-fisico.index');
                Route::get('/{id}', function ($id) {
                    return redirect()->route('gestion.inventario-fisico.edit', $id);
                });
                Route::get('/{id}/edit', [InventarioFisicoController::class, 'edit'])->name('gestion.inventario-fisico.edit');
                Route::delete('/{id}', [InventarioFisicoController::class, 'destroy'])->name('gestion.inventario-fisico.destroy');
                Route::put('/{id}/cabecera', [InventarioFisicoController::class, 'updateCabecera']);
                Route::post('/{id}/sincronizar', [InventarioFisicoController::class, 'sincronizarProductos']);
                Route::put('/{id}/detalle/{detalleId}/unidades', [InventarioFisicoController::class, 'actualizarUnidades']);
                Route::post('/{id}/contabilizar', [InventarioFisicoController::class, 'contabilizar']);
                Route::get('/{id}/detalles', [InventarioFisicoController::class, 'getDetalles']);
                Route::get('/{id}/pdf', [InventarioFisicoController::class, 'pdf'])->name('gestion.inventario-fisico.pdf');
            });

            // Inventario Físico Mantenimiento
            Route::prefix('inventario-fisico-mantenimiento')->group(function () {
                Route::get('/', [InventarioFisicoMantenimientoController::class, 'index'])->name('gestion.inventario-fisico-mantenimiento.index');
                Route::put('/{id}/estado', [InventarioFisicoMantenimientoController::class, 'updateEstado'])->name('gestion.inventario-fisico-mantenimiento.estado');
            });

            // Productos Venta
            Route::prefix('productos-venta')->group(function () {
                Route::get('/', [ProductoVentaController::class, 'index'])->name('gestion.productos-venta.index');
                Route::get('/create', [ProductoVentaController::class, 'create'])->name('gestion.productos-venta.create');
                Route::post('/', [ProductoVentaController::class, 'store'])->name('gestion.productos-venta.store');
                Route::get('/{id}/edit', [ProductoVentaController::class, 'edit'])->name('gestion.productos-venta.edit');
                Route::put('/{id}', [ProductoVentaController::class, 'update'])->name('gestion.productos-venta.update');
                Route::post('/{id}/activar', [ProductoVentaController::class, 'activar']);
                Route::post('/{id}/desactivar', [ProductoVentaController::class, 'desactivar']);
                Route::delete('/{id}', [ProductoVentaController::class, 'destroy'])->name('gestion.productos-venta.destroy');
                Route::post('/precio-sucursal', [ProductoVentaController::class, 'storePrecioSucursal'])->name('gestion.productos-venta.precio-sucursal.store');
                Route::put('/precio-sucursal/{id}', [ProductoVentaController::class, 'updatePrecioSucursal'])->name('gestion.productos-venta.precio-sucursal.update');
                Route::delete('/precio-sucursal/{id}', [ProductoVentaController::class, 'destroyPrecioSucursal'])->name('gestion.productos-venta.precio-sucursal.destroy');
                Route::post('/precio-mayorista', [ProductoVentaController::class, 'storePrecioMayorista'])->name('gestion.productos-venta.precio-mayorista.store');
                Route::put('/precio-mayorista/{id}', [ProductoVentaController::class, 'updatePrecioMayorista'])->name('gestion.productos-venta.precio-mayorista.update');
                Route::delete('/precio-mayorista/{id}', [ProductoVentaController::class, 'destroyPrecioMayorista'])->name('gestion.productos-venta.precio-mayorista.destroy');
                Route::post('/detalle', [ProductoVentaController::class, 'storeDetalle'])->name('gestion.productos-venta.detalle.store');
                Route::put('/detalle/{id}', [ProductoVentaController::class, 'updateDetalle'])->name('gestion.productos-venta.detalle.update');
                Route::delete('/detalle/{id}', [ProductoVentaController::class, 'destroyDetalle'])->name('gestion.productos-venta.detalle.destroy');
                Route::get('/catalogo', [ProductoVentaController::class, 'catalogo'])->name('gestion.productos-venta.catalogo');
                Route::post('/{id}/enviar-aprobacion', [ProductoVentaController::class, 'enviarAprobacion'])->name('gestion.productos-venta.enviar-aprobacion');
            });

            // Productos Aprobación Config
            Route::prefix('productos-aprobacion')->group(function () {
                Route::get('/config', [ProductoAprobacionConfigController::class, 'index'])->name('gestion.productos-aprobacion.config');
                Route::post('/config', [ProductoAprobacionConfigController::class, 'store'])->name('gestion.productos-aprobacion.config.store');
                Route::delete('/config/{id}', [ProductoAprobacionConfigController::class, 'destroy'])->name('gestion.productos-aprobacion.config.destroy');
                Route::post('/config/{id}/toggle', [ProductoAprobacionConfigController::class, 'toggle'])->name('gestion.productos-aprobacion.config.toggle');
                Route::get('/pendientes', [ProductoVentaController::class, 'pendientesAprobacion'])->name('gestion.productos-aprobacion.pendientes');
                Route::post('/votar/{id}', [ProductoVentaController::class, 'votarAprobacion'])->name('gestion.productos-aprobacion.votar');
                Route::get('/ver/{id}', [ProductoVentaController::class, 'verAprobacion'])->name('gestion.productos-aprobacion.ver');
            });
        });

        // ============================================================
        // 3.14 COMPRAS
        // ============================================================
        Route::prefix('compras')->group(function () {
            Route::get('/', [CompraController::class, 'index'])->name('compras.index');
            Route::get('/create', [CompraController::class, 'create'])->name('compras.create');
            Route::get('/gestion-estado', [CompraController::class, 'gestionEstado'])->name('compras.gestion-estado');
            Route::post('/crear', [CompraController::class, 'crearCompra'])->name('compras.crear');
            Route::put('/actualizar-cabecera/{id}', [CompraController::class, 'actualizarCabecera'])->name('compras.actualizar-cabecera');
            Route::post('/agregar-detalle', [CompraController::class, 'agregarDetalle'])->name('compras.agregar-detalle');
            Route::delete('/eliminar-detalle/{id}', [CompraController::class, 'eliminarDetalle'])->name('compras.eliminar-detalle');
            Route::post('/contabilizar/{id}', [CompraController::class, 'contabilizar'])->name('compras.contabilizar');
            Route::post('/{id}/cambiar-estado', [CompraController::class, 'cambiarEstado'])->name('compras.cambiar-estado');
            Route::get('/{id}', [CompraController::class, 'show'])->name('compras.show');
            Route::get('/{id}/pdf', [CompraController::class, 'pdf'])->name('compras.pdf');
            Route::get('/{id}/edit', [CompraController::class, 'edit'])->name('compras.edit');
            Route::put('/actualizar-detalle/{id}', [CompraController::class, 'actualizarDetalle'])->name('compras.actualizar-detalle');
        });

        // ============================================================
        // 3.15 INGRESOS
        // ============================================================
        Route::prefix('ingresos')->group(function () {
            Route::get('/', [IngresoController::class, 'index'])->name('ingresos.index');
            Route::get('/create', [IngresoController::class, 'create'])->name('ingresos.create');
            Route::get('/gestion-estado', [IngresoController::class, 'gestionEstado'])->name('ingresos.gestion-estado');
            Route::post('/', [IngresoController::class, 'store'])->name('ingresos.store');
            Route::post('/{id}/cambiar-estado', [IngresoController::class, 'cambiarEstado'])->name('ingresos.cambiar-estado');
            Route::get('/{id}/edit', [IngresoController::class, 'edit'])->name('ingresos.edit');
            Route::put('/{id}', [IngresoController::class, 'update'])->name('ingresos.update');
            Route::get('/{id}/pdf', [IngresoController::class, 'pdf'])->name('ingresos.pdf');
            Route::get('/{id}', [IngresoController::class, 'show'])->name('ingresos.show');
        });

        // ============================================================
        // 3.16 EGRESOS
        // ============================================================
        Route::prefix('egresos')->group(function () {
            Route::get('/', [EgresoController::class, 'index'])->name('egresos.index');
            Route::get('/create', [EgresoController::class, 'create'])->name('egresos.create');
            Route::post('/', [EgresoController::class, 'store'])->name('egresos.store');
            Route::get('/{id}/edit', [EgresoController::class, 'edit'])->name('egresos.edit');
            Route::put('/{id}', [EgresoController::class, 'update'])->name('egresos.update');
            Route::get('/{id}/pdf', [EgresoController::class, 'pdf'])->name('egresos.pdf')->withoutMiddleware([\App\Http\Middleware\VerificarContexto::class]);
            Route::get('/gestion-estado', [EgresoController::class, 'gestionEstado'])->name('egresos.gestion-estado');
            Route::post('/{id}/cambiar-estado', [EgresoController::class, 'cambiarEstado'])->name('egresos.cambiar-estado');
        });

        // ============================================================
        // 3.17 REPORTES (TODOS LOS CONTROLLERS DE LA CARPETA Reportes)
        // ============================================================
        Route::prefix('reportes')->group(function () {
            
            // Ventas por Vendedor
            Route::prefix('ventas-vendedor')->group(function () {
                Route::get('/', [ReporteVentasVendedorController::class, 'index'])->name('reporte-ventas-vendedor.index');
                Route::get('/detalle-producto', [ReporteVentasVendedorController::class, 'getDetalleProducto'])->name('reporte-ventas-vendedor.detalle');
                Route::get('/export', [ReporteVentasVendedorController::class, 'export'])->name('reporte-ventas-vendedor.export');
            });

            // Ventas por Operador (Supervisor)
            Route::get('/ventas-por-operador', [ReporteVentasSupervisorPorOperadorController::class, 'index'])->name('gestion.reportes.ventas-por-operador');

            // Unidades Vendidas
            Route::prefix('unidades-ventas')->group(function () {
                Route::get('/', [ReporteUnidadesVentasController::class, 'index'])->name('gestion.reportes.unidades-ventas.index');
                Route::get('/data', [ReporteUnidadesVentasController::class, 'getData'])->name('gestion.reportes.unidades-ventas.data');
            });

            // Ventas por Sucursal
            Route::prefix('ventas-sucursal')->group(function () {
                Route::get('/', [ReporteVentasSucursalController::class, 'index'])->name('gestion.reporte-ventas-sucursal.index');
                Route::get('/detalle-producto', [ReporteVentasSucursalController::class, 'getDetalleProducto'])->name('gestion.reporte-ventas-sucursal.detalle-producto');
            });

            // Listado de Facturas
            Route::prefix('listado-facturas')->group(function () {
                Route::get('/', [ReporteListadoFacturasController::class, 'index'])->name('gestion.reporte-listado-facturas.index');
                Route::get('/reimprimir/{id}', [ReporteListadoFacturasController::class, 'reimprimir'])->name('gestion.reporte-listado-facturas.reimprimir');
            });

            // Lista de Precios
            Route::prefix('lista-precios')->group(function () {
                Route::get('/', [ListaPreciosController::class, 'index'])->name('gestion.reportes.lista-precios');
                Route::get('/exportar', [ListaPreciosController::class, 'exportar'])->name('gestion.reportes.lista-precios.exportar');
                Route::get('/sucursal', [ListaPreciosController::class, 'indexSucursal'])->name('gestion.reportes.lista-precios.sucursal');
                Route::get('/exportar-sucursal', [ListaPreciosController::class, 'exportarPorSucursal'])->name('gestion.reportes.lista-precios.exportar-sucursal');
            });

            // Mayor de Cuenta
            Route::prefix('mayor-cuenta')->group(function () {
                Route::get('/', [MayorCuentaController::class, 'index'])->name('gestion.reportes.mayor-cuenta.index');
                Route::get('/por-sucursal', [MayorCuentaController::class, 'porSucursal'])->name('gestion.reportes.mayor-cuenta.por-sucursal');
                // ✅ GET para compatibilidad con la vista original
                Route::get('/exportar', [MayorCuentaController::class, 'exportar'])->name('gestion.reportes.mayor-cuenta.exportar');
                // ✅ POST para la vista con selector de sucursal
                Route::post('/exportar-por-sucursal', [MayorCuentaController::class, 'exportarPorSucursal'])->name('gestion.reportes.mayor-cuenta.exportar-por-sucursal');
            });

            // Resultados Comparativo
            Route::prefix('resultados-comparativo')->group(function () {
                Route::get('/', [ResultadosComparativoController::class, 'index'])->name('gestion.reportes.resultados-comparativo.index');
                Route::get('/exportar', [ResultadosComparativoController::class, 'exportar'])->name('gestion.reportes.resultados-comparativo.exportar');
            });

            // ---------- CONTROL INTERNO ----------
            Route::prefix('control-interno')->group(function () {
                // Informe Sucursal
                Route::prefix('informe-sucursal')->group(function () {
                    Route::get('/', [InformeSucursalController::class, 'index'])->name('gestion.reportes.control-interno.informe-sucursal');
                    Route::get('/exportar', [InformeSucursalController::class, 'exportar'])->name('gestion.reportes.control-interno.informe-sucursal.exportar');
                });

                // Informe Sucursal Entre Fechas
                Route::prefix('informe-sucursal-entre-fechas')->group(function () {
                    Route::get('/', [InformeSucursalEntreFechasController::class, 'index'])->name('gestion.reportes.control-interno.informe-sucursal-entre-fechas');
                    Route::get('/exportar', [InformeSucursalEntreFechasController::class, 'exportar'])->name('gestion.reportes.control-interno.informe-sucursal-entre-fechas.exportar');
                });

                // Informe Sucursal Operador Comisionistas
                Route::prefix('informe-sucursal-operador-comisionistas')->group(function () {
                    Route::get('/', [InformeSucursalOperadorComisionistasController::class, 'index'])->name('gestion.reportes.control-interno.informe-sucursal-operador-comisionistas');
                    Route::get('/exportar', [InformeSucursalOperadorComisionistasController::class, 'exportar'])->name('gestion.reportes.control-interno.informe-sucursal-operador-comisionistas.exportar');
                });

                // Arqueo Caja Bolivianos
                Route::prefix('arqueo-caja-bolivianos')->group(function () {
                    Route::get('/', [ArqueoCajaBolivianosController::class, 'index'])->name('gestion.reportes.control-interno.arqueo-caja-bolivianos');
                    Route::get('/pdf', [ArqueoCajaBolivianosController::class, 'generarPdf'])->name('gestion.reportes.control-interno.arqueo-caja-bolivianos.pdf');
                });

                // Arqueo Caja Bolivianos por Operador
                Route::prefix('arqueo-caja-bolivianos-ci-operador')->group(function () {
                    Route::get('/', [ArqueoCajaBolivianosCIOperadorController::class, 'index'])->name('gestion.reportes.control-interno.arqueo-caja-bolivianos-ci-operador');
                    Route::get('/pdf', [ArqueoCajaBolivianosCIOperadorController::class, 'generarPdf'])->name('gestion.reportes.control-interno.arqueo-caja-bolivianos-ci-operador.pdf');
                });

                // Arqueo Caja Chica por Operador
                Route::prefix('arqueo-caja-chica-ci')->group(function () {
                    Route::get('/', [ArqueoCajaChicaCIController::class, 'index'])->name('gestion.reportes.control-interno.arqueo-caja-chica-ci');
                    Route::get('/pdf', [ArqueoCajaChicaCIController::class, 'generarPdf'])->name('gestion.reportes.control-interno.arqueo-caja-chica-ci.pdf');
                });

                // Inventario Detallado
                Route::prefix('inventario-detalle')->group(function () {
                    Route::get('/', [InventarioDetalleController::class, 'index'])->name('gestion.reportes.control-interno.inventario-detalle');
                    Route::get('/movimientos', [InventarioDetalleController::class, 'getMovimientos'])->name('gestion.reportes.control-interno.inventario-detalle.movimientos');
                });

                // Reimpresión Inventario Físico
                Route::prefix('inventario-fisico-reimprime')->group(function () {
                    Route::get('/', [InventarioFisicoReimprimeController::class, 'index'])->name('gestion.reportes.control-interno.inventario-fisico-reimprime');
                    Route::get('/correlativos', [InventarioFisicoReimprimeController::class, 'getCorrelativos'])->name('gestion.reportes.control-interno.inventario-fisico-reimprime.correlativos');
                    Route::get('/pdf', [InventarioFisicoReimprimeController::class, 'generarPdf'])->name('gestion.reportes.control-interno.inventario-fisico-reimprime.pdf');
                });
            });
        });
    });

    // ============================================================
    // 4. PREFIJO: VENTA FACTURA
    // ============================================================
    Route::prefix('venta-factura')->group(function () {
        Route::get('/crear', [NuevaVentaController::class, 'create'])->name('ventas.crear');
        Route::post('/store', [NuevaVentaController::class, 'store'])->name('ventas.store');
        Route::get('/nueva', [FormularioVentaController::class, 'create'])->name('ventas.formulario');
        Route::post('/guardar', [FormularioVentaController::class, 'store'])->name('ventas.guardar');
        Route::get('/pago', [PagoVentaController::class, 'create'])->name('ventas.pago');
        Route::post('/buscar-cliente', [PagoVentaController::class, 'buscarCliente'])->name('ventas.buscar-cliente');
        Route::post('/procesar-pago', [PagoVentaController::class, 'store'])->name('ventas.procesar-pago');
        Route::get('/factura-pdf/{id}', [PagoVentaController::class, 'facturaPdf'])->name('ventas.factura-pdf');
    });

    // ============================================================
    // 5. PREFIJO: VENTA TÁCTIL
    // ============================================================
    Route::prefix('venta-tactil')->group(function () {
        Route::get('/nueva', [NuevaVentaTactilController::class, 'create'])->name('venta-tactil.nueva');
        Route::post('/nueva', [NuevaVentaTactilController::class, 'store'])->name('venta-tactil.nueva.store');
        Route::get('/', [MenuTactilController::class, 'index'])->name('venta-tactil.index');
        Route::get('/categoria/{id}', [MenuTactilController::class, 'verCategoria'])->name('venta-tactil.categoria');
        Route::get('/carrito', [CarritoTactilController::class, 'index'])->name('venta-tactil.carrito');
        Route::get('/pago', [PagoVentaController::class, 'createTactil'])->name('venta-tactil.pago');
    });

    // ============================================================
    // 6. PREFIJO: API
    // ============================================================
    Route::prefix('api')->group(function () {
        // Venta Táctil
        Route::prefix('venta-tactil')->group(function () {
            Route::get('/carrito', [VentaTactilController::class, 'getCarrito']);
            Route::post('/agregar', [VentaTactilController::class, 'agregarProducto']);
            Route::post('/agregar-combo', [VentaTactilController::class, 'agregarCombo']);
            Route::put('/carrito/{itemId}', [VentaTactilController::class, 'actualizarCantidad']);
            Route::delete('/carrito/{itemId}', [VentaTactilController::class, 'eliminarProducto']);
            Route::delete('/cancelar', [VentaTactilController::class, 'cancelarVenta']);
        });

        // Venta (Normal)
        Route::prefix('venta')->group(function () {
            Route::get('/grupos', [FormularioVentaController::class, 'getGrupos']);
            Route::get('/productos/{idVentaGrupo}', [FormularioVentaController::class, 'getProductos']);
            Route::get('/precio-producto/{idProducto}', [FormularioVentaController::class, 'getPrecioProducto']);
            Route::get('/mapeos-metodos-pago', function () {
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
        });

        // Catálogos (Facturación)
        Route::prefix('catalogos')->group(function () {
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

            Route::get('/metodos-pago', [PagoVentaController::class, 'getMetodosPago']);
        });

        // Almacenes por Sucursal
        Route::get('/almacenes-por-sucursal/{sucursalId}', [InventarioFisicoController::class, 'getAlmacenes'])->name('api.almacenes.por-sucursal');
    });

    // ============================================================
    // 7. PREFIJO: FACTURACIÓN
    // ============================================================
    Route::prefix('facturacion')->group(function () {
        // Métodos de Pago - Mapeo
        Route::prefix('metodos-pago/mapeo')->group(function () {
            Route::get('/', [MetodoPagoMapeoController::class, 'index'])->name('facturacion.metodos-pago.mapeo');
            Route::post('/', [MetodoPagoMapeoController::class, 'store'])->name('facturacion.metodos-pago.mapeo.store');
        });

        // Empresas
        Route::prefix('empresas')->group(function () {
            Route::get('/', [EmpresasHomeController::class, 'index'])->name('facturacion.empresas.home');
            Route::get('/crear', [EmpresaController::class, 'create'])->name('facturacion.empresas.create');
            Route::post('/crear', [EmpresaController::class, 'store'])->name('facturacion.empresas.store');
            Route::get('/importar', [ImportarEmpresaController::class, 'index'])->name('facturacion.empresas.importar');
            Route::post('/importar', [ImportarEmpresaController::class, 'store'])->name('facturacion.empresas.importar.store');
            Route::get('/clientes', [ImportarEmpresaController::class, 'clientes'])->name('facturacion.empresas.clientes');
            Route::get('/ultimo-id-fecha', [EmpresaController::class, 'ultimoIdFecha'])->name('facturacion.empresas.ultimo-id-fecha');
        });

        // Sucursales
        Route::prefix('sucursales')->group(function () {
            Route::get('/', [SucursalesHomeController::class, 'index'])->name('facturacion.sucursales.home');
            Route::get('/create', [SucursalController::class, 'create'])->name('facturacion.sucursales.create');
            Route::post('/', [SucursalController::class, 'store'])->name('facturacion.sucursales.store');
            Route::get('/clientes', [SucursalController::class, 'clientes'])->name('facturacion.sucursales.clientes');
            Route::get('/plazas', [SucursalController::class, 'plazas'])->name('facturacion.sucursales.plazas');
            Route::get('/empresa-por-cliente', [SucursalController::class, 'empresaPorCliente'])->name('facturacion.sucursales.empresaPorCliente');
        });

        // Importar Sucursales
        Route::prefix('importar/sucursales')->group(function () {
            Route::get('/', [ImportarSucursalController::class, 'index'])->name('facturacion.importar.sucursales.index');
            Route::post('/', [ImportarSucursalController::class, 'store'])->name('facturacion.importar.sucursales.store');
            Route::get('/clientes', [ImportarSucursalController::class, 'clientes'])->name('facturacion.importar.sucursales.clientes');
            Route::get('/sucursales', [ImportarSucursalController::class, 'sucursales'])->name('facturacion.importar.sucursales.lista');
        });

        // Puntos de Venta
        Route::prefix('puntos-venta')->group(function () {
            Route::get('/', [PuntoVentaHomeController::class, 'index'])->name('facturacion.puntos-venta.home');
            Route::get('/crear', [PuntoVentaController::class, 'create'])->name('facturacion.puntos-venta.create');
            Route::post('/', [PuntoVentaController::class, 'store'])->name('facturacion.puntos-venta.store');
            Route::get('/sucursales', [PuntoVentaController::class, 'sucursales'])->name('facturacion.puntos-venta.sucursales');
        });

        // SIAT
        Route::prefix('siat')->group(function () {
            // CUIS
            Route::prefix('cuis')->group(function () {
                Route::get('/vigente', [SiatCuisController::class, 'vigente'])->name('facturacion.siat.cuis.vigente');
                Route::post('/solicitar', [SiatCuisController::class, 'solicitar'])->name('facturacion.siat.cuis.solicitar');
            });

            // CUFD
            Route::prefix('cufd')->group(function () {
                Route::get('/vigente', [SiatCufdController::class, 'vigente'])->name('facturacion.siat.cufd.vigente');
                Route::post('/solicitar', [SiatCufdController::class, 'solicitar'])->name('facturacion.siat.cufd.solicitar');
            });

            // Catálogos
            Route::prefix('catalogos')->group(function () {
                Route::get('/', [SiatCatalogoController::class, 'index'])->name('facturacion.siat.catalogos.index');
                Route::post('/sync', [SiatCatalogoController::class, 'syncAll'])->name('facturacion.siat.catalogos.sync');
                Route::post('/sync/{key}', [SiatCatalogoController::class, 'syncOne'])->name('facturacion.siat.catalogos.syncOne');
                Route::post('/ping', [SiatCatalogoController::class, 'pingFechaHora'])->name('facturacion.siat.catalogos.ping');
            });

            // Operaciones
            Route::prefix('operaciones')->group(function () {
                Route::get('/cierre', [SiatOperacionesController::class, 'showCierre'])->name('facturacion.siat.operaciones.cierre');
                Route::post('/cierre', [SiatOperacionesController::class, 'cierre'])->name('facturacion.siat.operaciones.cierre.post');
            });
        });
    });

    // ============================================================
    // 8. PREFIJO: PDV (PUNTO DE VENTA)
    // ============================================================
    Route::prefix('pdv')->group(function () {
        // Borrar Liquidación
        Route::prefix('borrar-liquidacion')->group(function () {
            Route::get('/', [PdvBorrarLiquidacionController::class, 'index'])->name('pdv.borrar-liquidacion.index');
            Route::get('/liquidaciones', [PdvBorrarLiquidacionController::class, 'getLiquidaciones'])->name('pdv.borrar-liquidacion.liquidaciones');
            Route::post('/eliminar', [PdvBorrarLiquidacionController::class, 'eliminar'])->name('pdv.borrar-liquidacion.eliminar');
        });
    });

    // ============================================================
    // 9. PREFIJO: COMBO OPCIONES
    // ============================================================
    Route::prefix('combo-opciones')->group(function () {
        Route::get('/{idProductoCombo}/composicion', [ComboOpcionController::class, 'getComposicion']);
        Route::get('/{idProductoCombo}', [ComboOpcionController::class, 'index']);
        Route::get('/productos/disponibles', [ComboOpcionController::class, 'getProductosDisponibles']);
        Route::post('/', [ComboOpcionController::class, 'store']);
        Route::put('/{id}', [ComboOpcionController::class, 'update']);
        Route::delete('/{id}', [ComboOpcionController::class, 'destroy']);
    });

    // ============================================================
    // 10. PREFIJO: OPERACIÓN
    // ============================================================
    Route::prefix('operacion')->group(function () {
        // Producción
        Route::prefix('produccion')->middleware(['contexto.requerido'])->group(function () {
            Route::prefix('cronograma')->group(function () {
                Route::get('/', [CronogramaController::class, 'index'])->name('operacion.produccion.cronograma.index');
                Route::get('/ver', [CronogramaController::class, 'show'])->name('operacion.produccion.cronograma.show');
                Route::post('/', [CronogramaController::class, 'store'])->name('operacion.produccion.cronograma.store');
                Route::delete('/{id}', [CronogramaController::class, 'destroy'])->name('operacion.produccion.cronograma.destroy');
            });
        });

        // Pedidos
        Route::prefix('pedidos')->group(function () {
            // Hora Límite
            Route::prefix('hora-limite')->group(function () {
                Route::get('/', [HoraLimiteController::class, 'index'])->name('operacion.pedidos.hora-limite.index');
                Route::post('/', [HoraLimiteController::class, 'store'])->name('operacion.pedidos.hora-limite.store');
                Route::put('/{id}', [HoraLimiteController::class, 'update'])->name('operacion.pedidos.hora-limite.update');
                Route::delete('/{id}', [HoraLimiteController::class, 'destroy'])->name('operacion.pedidos.hora-limite.destroy');
            });

            // Pedido
            Route::prefix('pedido')->group(function () {
                Route::get('/', [PedidoController::class, 'index'])->name('operacion.pedidos.pedido.index');
                Route::post('/', [PedidoController::class, 'store'])->name('operacion.pedidos.pedido.store');
                Route::delete('/{id}', [PedidoController::class, 'destroy'])->name('operacion.pedidos.pedido.destroy');
                Route::post('/api/validar-producto', [PedidoController::class, 'apiValidarProducto']);
                Route::post('/api/validar-hora-limite', [PedidoController::class, 'apiValidarHoraLimite']);
                Route::get('/api/fecha-hora', [PedidoController::class, 'apiGetFechaHora']);
            });
        });
    });

    // ============================================================
    // 11. UTILIDADES
    // ============================================================
    // Verificar sesión
    Route::get('/check-session', function () {
        return response()->json([
            'has_session' => session()->has('operador_id') && session('operador_id') > 0
        ]);
    });

    // API para movimientos de inventario
    Route::get('/inventario/reporte-movimientos', [ReporteInventarioController::class, 'getMovimientos']);

}); // Fin de rutas protegidas