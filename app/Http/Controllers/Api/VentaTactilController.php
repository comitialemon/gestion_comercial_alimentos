<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VentaTactilController extends Controller
{
    /**
     * Obtener precio de un producto según comisionista y sucursal
     */
    public function getPrecio($idProducto)
    {
        try {
            // Log para depuración
            \Log::info('=== getPrecio ===');
            \Log::info('Producto ID: ' . $idProducto);
            \Log::info('Session cliente_id: ' . session('cliente_id'));
            \Log::info('Session cliente_sucursal_id: ' . session('cliente_sucursal_id'));
            \Log::info('Session venta_tactil_comisionista_identificador: ' . session('venta_tactil_comisionista_identificador'));
            
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $identificadorComisionista = session('venta_tactil_comisionista_identificador');
            
            // Verificar que tenemos los datos necesarios
            if (!$clienteId || !$sucursalId) {
                \Log::error('Faltan datos de contexto: cliente_id o sucursal_id');
                return response()->json([
                    'success' => false,
                    'error' => 'Faltan datos de contexto'
                ], 400);
            }
            
            // 1. Buscar precio mayorista (por comisionista)
            $precioMayorista = null;
            if ($identificadorComisionista) {
                \Log::info('Buscando precio mayorista con identificador: ' . $identificadorComisionista);
                $precioMayorista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_preciomayorista')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdIdentificador', $identificadorComisionista)
                    ->where('IdProducto', $idProducto)
                    ->value('Precio');
                
                \Log::info('Precio mayorista encontrado: ' . ($precioMayorista ?? 'NULL'));
            }
            
            if ($precioMayorista) {
                return response()->json([
                    'success' => true,
                    'precio' => (float) $precioMayorista,
                    'tipo' => 'mayorista'
                ]);
            }
            
            // 2. Buscar precio por sucursal
            \Log::info('Buscando precio por sucursal');
            $precioSucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_preciosucursal')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdProducto', $idProducto)
                ->where('PrecioDiferenciadoA', 'Sucursal')
                ->value('Precio');
            
            \Log::info('Precio sucursal encontrado: ' . ($precioSucursal ?? 'NULL'));
            
            if ($precioSucursal) {
                return response()->json([
                    'success' => true,
                    'precio' => (float) $precioSucursal,
                    'tipo' => 'sucursal'
                ]);
            }
            
            // 3. Precio por defecto
            \Log::info('Buscando precio por defecto');
            $precioDefault = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario')
                ->where('IdDetalleProducto', $idProducto)
                ->value('PrecioVenta');
            
            \Log::info('Precio default encontrado: ' . ($precioDefault ?? 'NULL'));
            
            return response()->json([
                'success' => true,
                'precio' => (float) ($precioDefault ?? 0),
                'tipo' => 'default'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo precio: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar producto al carrito (venta táctil)
     */
    public function agregarProducto(Request $request)
    {
        try {
            $request->validate([
                'id_producto' => 'required|integer',
                'unidades' => 'required|integer|min:1',
                'precio' => 'required|numeric|min:0'
            ]);

            $ventaId = session('venta_tactil_id');
            
            if (!$ventaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una venta activa. Inicia una nueva venta primero.'
                ], 400);
            }

            // Verificar si el producto ya existe en el carrito
            $existente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->where('idrelacionventainventario', $request->id_producto)
                ->first();

            $total = $request->unidades * $request->precio;

            if ($existente) {
                // Actualizar cantidad y total
                $nuevasUnidades = $existente->unidades + $request->unidades;
                $nuevoTotal = $nuevasUnidades * $existente->preciounidades;
                
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->where('idventasdetalle', $existente->idventasdetalle)
                    ->update([
                        'unidades' => $nuevasUnidades,
                        'totalbolivianos' => $nuevoTotal
                    ]);
            } else {
                // Insertar nuevo producto
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->insert([
                        'idventas' => $ventaId,
                        'IdVentaGrupo' => 0, // temporal, se puede mejorar después
                        'idrelacionventainventario' => $request->id_producto,
                        'unidades' => $request->unidades,
                        'preciounidades' => $request->precio,
                        'totalbolivianos' => $total,
                        'PorcentajeDescuento' => 0,
                        'Descuento' => 0,
                        'TotalBolivianosFacturado' => 0,
                        'entregado' => 0,
                    ]);
            }

            // Actualizar el total de la venta en la cabecera
            $nuevoTotalVenta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->sum('totalbolivianos');

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update([
                    'ImporteVenta' => $nuevoTotalVenta
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado correctamente',
                'total_venta' => $nuevoTotalVenta
            ]);

        } catch (\Exception $e) {
            Log::error('Error agregando producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el carrito actual
     */
    public function getCarrito()
    {
        try {
            $ventaId = session('venta_tactil_id');
            
            if (!$ventaId) {
                return response()->json([
                    'success' => true,
                    'items' => [],
                    'total' => 0
                ]);
            }

            $items = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle as d')
                ->join('inventario_relacion_ventainventario as p', 'd.idrelacionventainventario', '=', 'p.IdDetalleProducto')
                ->where('d.idventas', $ventaId)
                ->select(
                    'd.idventasdetalle as id',
                    'p.IdDetalleProducto as id_producto',
                    'p.Detalle as nombre',
                    'd.unidades',
                    'd.preciounidades as precio',
                    'd.totalbolivianos as subtotal'
                )
                ->get();

            $total = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->sum('totalbolivianos');

            return response()->json([
                'success' => true,
                'items' => $items,
                'total' => (float) $total
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cantidad de un producto en el carrito
     */
    public function actualizarCantidad(Request $request, $itemId)
    {
        try {
            // 🔥 LOG PARA VER QUÉ LLEGA EXACTAMENTE
            \Log::info('=== actualizarCantidad API ===');
            \Log::info('Item ID: ' . $itemId);
            \Log::info('Request all: ', $request->all());
            \Log::info('Unidades valor: ' . $request->unidades);
            \Log::info('Unidades tipo: ' . gettype($request->unidades));
            
            $request->validate([
                'unidades' => 'required|integer|min:1'
            ]);

            $ventaId = session('venta_tactil_id');
            
            if (!$ventaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una venta activa'
                ], 400);
            }

            $item = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventasdetalle', $itemId)
                ->where('idventas', $ventaId)
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            $nuevasUnidades = (int) $request->unidades;
            $nuevoSubtotal = $nuevasUnidades * $item->preciounidades;
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventasdetalle', $itemId)
                ->update([
                    'unidades' => $nuevasUnidades,
                    'totalbolivianos' => $nuevoSubtotal
                ]);

            $nuevoTotalVenta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->sum('totalbolivianos');

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update([
                    'ImporteVenta' => $nuevoTotalVenta
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Cantidad actualizada',
                'total_venta' => $nuevoTotalVenta
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Error de validación: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos: ' . json_encode($e->errors())
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error actualizando cantidad: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un producto del carrito
     */
    public function eliminarProducto($itemId)
    {
        try {
            $ventaId = session('venta_tactil_id');
            
            if (!$ventaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una venta activa'
                ], 400);
            }

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventasdetalle', $itemId)
                ->where('idventas', $ventaId)
                ->delete();

            // Actualizar total de la venta
            $nuevoTotalVenta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->sum('totalbolivianos');

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update([
                    'ImporteVenta' => $nuevoTotalVenta
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado',
                'total_venta' => $nuevoTotalVenta
            ]);

        } catch (\Exception $e) {
            Log::error('Error eliminando producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancelar venta completa
     */
    public function cancelarVenta()
    {
        try {
            \Log::info('=== cancelarVenta ===');
            $ventaId = session('venta_tactil_id');
            
            \Log::info('Venta ID en sesión: ' . $ventaId);
            
            if (!$ventaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una venta activa'
                ], 400);
            }

            // Eliminar detalles
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->delete();

            // Eliminar cabecera
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->delete();

            // Limpiar sesión
            session()->forget([
                'venta_tactil_id',
                'venta_tactil_lugar_id',
                'venta_tactil_comisionista_id',
                'venta_tactil_comisionista_identificador'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Venta cancelada correctamente'
            ]);

        } catch (\Exception $e) {
            \Log::error('Error cancelando venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function procesarPagoSimple(Request $request)
    {
        try {
            $request->validate([
                'venta_id' => 'required|exists:impuestos_ventas,IdVentas',
                'montos' => 'required|array',
            ]);

            $ventaId = $request->venta_id;

            // Registrar liquidación para cada método
            foreach ($request->montos as $metodoId => $monto) {
                if ($monto > 0) {
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->insert([
                            'IdVentas' => $ventaId,
                            'IdIdentificador' => session('operador_id'),
                            'IdCuenta' => $metodoId,
                            'Bolivianos' => $monto,
                            'EfectivoRecibido' => array_sum($request->montos),
                        ]);
                }
            }

            // Marcar venta como finalizada
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update([
                    'ActivoInactivo' => 1,
                    'FechaUltimaActualizcion' => now()
                ]);

            return response()->json(['success' => true, 'message' => 'Venta completada']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}