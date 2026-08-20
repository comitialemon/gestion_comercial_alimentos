<?php

namespace App\Http\Controllers\Operacion\Pedidos\ClientesMayoristas;

use App\Http\Controllers\Controller;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PrecioProducto;
use App\Models\Operacion\Pedidos\ClientesMayoristas\Contenedor;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PrecioProductoController extends Controller
{
    /**
     * Vista para asignar precios (Supervisor)
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        // 1. Obtener todos los identificadores
        $identificadores = Identificador::orderBy('Nombre')
            ->get(['IdIdentificador', 'Nombre', 'CI_NIT']);

        // 2. Obtener los grupos de análisis que están en contenedores activos
        $gruposIds = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with('gruposAnalisis')
            ->get()
            ->pluck('gruposAnalisis.*.IdGrupoAnalisis')
            ->flatten()
            ->unique()
            ->toArray();

        // 3. Obtener los productos de esos grupos
        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $gruposIds)
            ->where('ActivoInactivo', 0)
            ->orderBy('OrdenInformes')
            ->orderBy('Descripcion')
            ->get(['IdProducto', 'Descripcion', 'Codigo', 'Precio']);

        // 4. Obtener todos los precios
        $todosLosPrecios = PrecioProducto::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->get(['IdProducto', 'IdIdentificador', 'Precio']);

        // 5. Agrupar precios por producto
        $preciosPorProducto = [];
        foreach ($todosLosPrecios as $precio) {
            $productoId = $precio->IdProducto;
            if (!isset($preciosPorProducto[$productoId])) {
                $preciosPorProducto[$productoId] = [];
            }
            $preciosPorProducto[$productoId][$precio->IdIdentificador] = $precio->Precio;
        }

        // 6. Armar el array final de productos
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

        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/PrecioPedidosClientesMayoristas', [
            'identificadores' => $identificadores,
            'productos' => $productosFinal,
            'sucursalId' => $sucursalId,
        ]);
    }

    /**
     * Guardar o actualizar un precio
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
     * Eliminar un precio (desactivar)
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
     * Guardar en bitácora
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
     * Obtener el precio de un producto para un identificador (API)
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
     * ✅ VER BITÁCORA DE PRECIOS - CON AUTOSUGGEST
     */
    public function bitacoraIndex(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');

        // 1. Obtener SOLO los clientes que tienen precios asignados (para el autocomplete)
        $clientesConPrecios = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_precio_productos as p')
            ->join('todos_identificador as i', 'p.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('p.IdCliente', $clienteId)
            ->where('p.IdSucursal', $sucursalId)
            ->where('p.ActivoInactivo', 1)
            ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->distinct()
            ->orderBy('i.Nombre')
            ->get();

        // 2. Obtener SOLO los productos habilitados por contenedor
        $gruposIds = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with('gruposAnalisis')
            ->get()
            ->pluck('gruposAnalisis.*.IdGrupoAnalisis')
            ->flatten()
            ->unique()
            ->toArray();

        $productosHabilitados = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $gruposIds)
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto', 'Descripcion', 'Codigo']);

        // 3. Consultar bitácora
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
            ->where('b.IdSucursal', $sucursalId);

        // Aplicar filtros si existen
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
            'clientesConPrecios' => $clientesConPrecios,
            'productosHabilitados' => $productosHabilitados,
            'filtros' => $request->only(['identificador_id', 'producto_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }
}