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
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $identificadorComisionista = session('venta_tactil_comisionista_identificador');
            
            if (!$clienteId || !$sucursalId) {
                return response()->json([
                    'success' => false,
                    'error' => 'Faltan datos de contexto'
                ], 400);
            }
            
            // 1. Buscar precio mayorista
            if ($identificadorComisionista) {
                $precioMayorista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_preciomayorista')
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdIdentificador', $identificadorComisionista)
                    ->where('IdProducto', $idProducto)
                    ->value('Precio');
                
                if ($precioMayorista) {
                    return response()->json([
                        'success' => true,
                        'precio' => (float) $precioMayorista,
                        'tipo' => 'mayorista'
                    ]);
                }
            }
            
            // 2. Buscar precio por sucursal
            $precioSucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_preciosucursal')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdProducto', $idProducto)
                ->where('PrecioDiferenciadoA', 'Sucursal')
                ->value('Precio');
            
            if ($precioSucursal) {
                return response()->json([
                    'success' => true,
                    'precio' => (float) $precioSucursal,
                    'tipo' => 'sucursal'
                ]);
            }
            
            // 3. Precio por defecto
            $precioDefault = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario')
                ->where('IdDetalleProducto', $idProducto)
                ->value('PrecioVenta');
            
            return response()->json([
                'success' => true,
                'precio' => (float) ($precioDefault ?? 0),
                'tipo' => 'default'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error obteniendo precio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar producto normal al carrito
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

            $total = $request->unidades * $request->precio;

            // Verificar si el producto ya existe en el carrito
            $existente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->where('idrelacionventainventario', $request->id_producto)
                ->first();

            if ($existente) {
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
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->insert([
                        'idventas' => $ventaId,
                        'IdVentaGrupo' => 0,
                        'idrelacionventainventario' => $request->id_producto,
                        'unidades' => $request->unidades,
                        'preciounidades' => $request->precio,
                        'totalbolivianos' => $total,
                        'PorcentajeDescuento' => 0,
                        'Descuento' => 0,
                        'TotalBolivianosFacturado' => 0,
                        'entregado' => 0,
                        'personalizacion' => null,
                    ]);
            }

            // Actualizar el total de la venta
            $nuevoTotalVenta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->sum('totalbolivianos');

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update(['ImporteVenta' => $nuevoTotalVenta]);

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
     * Agregar combo con opciones personalizadas al carrito
     * 🔥 NUEVA VERSIÓN: Soporta múltiples combos con diferentes personalizaciones
     */
    public function agregarCombo(Request $request)
    {
        try {
            \Log::info('=== agregarCombo ===');
            \Log::info('Request data:', $request->all());
            
            $request->validate([
                'id_combo' => 'required|integer',
                'personalizaciones' => 'nullable|array',
                'selecciones' => 'nullable|array',
                'cantidad_total' => 'nullable|integer|min:1',
                'cantidad' => 'nullable|integer|min:1',
                'precio_unitario' => 'required|numeric|min:0'
            ]);

            $ventaId = session('venta_tactil_id');
            
            if (!$ventaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una venta activa. Inicia una nueva venta primero.'
                ], 400);
            }

            $idCombo = $request->id_combo;
            $precioCombo = $request->precio_unitario;
            
            // Determinar cantidad total
            $cantidadTotal = $request->cantidad_total ?? $request->cantidad ?? 1;
            
            // Determinar personalizaciones
            $personalizaciones = $request->personalizaciones;
            
            // Si viene el viejo formato 'selecciones', convertirlo
            if (!$personalizaciones && $request->selecciones) {
                $personalizaciones = [];
                for ($i = 0; $i < $cantidadTotal; $i++) {
                    $personalizaciones[] = ['personalizacion' => $request->selecciones];
                }
            }
            
            // Si aún no hay personalizaciones, crear array vacío
            if (!$personalizaciones) {
                $personalizaciones = [];
                for ($i = 0; $i < $cantidadTotal; $i++) {
                    $personalizaciones[] = ['personalizacion' => []];
                }
            }
            
            $totalCombo = $precioCombo * $cantidadTotal;

            // Verificar si el combo ya existe en el carrito
            $existente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->where('idrelacionventainventario', $idCombo)
                ->first();

            $jsonPersonalizaciones = json_encode($personalizaciones);

            if ($existente) {
                // Combinar personalizaciones existentes con las nuevas
                $existentes = json_decode($existente->personalizacion, true) ?? [];
                $nuevasPersonalizaciones = array_merge($existentes, $personalizaciones);
                
                $nuevasUnidades = $existente->unidades + $cantidadTotal;
                $nuevoTotal = $nuevasUnidades * $existente->preciounidades;
                
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->where('idventasdetalle', $existente->idventasdetalle)
                    ->update([
                        'unidades' => $nuevasUnidades,
                        'totalbolivianos' => $nuevoTotal,
                        'personalizacion' => json_encode($nuevasPersonalizaciones)
                    ]);
            } else {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->insert([
                        'idventas' => $ventaId,
                        'IdVentaGrupo' => 0,
                        'idrelacionventainventario' => $idCombo,
                        'unidades' => $cantidadTotal,
                        'preciounidades' => $precioCombo,
                        'totalbolivianos' => $totalCombo,
                        'PorcentajeDescuento' => 0,
                        'Descuento' => 0,
                        'TotalBolivianosFacturado' => 0,
                        'entregado' => 0,
                        'personalizacion' => $jsonPersonalizaciones,
                    ]);
            }

            // Actualizar el total de la venta
            $nuevoTotalVenta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->sum('totalbolivianos');

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update(['ImporteVenta' => $nuevoTotalVenta]);

            \Log::info('Combo agregado correctamente', [
                'id_combo' => $idCombo,
                'cantidad_total' => $cantidadTotal,
                'total_combo' => $totalCombo,
                'personalizaciones' => $personalizaciones
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Combo agregado correctamente',
                'total_venta' => $nuevoTotalVenta
            ]);

        } catch (\Exception $e) {
            \Log::error('Error agregando combo: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
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
                    'd.idrelacionventainventario as id_producto',
                    'p.Detalle as nombre',
                    'd.unidades',
                    'd.preciounidades as precio',
                    'd.totalbolivianos as subtotal',
                    'd.personalizacion'
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
                ->update(['ImporteVenta' => $nuevoTotalVenta]);

            return response()->json([
                'success' => true,
                'message' => 'Cantidad actualizada',
                'total_venta' => $nuevoTotalVenta
            ]);

        } catch (\Exception $e) {
            Log::error('Error actualizando cantidad: ' . $e->getMessage());
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

            $nuevoTotalVenta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->sum('totalbolivianos');

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update(['ImporteVenta' => $nuevoTotalVenta]);

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
            $ventaId = session('venta_tactil_id');
            
            if (!$ventaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay una venta activa'
                ], 400);
            }

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $ventaId)
                ->delete();

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->delete();

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
            Log::error('Error cancelando venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}