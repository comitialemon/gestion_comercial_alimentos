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
     * ✅ BUSCAR CLIENTES PARA AUTOCOMPLETE (de la bitácora)
     */
    public function buscarClientesBitacora(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $termino = $request->get('q', '');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_precio_bitacora as b')
            ->join('todos_identificador as i', 'b.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('b.IdCliente', $clienteId)
            ->where('b.IdSucursal', $sucursalId);

        if (!empty($termino)) {
            $query->where(function ($q) use ($termino) {
                $q->where('i.Nombre', 'LIKE', '%' . $termino . '%')
                ->orWhere('i.CI_NIT', 'LIKE', '%' . $termino . '%');
            });
        }

        $clientes = $query
            ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->groupBy('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->orderBy('i.Nombre')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'clientes' => $clientes,
        ]);
    }

    /**
     * ✅ BUSCAR PRODUCTOS PARA AUTOCOMPLETE (de la bitácora)
     */
    public function buscarProductosBitacora(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $termino = $request->get('q', '');

        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_precio_bitacora as b')
            ->join('inventario_productodetalle as p', 'b.IdProducto', '=', 'p.IdProducto')
            ->where('b.IdCliente', $clienteId)
            ->where('b.IdSucursal', $sucursalId);

        if (!empty($termino)) {
            $query->where(function ($q) use ($termino) {
                $q->where('p.Descripcion', 'LIKE', '%' . $termino . '%')
                ->orWhere('p.Codigo', 'LIKE', '%' . $termino . '%');
            });
        }

        $productos = $query
            ->select('p.IdProducto', 'p.Descripcion', 'p.Codigo')
            ->groupBy('p.IdProducto', 'p.Descripcion', 'p.Codigo')
            ->orderBy('p.Descripcion')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'productos' => $productos,
        ]);
    }
    /**
     * ✅ VER BITÁCORA DE PRECIOS (paginada por CLIENTES, ordenados ALFABÉTICAMENTE)
     */
    public function bitacoraIndex(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        // ✅ Cuántos CLIENTES por página
        $clientesPorPagina = 5;

        // ============================================================
        // 1. IDENTIFICADORES (también sirven para el autocomplete de clientes)
        // ============================================================
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

        // ============================================================
        // 2. GRUPOS
        // ============================================================
        $gruposIds = Contenedor::where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 1)
            ->with('gruposAnalisis')
            ->get()
            ->pluck('gruposAnalisis.*.IdGrupoAnalisis')
            ->flatten()
            ->unique()
            ->toArray();

        // ============================================================
        // 3. PRODUCTOS (también sirven para el autocomplete de productos)
        // ============================================================
        $productos = ProductoDetalle::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $gruposIds)
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get(['IdProducto', 'Descripcion', 'Codigo']);

        // ============================================================
        // 4. QUERY BASE
        // ============================================================
        $queryBase = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_precio_bitacora as b')
            ->where('b.IdCliente', $clienteId)
            ->where('b.IdSucursal', $sucursalId)
            ->whereIn('b.IdIdentificador', $identificadores->pluck('IdIdentificador'));

        // Filtros
        if ($request->filled('identificador_id')) {
            $queryBase->where('b.IdIdentificador', $request->identificador_id);
        }
        if ($request->filled('producto_id')) {
            $queryBase->where('b.IdProducto', $request->producto_id);
        }
        if ($request->filled('fecha_desde')) {
            $queryBase->whereDate('b.FechaCambio', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $queryBase->whereDate('b.FechaCambio', '<=', $request->fecha_hasta);
        }

        // ============================================================
        // 5. OBTENER IDs DE CLIENTES PAGINADOS (ORDEN ALFABÉTICO)
        // ============================================================
        $page = (int) $request->get('page', 1);
        $page = max(1, $page);
        $offset = ($page - 1) * $clientesPorPagina;

        $clientesPaginadosIds = (clone $queryBase)
            ->join('todos_identificador as i_sort', 'b.IdIdentificador', '=', 'i_sort.IdIdentificador')
            ->select('b.IdIdentificador')
            ->groupBy('b.IdIdentificador', 'i_sort.Nombre')
            ->orderBy('i_sort.Nombre', 'asc')
            ->offset($offset)
            ->limit($clientesPorPagina)
            ->pluck('b.IdIdentificador')
            ->toArray();

        // Total de clientes únicos
        $totalClientes = (clone $queryBase)
            ->distinct()
            ->count('b.IdIdentificador');

        // ============================================================
        // 6. OBTENER REGISTROS DE ESOS CLIENTES
        // ============================================================
        $bitacora = collect();
        
        if (!empty($clientesPaginadosIds)) {
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
                    'o.IdIdentificador as OperadorIdIdentificador',
                    'o.NombreAcceso as OperadorNombreAcceso',
                    'o.Iniciales as OperadorIniciales',
                    'oi.Nombre as OperadorNombre',
                    'oi.CI_NIT as OperadorCI_NIT'
                )
                ->where('b.IdCliente', $clienteId)
                ->where('b.IdSucursal', $sucursalId)
                ->whereIn('b.IdIdentificador', $clientesPaginadosIds);

            // ✅ MISMOS filtros que la query base
            if ($request->filled('producto_id')) {
                $query->where('b.IdProducto', $request->producto_id);
            }
            if ($request->filled('fecha_desde')) {
                $query->whereDate('b.FechaCambio', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('b.FechaCambio', '<=', $request->fecha_hasta);
            }

            // ✅ Ordenar: primero por cliente (alfabético), luego por fecha descendente
            $bitacora = $query
                ->orderBy('i.Nombre', 'asc')
                ->orderBy('b.FechaCambio', 'desc')
                ->get();
        }

        // ============================================================
        // 7. CONSTRUIR PAGINACIÓN MANUAL
        // ============================================================
        $totalPaginas = $totalClientes > 0 
            ? (int) ceil($totalClientes / $clientesPorPagina) 
            : 1;

        $paginacion = [
            'current_page' => $page,
            'last_page' => $totalPaginas,
            'per_page' => $clientesPorPagina,
            'total' => $totalClientes,
            'from' => $totalClientes > 0 ? ($offset + 1) : 0,
            'to' => min($offset + $clientesPorPagina, $totalClientes),
            'links' => $this->generarLinksPaginacion($page, $totalPaginas, $request),
        ];

        // ============================================================
        // 8. RETORNAR A INERTIA
        //    ✅ Se reutilizan $identificadores y $productos para los autocompletes
        // ============================================================
        return Inertia::render('Operacion/ClientesMayoristas/PedidosClientes/BitacoraPrecios', [
            'bitacora'              => $bitacora,
            'paginacion'            => $paginacion,
            'identificadores'       => $identificadores,
            'productos'             => $productos,
            'clientesPorPagina'     => $clientesPorPagina,
            'clientesConPrecios'    => $identificadores,   // ✅ Para el autocomplete de clientes
            'productosHabilitados'  => $productos,         // ✅ Para el autocomplete de productos
            'filtros'               => $request->only(['identificador_id', 'producto_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    /**
     * ✅ Generar links de paginación al estilo Laravel
     */
    private function generarLinksPaginacion($currentPage, $lastPage, $request)
    {
        $links = [];
        $queryParams = $request->query();
        
        // Anterior
        $links[] = [
            'url' => $currentPage > 1 
                ? $request->url() . '?' . http_build_query(array_merge($queryParams, ['page' => $currentPage - 1]))
                : null,
            'label' => '&laquo; Anterior',
            'active' => false,
        ];
        
        // Números
        for ($i = 1; $i <= $lastPage; $i++) {
            $links[] = [
                'url' => $request->url() . '?' . http_build_query(array_merge($queryParams, ['page' => $i])),
                'label' => (string) $i,
                'active' => $i === $currentPage,
            ];
        }
        
        // Siguiente
        $links[] = [
            'url' => $currentPage < $lastPage 
                ? $request->url() . '?' . http_build_query(array_merge($queryParams, ['page' => $currentPage + 1]))
                : null,
            'label' => 'Siguiente &raquo;',
            'active' => false,
        ];
        
        return $links;
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
    /**
     * ✅ EXPORTAR PDF DE LA BITÁCORA (Código / Detalle / Precio)
     */
    public function exportarPdfBitacora(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $identificadorFiltro = $request->get('identificador_id');
        $fechaDesdeFiltro = $request->get('fecha_desde');
        $fechaHastaFiltro = $request->get('fecha_hasta');
        $productoFiltro = $request->get('producto_id');

        // ============================================================
        // 1. EMPRESA
        // ============================================================
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $clienteId)
            ->first(['Nombre', 'NIT', 'Direccion', 'Fono']);

        // ============================================================
        // 2. IDENTIFICADORES
        // ============================================================
        $identificadoresQuery = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador as i')
            ->join('todos_operador as o', 'i.IdIdentificador', '=', 'o.IdIdentificador')
            ->join('todos_operador_tipo as ot', 'o.IdOperadorTipo', '=', 'ot.IdOperadorTipo')
            ->where('ot.Detalle', 'PedidoClientes')
            ->where('o.ActivoInactivo', 0)
            ->select('i.IdIdentificador', 'i.Nombre', 'i.CI_NIT')
            ->orderBy('i.Nombre')
            ->distinct();

        if (!empty($identificadorFiltro)) {
            $identificadoresQuery->where('i.IdIdentificador', $identificadorFiltro);
        }

        $identificadores = $identificadoresQuery->get();

        if ($identificadores->isEmpty()) {
            return redirect()->back()->with('error', 'No hay datos para exportar.');
        }

        $identificadoresIds = $identificadores->pluck('IdIdentificador')->toArray();

        // ============================================================
        // 3. QUERY BASE
        // ============================================================
        $queryBase = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('operacion_pedidos_clientes_precio_bitacora as b')
            ->where('b.IdCliente', $clienteId)
            ->where('b.IdSucursal', $sucursalId)
            ->whereIn('b.IdIdentificador', $identificadoresIds);

        if (!empty($productoFiltro)) {
            $queryBase->where('b.IdProducto', $productoFiltro);
        }
        if (!empty($fechaDesdeFiltro)) {
            $queryBase->whereDate('b.FechaCambio', '>=', $fechaDesdeFiltro);
        }
        if (!empty($fechaHastaFiltro)) {
            $queryBase->whereDate('b.FechaCambio', '<=', $fechaHastaFiltro);
        }

        // ============================================================
        // 4. REGISTROS
        // ============================================================
        $registros = (clone $queryBase)
            ->join('todos_identificador as i', 'b.IdIdentificador', '=', 'i.IdIdentificador')
            ->join('inventario_productodetalle as p', 'b.IdProducto', '=', 'p.IdProducto')
            ->select(
                'b.IdIdentificador',
                'b.IdProducto',
                'b.PrecioNuevo',
                'b.FechaCambio',
                'i.Nombre as ClienteNombre',
                'i.CI_NIT as ClienteCI_NIT',
                'p.Descripcion as ProductoNombre',
                'p.Codigo as ProductoCodigo'
            )
            ->orderBy('i.Nombre', 'asc')
            ->orderBy('p.Descripcion', 'asc')
            ->orderBy('b.FechaCambio', 'desc')
            ->get();

        if ($registros->isEmpty()) {
            return redirect()->back()->with('error', 'No hay registros para exportar.');
        }

        // ============================================================
        // 5. AGRUPAR: Cliente → Producto (último precio)
        // ============================================================
        $agrupado = [];
        foreach ($registros as $reg) {
            $cId = $reg->IdIdentificador;
            $pId = $reg->IdProducto;

            if (!isset($agrupado[$cId])) {
                $agrupado[$cId] = [
                    'id' => $cId,
                    'nombre' => $reg->ClienteNombre,
                    'ci_nit' => $reg->ClienteCI_NIT,
                    'productos' => [],
                ];
            }

            if (!isset($agrupado[$cId]['productos'][$pId])) {
                $agrupado[$cId]['productos'][$pId] = [
                    'codigo' => $reg->ProductoCodigo,
                    'nombre' => $reg->ProductoNombre,
                    'precio_actual' => $reg->PrecioNuevo,
                ];
            }
        }

        uasort($agrupado, function ($a, $b) {
            return strcasecmp($a['nombre'] ?? '', $b['nombre'] ?? '');
        });

        // ============================================================
        // 6. DATOS CABECERA
        // ============================================================
        $fechaImpresion = Carbon::now('America/La_Paz')->format('d/m/Y H:i');

        $operadorLogueado = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->where('IdOperador', session('operador_id'))
            ->first(['Iniciales', 'NombreAcceso']);

        $inicialesOperador = $operadorLogueado->Iniciales ?? ($operadorLogueado->NombreAcceso ?? '-');

        $nombreClienteFiltro = null;
        if (!empty($identificadorFiltro)) {
            $nombreClienteFiltro = $identificadores->first()->Nombre ?? null;
        }

        $totalProductos = 0;
        foreach ($agrupado as $c) {
            $totalProductos += count($c['productos']);
        }

        // ============================================================
        // 7. CREAR PDF
        // ============================================================
        $pdf = new \TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 8, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        $y = 6;

        // ============================================================
        // HEADER COMPACTO
        // ============================================================
        $yHeader = $y;

        // ===== IZQUIERDA: EMPRESA + NIT =====
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(10, $yHeader);
        $pdf->Cell(70, 5.5, mb_strtoupper($empresa->Nombre ?? 'EMPRESA', 'UTF-8'), 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetXY(10, $yHeader + 6);
        $nit = !empty($empresa->NIT) ? 'NIT: ' . $empresa->NIT : '';
        $pdf->Cell(70, 3.5, $nit, 0, 1, 'L');

        // ===== CENTRO: TÍTULO + SUBTÍTULO =====
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(70, $yHeader + 1);
        $pdf->Cell(76, 6, 'BITÁCORA DE PRECIOS', 0, 1, 'C');

        $pdf->SetFont('helvetica', 'I', 7.5);
        $pdf->SetTextColor(100, 100, 100);
        $subtitulo = 'Último precio registrado por producto';
        if ($nombreClienteFiltro) {
            $subtitulo .= ' · ' . $nombreClienteFiltro;
        }
        $pdf->SetXY(70, $yHeader + 7);
        $pdf->Cell(76, 3.5, $subtitulo, 0, 1, 'C');

        // ===== DERECHA: FECHA + GENERADO POR =====
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(130, $yHeader);
        $pdf->Cell(54, 3.5, 'FECHA IMPRESIÓN:', 0, 0, 'R');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetXY(168, $yHeader);
        $pdf->Cell(38, 3.5, $fechaImpresion, 0, 1, 'R');

        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(130, $yHeader + 4.5);
        $pdf->Cell(54, 3.5, 'GENERADO POR:', 0, 0, 'R');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetXY(168, $yHeader + 4.5);
        $pdf->Cell(38, 3.5, $inicialesOperador, 0, 1, 'R');

        // ===== LÍNEA SEPARADORA =====
        $y = $yHeader + 12;   // ✅ antes era +16
        $pdf->SetDrawColor(30, 60, 120);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $y, 206, $y);
        $pdf->SetLineWidth(0.2);
        $y += 3;               // ✅ antes era +5

        // ============================================================
        // BARRA DE RESUMEN (compacta)
        // ============================================================
        $rango = 'Todos los registros';
        if ($fechaDesdeFiltro || $fechaHastaFiltro) {
            $rango = ($fechaDesdeFiltro ? Carbon::parse($fechaDesdeFiltro)->format('d/m/Y') : '...')
                . ' - '
                . ($fechaHastaFiltro ? Carbon::parse($fechaHastaFiltro)->format('d/m/Y') : '...');
        }

        $pdf->SetFillColor(245, 247, 252);
        $pdf->SetDrawColor(220, 225, 235);
        $pdf->RoundedRect(10, $y, 196, 6.5, 1.5, '1111', 'DF');

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(50, 50, 50);

        $textoResumen = 'TOTAL CLIENTES: ' . count($agrupado)
                    . '     ·     TOTAL PRODUCTOS: ' . $totalProductos
                    . '     ·     RANGO DE FECHAS: ' . $rango;

        $pdf->SetXY(12, $y + 1.5);
        $pdf->Cell(192, 3.5, $textoResumen, 0, 0, 'L');

        $y += 9;              // ✅ antes era +12

        // ============================================================
        // DEFINICIÓN DE COLUMNAS
        // ============================================================
        $colNum     = 8;
        $colCodigo  = 72;
        $colDetalle = 72;
        $colPrecio  = 44;

        $xNum     = 10;
        $xCodigo  = $xNum + $colNum;          // 18
        $xDetalle = $xCodigo + $colCodigo;    // 90
        $xPrecio  = $xDetalle + $colDetalle;  // 162
        $xFin     = $xPrecio + $colPrecio;    // 206

        // ============================================================
        // CABECERA TABLA
        // ============================================================
        $pdf->SetFillColor(30, 60, 120);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(30, 60, 120);
        $pdf->SetFont('helvetica', 'B', 8);

        $pdf->SetXY($xNum, $y);
        $pdf->Cell($colNum, 5.5, '#', 'LTRB', 0, 'C', 1);
        $pdf->Cell($colCodigo, 5.5, 'CÓDIGO', 'LTRB', 0, 'C', 1);
        $pdf->Cell($colDetalle, 5.5, 'DETALLE', 'LTRB', 0, 'C', 1);
        $pdf->Cell($colPrecio, 5.5, 'PRECIO', 'LTRB', 1, 'C', 1);
        $y += 5.5;

        $pdf->SetTextColor(0, 0, 0);

        // ============================================================
        // ITERAR CLIENTES
        // ============================================================
        $contadorGlobal = 0;

        foreach ($agrupado as $cliente) {
            // ===== HEADER CLIENTE =====
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetFillColor(232, 234, 246);
            $pdf->SetTextColor(30, 60, 120);
            $pdf->SetDrawColor(200, 210, 230);

            $pdf->SetXY(10, $y);
            $pdf->Cell(196, 5.5, '', 'LTRB', 1, 'L', 1);

            $pdf->SetXY(10, $y);
            $pdf->Cell(150, 5.5, '  ' . $cliente['nombre'] . ($cliente['ci_nit'] ? '   ·   CI: ' . $cliente['ci_nit'] : ''), 'L', 0, 'L', 1);

            $cantProd = count($cliente['productos']);
            $pdf->Cell(46, 5.5, $cantProd . ' producto' . ($cantProd !== 1 ? 's' : '') . '   ', 'R', 1, 'R', 1);
            $y += 5.5;

            $pdf->SetTextColor(0, 0, 0);

            // ===== PRODUCTOS =====
            $fill = false;

            foreach ($cliente['productos'] as $producto) {
                $contadorGlobal++;

                $codigo = $producto['codigo'] ?? '-';
                $nombre = $producto['nombre'] ?? '-';
                $precioTxt = 'Bs. ' . number_format($producto['precio_actual'], 2, ',', '.');

                $pdf->SetFont('helvetica', '', 8);

                $alturaCodigo = $pdf->getStringHeight($colCodigo - 4, $codigo);
                $alturaDetalle = $pdf->getStringHeight($colDetalle - 4, $nombre);
                $alturaContenido = max($alturaCodigo, $alturaDetalle);
                $alturaFila = max(5, $alturaContenido + 1.5);

                $yInicio = $y;
                $yFin = $yInicio + $alturaFila;

                // Fondo alternado
                $bgColor = $fill ? [248, 249, 252] : [255, 255, 255];
                $pdf->SetFillColor($bgColor[0], $bgColor[1], $bgColor[2]);

                // 1. Fondo
                $pdf->Rect($xNum, $yInicio, $xFin - $xNum, $alturaFila, 'F');

                // 2. Líneas verticales
                $pdf->SetDrawColor(220, 220, 220);
                $pdf->Line($xNum,     $yInicio, $xNum,     $yFin);
                $pdf->Line($xCodigo,  $yInicio, $xCodigo,  $yFin);
                $pdf->Line($xDetalle, $yInicio, $xDetalle, $yFin);
                $pdf->Line($xPrecio,  $yInicio, $xPrecio,  $yFin);
                $pdf->Line($xFin,     $yInicio, $xFin,     $yFin);

                // 3. Línea horizontal superior
                $pdf->Line($xNum, $yInicio, $xFin, $yInicio);

                // 4. Columna #
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetTextColor(120, 120, 120);
                $pdf->SetXY($xNum, $yInicio);
                $pdf->Cell($colNum, $alturaFila, $contadorGlobal, 0, 0, 'C', false);

                // 5. Columna CÓDIGO
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetTextColor(30, 60, 120);
                $pdf->SetXY($xCodigo + 2, $yInicio + 0.8);
                $pdf->MultiCell(
                    $colCodigo - 4,
                    3.5,
                    $codigo,
                    0,
                    'L',
                    false,
                    1,
                    '',
                    '',
                    true,
                    0,
                    false,
                    false
                );

                // 6. Columna DETALLE
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(50, 50, 50);
                $pdf->SetXY($xDetalle + 2, $yInicio + 0.8);
                $pdf->MultiCell(
                    $colDetalle - 4,
                    3.5,
                    $nombre,
                    0,
                    'L',
                    false,
                    1,
                    '',
                    '',
                    true,
                    0,
                    false,
                    false
                );

                // 7. Columna PRECIO
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetTextColor(20, 130, 80);
                $pdf->SetXY($xPrecio, $yInicio);
                $pdf->Cell($colPrecio, $alturaFila, $precioTxt . '   ', 0, 0, 'R', false);

                // 8. Avanzar Y
                $y = $yFin;
                $fill = !$fill;
            }

            // ===== SUBTOTAL CLIENTE =====
            $pdf->SetFillColor(245, 247, 252);
            $pdf->SetTextColor(30, 60, 120);
            $pdf->SetDrawColor(220, 220, 220);
            $pdf->SetFont('helvetica', 'B', 8);

            $pdf->SetXY($xNum, $y);
            $pdf->Cell($colNum + $colCodigo + $colDetalle, 4.5, '  Subtotal ' . $cliente['nombre'], 'LTRB', 0, 'L', 1);
            $pdf->Cell($colPrecio, 4.5, $cantProd . ' producto' . ($cantProd !== 1 ? 's' : '') . '   ', 'LTRB', 1, 'R', 1);
            $y += 4.5;

            $y += 2;
        }

        // ============================================================
        // TOTAL GENERAL
        // ============================================================
        $y += 2;
        $pdf->SetDrawColor(30, 60, 120);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $y, 206, $y);
        $pdf->SetLineWidth(0.2);
        $y += 3;

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(30, 60, 120);
        $pdf->SetXY(10, $y);
        $pdf->Cell(150, 6, 'TOTAL GENERAL', 0, 0, 'R');
        $pdf->Cell(46, 6, count($agrupado) . ' clientes · ' . $totalProductos . ' productos', 0, 1, 'R');

        // ============================================================
        // SALIDA
        // ============================================================
        if (ob_get_length()) {
            ob_end_clean();
        }

        $nombreArchivo = 'Bitacora_Precios_' . Carbon::now('America/La_Paz')->format('Y-m-d') . '.pdf';
        $pdf->Output($nombreArchivo, 'D');
        exit;
    }
    
}