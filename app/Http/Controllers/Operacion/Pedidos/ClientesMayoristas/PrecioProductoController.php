<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PrecioProducto;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Gestion\Inventario\ProductoDetalle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PrecioProductoController extends Controller
{
    /**
     * ✅ VISTA PARA ASIGNAR PRECIOS - CONSULTA DIRECTA
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // ✅ CONSULTA DIRECTA (sin caché, sin modelos)
        $identificadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador as i')
            ->join('todos_operador as o', 'i.IdIdentificador', '=', 'o.IdIdentificador')
            ->join('todos_operador_tipo as ot', 'o.IdOperadorTipo', '=', 'ot.IdOperadorTipo')
            ->where('ot.Detalle', 'PedidoClientes')
            ->where('o.ActivoInactivo', 0)  // ✅ 0 = Activo
            ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->orderBy('i.Nombre')
            ->distinct()
            ->get();

        // ✅ 2. OBTENER LOS GRUPOS DE ANÁLISIS QUE ESTÁN EN CONTENEDORES ACTIVOS
        $gruposIds = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with('gruposAnalisis')
            ->get()
            ->pluck('gruposAnalisis.*.IdGrupoAnalisis')
            ->flatten()
            ->unique()
            ->toArray();

        // ✅ 3. OBTENER LOS PRODUCTOS DE ESOS GRUPOS
        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $gruposIds)
            ->where('ActivoInactivo', 0)
            ->orderBy('OrdenInformes')
            ->orderBy('Descripcion')
            ->get(['IdProducto', 'Descripcion', 'Codigo', 'Precio']);

        // ✅ 4. OBTENER TODOS LOS PRECIOS PARA ESTOS IDENTIFICADORES
        $todosLosPrecios = PrecioProducto::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->whereIn('IdIdentificador', $identificadores->pluck('IdIdentificador'))
            ->where('ActivoInactivo', 1)
            ->get(['IdProducto', 'IdIdentificador', 'Precio']);

        // ✅ 5. AGRUPAR PRECIOS POR PRODUCTO
        $preciosPorProducto = [];
        foreach ($todosLosPrecios as $precio) {
            $productoId = $precio->IdProducto;
            if (!isset($preciosPorProducto[$productoId])) {
                $preciosPorProducto[$productoId] = [];
            }
            $preciosPorProducto[$productoId][$precio->IdIdentificador] = $precio->Precio;
        }

        // ✅ 6. ARMAR EL ARRAY FINAL DE PRODUCTOS
        $productosFinal = [];
        foreach ($productos as $producto) {
            $productosFinal[] = [
                'IdProducto' => $producto->IdProducto,
                'Codigo' => $producto->Codigo,
                'Descripcion' => $producto->Descripcion,
                'Precio' => $producto->Precio,
                'precios' => $preciosPorProducto[$producto->IdProducto] ?? [],
            ];
        }

        // ✅ LOG PARA VERIFICAR
        Log::info('=== IDENTIFICADORES ENCONTRADOS ===');
        Log::info('Cantidad: ' . $identificadores->count());
        Log::info('Datos:', $identificadores->toArray());

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/PrecioPedidosClientesMayoristas', [
            'identificadores' => $identificadores,
            'productos' => $productosFinal,
            'sucursalId' => $sucursalId,
        ]);
    }

    /**
     * ✅ GUARDAR O ACTUALIZAR UN PRECIO
     */
    public function store(Request $request)
    {
        $request->validate([
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
            'Precio' => 'required|numeric|min:0',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'Motivo' => 'nullable|string|max:255',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        try {
            DB::transaction(function() use ($request, $clienteId, $operadorId) {
                $precio = PrecioProducto::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $request->IdSucursal)
                    ->where('IdIdentificador', $request->IdIdentificador)
                    ->where('IdProducto', $request->IdProducto)
                    ->first();

                if ($precio) {
                    $precioAnterior = $precio->Precio;
                    
                    $precio->update([
                        'Precio' => $request->Precio,
                        'IdOperadorActualiza' => $operadorId,
                        'FechaActualiza' => Carbon::now('America/La_Paz'),
                    ]);

                    if ($precioAnterior != $request->Precio) {
                        $this->guardarBitacora($precio, $precioAnterior, $request->Precio, $operadorId, $request->Motivo);
                    }

                } else {
                    $precio = PrecioProducto::create([
                        'IdIdentificador' => $request->IdIdentificador,
                        'IdProducto' => $request->IdProducto,
                        'Precio' => $request->Precio,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $request->IdSucursal,
                        'IdOperadorInserta' => $operadorId,
                        'FechaInserta' => Carbon::now('America/La_Paz'),
                        'ActivoInactivo' => 1,
                    ]);

                    $this->guardarBitacora($precio, 0, $request->Precio, $operadorId, $request->Motivo ?? 'Creación de precio');
                }
            });

            $this->limpiarCachePrecios();

            return response()->json([
                'success' => true,
                'message' => 'Precio guardado correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al guardar precio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el precio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ ELIMINAR UN PRECIO (desactivar)
     */
    public function destroy($productoId, $identificadorId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            DB::transaction(function() use ($productoId, $identificadorId, $clienteId, $sucursalId, $operadorId) {
                $precio = PrecioProducto::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdIdentificador', $identificadorId)
                    ->where('IdProducto', $productoId)
                    ->where('ActivoInactivo', 1)
                    ->first();

                if (!$precio) {
                    throw new \Exception('Precio no encontrado');
                }

                $this->guardarBitacora(
                    $precio, 
                    $precio->Precio, 
                    0, 
                    $operadorId, 
                    'Eliminación de precio'
                );

                $precio->update([
                    'ActivoInactivo' => 0,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaActualiza' => Carbon::now('America/La_Paz'),
                ]);
            });

            $this->limpiarCachePrecios();

            return response()->json([
                'success' => true,
                'message' => 'Precio eliminado correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar precio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el precio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ GUARDAR EN BITÁCORA
     */
    private function guardarBitacora($precio, $precioAnterior, $precioNuevo, $operadorId, $motivo = null)
    {
        DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_precio_bitacora')
            ->insert([
                'IdPrecioCliente' => $precio->IdPrecioCliente,
                'IdIdentificador' => $precio->IdIdentificador,
                'IdProducto' => $precio->IdProducto,
                'IdCliente' => $precio->IdCliente,
                'IdSucursal' => $precio->IdSucursal,
                'PrecioAnterior' => $precioAnterior,
                'PrecioNuevo' => $precioNuevo,
                'IdOperador' => $operadorId,
                'FechaCambio' => Carbon::now('America/La_Paz'),
                'Motivo' => $motivo,
            ]);
    }

    /**
     * ✅ OBTENER EL PRECIO DE UN PRODUCTO PARA UN IDENTIFICADOR (API)
     */
    public function getPrecio(Request $request)
    {
        $request->validate([
            'IdIdentificador' => 'required|exists:todos_identificador,IdIdentificador',
            'IdProducto' => 'required|exists:inventario_productodetalle,IdProducto',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        $precio = PrecioProducto::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdIdentificador', $request->IdIdentificador)
            ->where('IdProducto', $request->IdProducto)
            ->where('ActivoInactivo', 1)
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'tiene_precio' => $precio ? true : false,
                'precio' => $precio ? $precio->Precio : null,
                'precio_formateado' => $precio ? number_format($precio->Precio, 2, ',', '.') : null,
            ]
        ]);
    }

    /**
     * ✅ VER BITÁCORA DE PRECIOS
     */
    public function bitacoraIndex(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        // ✅ USAR LA MISMA CONSULTA DIRECTA
        $identificadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador as i')
            ->join('todos_operador as o', 'i.IdIdentificador', '=', 'o.IdIdentificador')
            ->join('todos_operador_tipo as ot', 'o.IdOperadorTipo', '=', 'ot.IdOperadorTipo')
            ->where('ot.Detalle', 'PedidoClientes')
            ->where('o.ActivoInactivo', 0)
            ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->orderBy('i.Nombre')
            ->distinct()
            ->get();

        $gruposIds = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with('gruposAnalisis')
            ->get()
            ->pluck('gruposAnalisis.*.IdGrupoAnalisis')
            ->flatten()
            ->unique()
            ->toArray();

        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $gruposIds)
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto', 'Descripcion', 'Codigo']);

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_precio_bitacora as b')
            ->join('todos_identificador as i', 'b.IdIdentificador', '=', 'i.IdIdentificador')
            ->join('inventario_productodetalle as p', 'b.IdProducto', '=', 'p.IdProducto')
            ->join('todos_operador as o', 'b.IdOperador', '=', 'o.IdOperador')
            ->join('todos_identificador as oi', 'o.IdIdentificador', '=', 'oi.IdIdentificador')
            ->select(
                'b.*',
                'i.Nombre as IdentificadorNombre',
                'i.CI_NIT',
                'p.Descripcion as ProductoNombre',
                'p.Codigo as ProductoCodigo',
                'oi.Nombre as OperadorNombre'
            )
            ->where('b.IdCliente', $clienteId)
            ->where('b.IdSucursal', $sucursalId)
            ->whereIn('b.IdIdentificador', $identificadores->pluck('IdIdentificador'));

        if ($request->filled('identificador_id')) {
            $query->where('b.IdIdentificador', $request->identificador_id);
        }

        if ($request->filled('producto_id')) {
            $query->where('b.IdProducto', $request->producto_id);
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('b.FechaCambio', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('b.FechaCambio', '<=', $request->fecha_hasta);
        }

        $bitacora = $query->orderBy('b.FechaCambio', 'desc')
            ->paginate(20);

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/BitacoraPrecios', [
            'bitacora' => $bitacora,
            'identificadores' => $identificadores,
            'productos' => $productos,
            'filtros' => $request->only(['identificador_id', 'producto_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    /**
     * ✅ LIMPIAR CACHÉ DE PRECIOS
     */
    private function limpiarCachePrecios()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        cache()->forget('identificadores_pedido_clientes');
        cache()->forget('operador_tipo_pedido_clientes');
        cache()->forget('operadores_pedido_clientes');
        cache()->forget("precios_cliente_{$clienteId}_sucursal_{$sucursalId}");
        cache()->forget("productos_habilitados_{$clienteId}");
        cache()->forget('operador_identificador_' . session('operador_id'));
        cache()->forget('operador_nombre_' . session('operador_id'));
    }
}