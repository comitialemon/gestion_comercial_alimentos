<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Services\TimezoneService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class VentaReprocesarRangoController extends Controller
{
    protected $timezoneService;

    public function __construct(TimezoneService $timezoneService)
    {
        $this->timezoneService = $timezoneService;
    }

    /**
     * 🔥 OBTENER FECHA ACTUAL DEL CLIENTE
     */
    private function getFechaCliente()
    {
        return $this->timezoneService->getFechaActual();
    }

    /**
     * 📋 Formulario para reprocesar rango de facturas
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // 🔥 Obtener todas las sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal']);
        
        // 🔥 Si hay una sucursal seleccionada, obtener las facturas
        $facturas = [];
        $sucursalSeleccionada = null;
        
        if ($request->filled('sucursal_id')) {
            $sucursalSeleccionada = $request->sucursal_id;
            
            $facturas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $sucursalSeleccionada)
                ->where('ActivoInactivo', 1)
                ->orderBy('NumeroFactura', 'ASC')
                ->get(['IdVentas as id', 'NumeroFactura as numero']);
        }
        
        return Inertia::render('Gestion/Impuestos/VentaReprocesarRango/Index', [
            'sucursales' => $sucursales,
            'facturas' => $facturas,
            'sucursalSeleccionada' => $sucursalSeleccionada,
            'filtros' => [
                'sucursal_id' => $request->sucursal_id,
                'factura_inicial_id' => $request->factura_inicial_id,
                'factura_final_id' => $request->factura_final_id,
            ],
        ]);
    }
    
    /**
     * 🔥 API: Obtener facturas por sucursal (para el autocomplete)
     */
    public function getFacturasPorSucursal(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = $request->sucursal_id;
        
        if (!$sucursalId) {
            return response()->json([]);
        }
        
        $facturas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->orderBy('NumeroFactura', 'ASC')
            ->get(['IdVentas as id', 'NumeroFactura as numero']);
        
        return response()->json($facturas);
    }

    /**
     * 🔥 PROCESAR RANGO DE FACTURAS (Reprocesar inventario)
     * ✅ CORREGIDO: Lógica de combos con personalización + zona horaria
     */
    public function procesarRango(Request $request)
    {
        try {
            $clienteId = session('cliente_id');
            $operadorId = session('operador_id');
            
            $request->validate([
                'sucursal_id' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
                'factura_inicial_id' => 'required|integer',
                'factura_final_id' => 'required|integer',
            ]);
            
            $facturaInicialId = $request->factura_inicial_id;
            $facturaFinalId = $request->factura_final_id;
            $sucursalId = $request->sucursal_id;
            
            if ($facturaInicialId > $facturaFinalId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El número de factura inicial debe ser menor o igual que el número de factura final.'
                ], 422);
            }
            
            \Log::info('=== REPROCESANDO RANGO DE FACTURAS ===');
            \Log::info('Sucursal ID: ' . $sucursalId);
            \Log::info('Factura inicial ID: ' . $facturaInicialId);
            \Log::info('Factura final ID: ' . $facturaFinalId);
            
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            $facturas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $sucursalId)
                ->whereBetween('IdVentas', [$facturaInicialId, $facturaFinalId])
                ->where('ActivoInactivo', 1)
                ->orderBy('NumeroFactura', 'ASC')
                ->get(['IdVentas', 'NumeroFactura', 'IdEstado', 'IdOperadorIngresa', 'FechaVenta', 'IdNIT']);
            
            if ($facturas->isEmpty()) {
                throw new \Exception('No se encontraron facturas en el rango seleccionado');
            }
            
            \Log::info('📦 Facturas encontradas: ' . $facturas->count());
            
            $totalProcesadas = 0;
            $facturasProcesadas = [];
            $errores = [];
            $todosLosProductos = [];
            $totalMovimientos = 0;
            
            // 🔥 OBTENER ID DEL TIPO DE OPERACIÓN "VENTAS"
            $idTipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdCliente', $clienteId)
                ->where('Detalle', 'Ventas')
                ->where('ActivoInactivo', 0)
                ->value('IdTipoOperacion');

            if (!$idTipoOperacion) {
                $idTipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_tipooperacion')
                    ->where('IdCliente', $clienteId)
                    ->where(DB::raw('UPPER(Detalle)'), 'VENTAS')
                    ->where('ActivoInactivo', 0)
                    ->value('IdTipoOperacion');
            }

            if (!$idTipoOperacion) {
                throw new \Exception('No se encontró el tipo de operación "Ventas" para este cliente');
            }
            
            foreach ($facturas as $factura) {
                try {
                    $idVentas = $factura->IdVentas;
                    $numeroFactura = $factura->NumeroFactura;
                    $idEstado = $factura->IdEstado;
                    $idOperadorIngresa = $factura->IdOperadorIngresa;
                    
                    \Log::info('Procesando factura: ' . $idVentas . ' - N° ' . $numeroFactura);
                    
                    // Obtener nombre del operador
                    $operador = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('todos_operador as o')
                        ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                        ->where('o.IdOperador', $idOperadorIngresa)
                        ->first();
                    
                    $nombreOperador = $operador ? $operador->Nombre : 'Desconocido';
                    
                    // 🔥 ELIMINAR movimientos anteriores de esta factura
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_propiamente')
                        ->where('IdTipoDeOperacion', $idTipoOperacion)
                        ->where('IdDocumento', $idVentas)
                        ->delete();
                    
                    // 🔥 OBTENER FECHA CORRECTA DEL CLIENTE (usar la fecha de la venta, pero corregir si es necesario)
                    $fechaVentaOriginal = date('Y-m-d', strtotime($factura->FechaVenta));
                    $fechaCliente = $this->getFechaCliente();
                    
                    // Si la fecha de la venta es diferente a la fecha actual, usar la fecha de la venta (pero advertir)
                    $fechaVenta = $fechaVentaOriginal;
                    if ($fechaVentaOriginal !== $fechaCliente) {
                        \Log::warning('⚠️ La fecha de la venta (' . $fechaVentaOriginal . ') es diferente a la fecha actual del cliente (' . $fechaCliente . '). Se usará la fecha de la venta.');
                        $fechaVenta = $fechaVentaOriginal;
                    }
                    
                    $idFecha = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('todos_fecha')
                        ->where('Fecha', $fechaVenta)
                        ->value('IdFecha');
                    
                    if (!$idFecha) {
                        $idFecha = DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('todos_fecha')
                            ->insertGetId([
                                'Fecha' => $fechaVenta,
                                'ActivoInactivo' => 1,
                                'CierreSucursal' => 0,
                                'CierrePermanente' => 0,
                            ]);
                    }
                    
                    // Obtener almacén
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
                    
                    // Determinar Factura/Recibo
                    $reciboFactura = "Factura";
                    
                    $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('todos_cliente_sucursal')
                        ->where('IdClienteSucursal', $sucursalId)
                        ->first();
                    
                    $nitCero = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('todos_identificador')
                        ->where('CI_NIT', '99')
                        ->value('IdIdentificador');
                    
                    if ($sucursal && $sucursal->ActivaInactivaR == 0 && $factura->IdNIT == $nitCero) {
                        $reciboFactura = "Recibo";
                    }
                    
                    // Procesar detalles - 🔥 CON LÓGICA CORREGIDA DE COMBOS
                    $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_detalle')
                        ->where('idventas', $idVentas)
                        ->get();
                    
                    $movimientosFactura = 0;
                    
                    foreach ($detalles as $detalle) {
                        // 🔥 VERIFICAR SI TIENE PERSONALIZACIÓN
                        if ($detalle->personalizacion && $detalle->personalizacion != 'null') {
                            $personalizaciones = json_decode($detalle->personalizacion, true);
                            
                            \Log::info('Reprocesando combo con personalización', [
                                'id_combo' => $detalle->idrelacionventainventario,
                                'personalizaciones' => $personalizaciones
                            ]);
                            
                            // Obtener composición original
                            $composicionOriginal = DB::connection('mysql_gestion_comercial_alimentos')
                                ->table('inventario_relacion_ventainventario_detalle')
                                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                                ->get()
                                ->keyBy('IdProducto');
                            
                            // ✅ CANASTA ACUMULADORA PARA ESTE COMBO
                            $productosADescontar = [];
                            
                            // Procesar cada combo individual
                            foreach ($personalizaciones as $index => $personalizacion) {
                                $sustitutos = $personalizacion['sustitutos'] ?? [];
                                
                                foreach ($composicionOriginal as $idProductoOriginal => $composicion) {
                                    $cantidadOriginal = (float) $composicion->Porcion;
                                    
                                    // Calcular cuánto se reemplaza
                                    $totalReemplazado = 0;
                                    foreach ($sustitutos as $sustituto) {
                                        if ($sustituto['id_producto_original'] == $idProductoOriginal) {
                                            $totalReemplazado += $sustituto['cantidad'] ?? 0;
                                        }
                                    }
                                    
                                    // ACUMULAR PRODUCTOS ORIGINALES (los que NO se reemplazaron)
                                    $quedanOriginales = $cantidadOriginal - $totalReemplazado;
                                    if ($quedanOriginales > 0) {
                                        if (!isset($productosADescontar[$idProductoOriginal])) {
                                            $productosADescontar[$idProductoOriginal] = 0;
                                        }
                                        $productosADescontar[$idProductoOriginal] += $quedanOriginales;
                                    }
                                    
                                    // ACUMULAR SUSTITUTOS (los que se agregaron)
                                    foreach ($sustitutos as $sustituto) {
                                        if ($sustituto['id_producto_original'] == $idProductoOriginal) {
                                            $idSustituto = $sustituto['id_producto_sustituto'];
                                            $cantidadSustituto = $sustituto['cantidad'] ?? 0;
                                            
                                            if ($cantidadSustituto > 0) {
                                                if (!isset($productosADescontar[$idSustituto])) {
                                                    $productosADescontar[$idSustituto] = 0;
                                                }
                                                $productosADescontar[$idSustituto] += $cantidadSustituto;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            // 🔥 REGISTRAR TODOS LOS PRODUCTOS ACUMULADOS
                            foreach ($productosADescontar as $idProducto => $cantidadTotal) {
                                if ($cantidadTotal <= 0) continue;
                                
                                // Obtener nombre del producto
                                $nombreProducto = DB::connection('mysql_gestion_comercial_alimentos')
                                    ->table('inventario_productodetalle')
                                    ->where('IdProducto', $idProducto)
                                    ->value('Descripcion');
                                
                                // Agrupar para el resumen
                                $key = $nombreProducto ?? 'Producto #' . $idProducto;
                                if (!isset($todosLosProductos[$key])) {
                                    $todosLosProductos[$key] = [
                                        'nombre' => $key,
                                        'cantidad' => 0,
                                        'facturas' => []
                                    ];
                                }
                                $todosLosProductos[$key]['cantidad'] += $cantidadTotal;
                                if (!in_array($numeroFactura, $todosLosProductos[$key]['facturas'])) {
                                    $todosLosProductos[$key]['facturas'][] = $numeroFactura;
                                }
                                
                                // Obtener precio costo
                                $precioCosto = DB::connection('mysql_gestion_comercial_alimentos')
                                    ->table('inventario_productodetalle_precio_costo')
                                    ->where('IdProducto', $idProducto)
                                    ->orderBy('IdPrecioCosto', 'DESC')
                                    ->value('PrecioCosto');
                                
                                $costoTotal = $cantidadTotal * ($precioCosto ?? 0);
                                
                                if ($idEstado == 1) {
                                    DB::connection('mysql_gestion_comercial_alimentos')
                                        ->table('inventario_propiamente')
                                        ->insert([
                                            'IdTipoDeOperacion' => $idTipoOperacion,
                                            'IdDocumento' => $idVentas,
                                            'IdFecha' => $idFecha,
                                            'IdAlmacen' => $idAlmacen,
                                            'IdProducto' => $idProducto,
                                            'Glosa' => "{$reciboFactura} Ventas No {$numeroFactura}; Op.{$nombreOperador}",
                                            'D_H' => 'H',
                                            'Unidades' => $cantidadTotal,
                                            'Bolivianos' => $costoTotal,
                                            'IdCliente' => $clienteId,
                                            'IdSucursal' => $sucursalId,
                                        ]);
                                    
                                    $movimientosFactura++;
                                    $totalMovimientos++;
                                }
                            }
                            
                        } else {
                            // 🔥 SIN PERSONALIZACIÓN - usar la composición normal
                            $productosPorcion = DB::connection('mysql_gestion_comercial_alimentos')
                                ->table('inventario_relacion_ventainventario_detalle')
                                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                                ->get();
                            
                            foreach ($productosPorcion as $porcion) {
                                $cantidadDescontar = $porcion->Porcion * $detalle->unidades;
                                
                                // Obtener nombre del producto
                                $nombreProducto = DB::connection('mysql_gestion_comercial_alimentos')
                                    ->table('inventario_productodetalle')
                                    ->where('IdProducto', $porcion->IdProducto)
                                    ->value('Descripcion');
                                
                                // Agrupar para el resumen
                                $key = $nombreProducto ?? 'Producto #' . $porcion->IdProducto;
                                if (!isset($todosLosProductos[$key])) {
                                    $todosLosProductos[$key] = [
                                        'nombre' => $key,
                                        'cantidad' => 0,
                                        'facturas' => []
                                    ];
                                }
                                $todosLosProductos[$key]['cantidad'] += $cantidadDescontar;
                                if (!in_array($numeroFactura, $todosLosProductos[$key]['facturas'])) {
                                    $todosLosProductos[$key]['facturas'][] = $numeroFactura;
                                }
                                
                                // Obtener precio costo
                                $precioCosto = DB::connection('mysql_gestion_comercial_alimentos')
                                    ->table('inventario_productodetalle_precio_costo')
                                    ->where('IdProducto', $porcion->IdProducto)
                                    ->orderBy('IdPrecioCosto', 'DESC')
                                    ->value('PrecioCosto');
                                
                                $costoTotal = $cantidadDescontar * ($precioCosto ?? 0);
                                
                                if ($idEstado == 1) {
                                    DB::connection('mysql_gestion_comercial_alimentos')
                                        ->table('inventario_propiamente')
                                        ->insert([
                                            'IdTipoDeOperacion' => $idTipoOperacion,
                                            'IdDocumento' => $idVentas,
                                            'IdFecha' => $idFecha,
                                            'IdAlmacen' => $idAlmacen,
                                            'IdProducto' => $porcion->IdProducto,
                                            'Glosa' => "{$reciboFactura} Ventas No {$numeroFactura}; Op.{$nombreOperador}",
                                            'D_H' => 'H',
                                            'Unidades' => $cantidadDescontar,
                                            'Bolivianos' => $costoTotal,
                                            'IdCliente' => $clienteId,
                                            'IdSucursal' => $sucursalId,
                                        ]);
                                    
                                    $movimientosFactura++;
                                    $totalMovimientos++;
                                }
                            }
                        }
                    }
                    
                    $totalProcesadas++;
                    $facturasProcesadas[] = [
                        'id' => $idVentas,
                        'numero' => $numeroFactura,
                        'estado' => $idEstado == 1 ? 'Válida' : 'No válida',
                        'movimientos' => $movimientosFactura
                    ];
                    
                    \Log::info('✅ Factura ' . $numeroFactura . ' procesada correctamente - ' . $movimientosFactura . ' movimientos');
                    
                } catch (\Exception $e) {
                    \Log::error('❌ Error procesando factura ' . $factura->IdVentas . ': ' . $e->getMessage());
                    $errores[] = 'Factura N° ' . $factura->NumeroFactura . ': ' . $e->getMessage();
                }
            }
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            \Log::info('✅ RANGO DE FACTURAS REPROCESADO CON ÉXITO');
            \Log::info('Total facturas: ' . $totalProcesadas . ' | Movimientos: ' . $totalMovimientos);
            
            // Convertir productos a array para el modal
            $productosArray = [];
            foreach ($todosLosProductos as $producto) {
                $productosArray[] = [
                    'nombre' => $producto['nombre'],
                    'cantidad' => $producto['cantidad'],
                    'facturas' => $producto['facturas']
                ];
            }
            
            $mensaje = "✅ {$totalProcesadas} facturas reprocesadas correctamente";
            if (!empty($errores)) {
                $mensaje .= " | ⚠️ " . count($errores) . " errores";
            }
            
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'total' => $totalProcesadas,
                'total_movimientos' => $totalMovimientos,
                'facturas' => $facturasProcesadas,
                'productos' => $productosArray,
                'errores' => $errores,
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            \Log::error('❌ Error reprocesando rango: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}