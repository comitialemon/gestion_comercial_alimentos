<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Todos\ClienteSucursal;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class VentaReprocesarRangoController extends Controller
{
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
            $todosLosProductos = []; // 🔥 NUEVO: Para agrupar productos
            $totalMovimientos = 0;    // 🔥 NUEVO: Total de movimientos
            
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
                    
                    // Eliminar movimientos anteriores
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_propiamente')
                        ->where('IdTipoDeOperacion', 2)
                        ->where('IdDocumento', $idVentas)
                        ->delete();
                    
                    // Obtener fecha
                    $fechaVenta = date('Y-m-d', strtotime($factura->FechaVenta));
                    
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
                    
                    // Procesar detalles
                    $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_detalle')
                        ->where('idventas', $idVentas)
                        ->get();
                    
                    $movimientosFactura = 0;
                    
                    foreach ($detalles as $detalle) {
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
                            
                            // 🔥 Agrupar productos por nombre
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
                                        'IdTipoDeOperacion' => 2,
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
                    
                    $totalProcesadas++;
                    $facturasProcesadas[] = [
                        'id' => $idVentas,
                        'numero' => $numeroFactura,
                        'estado' => $idEstado == 1 ? 'Válida' : 'No válida',
                        'movimientos' => $movimientosFactura
                    ];
                    
                    \Log::info('✅ Factura ' . $numeroFactura . ' procesada correctamente');
                    
                } catch (\Exception $e) {
                    \Log::error('❌ Error procesando factura ' . $factura->IdVentas . ': ' . $e->getMessage());
                    $errores[] = 'Factura N° ' . $factura->NumeroFactura . ': ' . $e->getMessage();
                }
            }
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            \Log::info('✅ RANGO DE FACTURAS REPROCESADO CON ÉXITO');
            
            // 🔥 Convertir productos a array para el modal
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
                'total_movimientos' => $totalMovimientos, // 🔥 NUEVO
                'facturas' => $facturasProcesadas,
                'productos' => $productosArray, // 🔥 NUEVO: Lista de productos afectados
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