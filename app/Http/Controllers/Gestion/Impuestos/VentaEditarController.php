<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Impuestos\VentaEstado;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class VentaEditarController extends Controller
{
    /**
     * 📋 GRID DE VENTAS CON FILTROS
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // 🔥 LOG PARA VERIFICAR EL CLIENTE ID
        \Log::info('=== CLIENTE ID EN SESIÓN ===');
        \Log::info('Cliente ID: ' . $clienteId);
        
        // =============================================
        // CONSULTA PRINCIPAL
        // =============================================
        $query = Venta::where('IdCliente', $clienteId);
        
        // FILTRO POR SUCURSAL
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('IdClienteSucursal', $request->sucursal_id);
        }
        
        // FILTRO POR ESTADO
        if ($request->filled('estado') && $request->estado !== '') {
            $query->where('IdEstado', $request->estado);
        }
        
        // FILTRO POR VENDEDOR
        if ($request->filled('vendedor_id') && $request->vendedor_id !== '') {
            $query->where('IdOperadorIngresa', $request->vendedor_id);
        }
        
        // FILTRO POR BÚSQUEDA (N° Factura)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('NumeroFactura', 'like', "%{$search}%")
                  ->orWhere('NumeroAutorizacion', 'like', "%{$search}%");
            });
        }
        
        // PAGINACIÓN
        $ventas = $query->orderBy('FechaVenta', 'desc')
            ->paginate(20)
            ->withQueryString();
        
        // ENRIQUECER DATOS
        $ventas->getCollection()->transform(function($venta) {
            $estado = VentaEstado::find($venta->IdEstado);
            $venta->estado_nombre = $estado ? $estado->Detalle : 'Desconocido';
            $venta->estado_abrev = $estado ? $estado->Abreviacion : '?';
            
            $cliente = Identificador::find($venta->IdNIT);
            $venta->cliente_nit = $cliente ? $cliente->CI_NIT : '0';
            
            $operador = Operador::with('identificador')->find($venta->IdOperadorIngresa);
            $venta->vendedor_nombre = $operador && $operador->identificador ? $operador->identificador->Nombre : 'Desconocido';
            
            $sucursal = ClienteSucursal::find($venta->IdClienteSucursal);
            $venta->sucursal_nombre = $sucursal ? $sucursal->Nombre : 'Sin sucursal';
            
            return $venta;
        });
        
        // =============================================
        // CATÁLOGOS PARA FILTROS
        // =============================================
        
        // Estados
        $estados = VentaEstado::orderBy('IdVentasEstado')->get();
        
        // 🔥 TODAS LAS SUCURSALES DEL CLIENTE
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // 🔥 OPERADORES DEL CLIENTE - CONSULTA CORREGIDA
        $vendedores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador_sucursaldb as os')
            ->join('todos_operador as o', 'os.IdOperador', '=', 'o.IdOperador')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('os.IdCliente', $clienteId)
            // 🔥 QUITAMOS EL FILTRO ActivoInactivo PARA VER SI APARECEN
            // ->where('o.ActivoInactivo', 1)
            ->select(
                'o.IdOperador as id',
                DB::raw("CONCAT(i.CI_NIT, ' - ', i.Nombre) as nombre_completo"),
                'i.IdIdentificador',
                'i.Nombre'
            )
            ->distinct()
            ->orderBy('i.Nombre', 'asc')
            ->get();
        
        // 🔥 SI NO HAY VENDEDORES, INTENTAMOS SIN EL FILTRO DE CLIENTE (PARA DEPURAR)
        if ($vendedores->isEmpty()) {
            \Log::info('⚠️ No hay vendedores para el cliente ' . $clienteId . ', intentando sin filtro de cliente');
            
            $vendedores = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.ActivoInactivo', 1)
                ->select(
                    'o.IdOperador as id',
                    DB::raw("CONCAT(i.CI_NIT, ' - ', i.Nombre) as nombre_completo"),
                    'i.IdIdentificador',
                    'i.Nombre'
                )
                ->orderBy('i.Nombre', 'asc')
                ->get();
        }
        
        // 🔥 LOG PARA VER QUÉ ESTÁ PASANDO
        \Log::info('=== VENTAEDTIAR - RESULTADO ===');
        \Log::info('Cliente ID en sesión: ' . $clienteId);
        \Log::info('Total vendedores encontrados: ' . $vendedores->count());
        
        if ($vendedores->isNotEmpty()) {
            \Log::info('Primeros 5 vendedores:', $vendedores->take(5)->toArray());
        } else {
            \Log::info('⚠️ NO SE ENCONTRARON VENDEDORES');
            
            // 🔥 CONSULTA DE DEPURACIÓN - Ver cuántos operadores hay en total
            $totalOperadores = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->count();
            \Log::info('Total operadores en la tabla: ' . $totalOperadores);
            
            // Ver cuántos operadores tienen ActivoInactivo = 1
            $operadoresActivos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('ActivoInactivo', 1)
                ->count();
            \Log::info('Operadores activos (ActivoInactivo=1): ' . $operadoresActivos);
            
            // Ver cuántos operadores hay en todos_operador_sucursaldb para este cliente
            $operadoresEnSucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador_sucursaldb')
                ->where('IdCliente', $clienteId)
                ->count();
            \Log::info('Operadores en todos_operador_sucursaldb para cliente ' . $clienteId . ': ' . $operadoresEnSucursal);
        }
        
        // ESTADÍSTICAS
        $totalVentas = Venta::where('IdCliente', $clienteId)->count();
        $totalValidas = Venta::where('IdCliente', $clienteId)
            ->where('IdEstado', VentaEstado::VALIDA)
            ->count();
        $totalAnuladas = Venta::where('IdCliente', $clienteId)
            ->where('IdEstado', VentaEstado::ANULADA)
            ->count();
        
        return Inertia::render('Gestion/Impuestos/VentaEditar/Index', [
            'ventas' => $ventas,
            'estados' => $estados,
            'sucursales' => $sucursales,
            'vendedores' => $vendedores,
            'estadisticas' => [
                'total' => $totalVentas,
                'validas' => $totalValidas,
                'anuladas' => $totalAnuladas,
            ],
            'filtros' => [
                'sucursal_id' => $request->sucursal_id,
                'vendedor_id' => $request->vendedor_id,
                'estado' => $request->estado,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * ✏️ FORMULARIO DE EDICIÓN DE VENTA
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        \Log::info('=== EDIT VENTA ===');
        \Log::info('ID recibido: ' . $id);
        \Log::info('Cliente ID: ' . $clienteId);
        \Log::info('Sucursal ID: ' . $sucursalId);
        
        // 🔥 PRIMERO: Buscar la venta sin filtrar por sucursal (SOLO PARA DEPURAR)
        $venta = Venta::where('IdCliente', $clienteId)
            ->with(['detalles'])
            ->find($id);
        
        // 🔥 Si no la encuentra, buscar sin filtrar por cliente (SOLO PARA DEPURAR)
        if (!$venta) {
            \Log::warning('⚠️ Venta no encontrada para el cliente, buscando sin filtro de cliente');
            
            $venta = Venta::with(['detalles'])->find($id);
            
            if ($venta) {
                \Log::info('✅ Venta encontrada sin filtro de cliente:', [
                    'id' => $venta->IdVentas,
                    'cliente_id' => $venta->IdCliente,
                    'sucursal_id' => $venta->IdClienteSucursal
                ]);
            }
        }
        
        // 🔥 Si no encuentra la venta, redirigir con mensaje claro
        if (!$venta) {
            \Log::error('❌ Venta NO encontrada con ID: ' . $id);
            
            // 🔥 Redirigir al grid con mensaje de error
            return redirect()->route('gestion.ventas-editar.index')
                ->with('error', 'Venta no encontrada. ID: ' . $id . ' - Verifica que la venta exista y pertenezca a tu cliente.');
        }
        
        // =============================================
        // ENRIQUECER DATOS DE LA VENTA
        // =============================================
        // Cliente (NIT)
        $cliente = Identificador::find($venta->IdNIT);
        $venta->cliente_nit = $cliente ? $cliente->CI_NIT : '0';
        $venta->cliente_nombre = $cliente ? $cliente->Nombre : 'CONSUMIDOR FINAL';
        
        // Estado
        $estado = VentaEstado::find($venta->IdEstado);
        $venta->estado_nombre = $estado ? $estado->Detalle : 'Desconocido';
        
        // Sucursal
        $sucursal = ClienteSucursal::find($venta->IdClienteSucursal);
        $venta->sucursal_nombre = $sucursal ? $sucursal->Nombre : 'Sin sucursal';
        
        // Vendedor
        $operador = Operador::with('identificador')->find($venta->IdOperadorIngresa);
        $venta->vendedor_nombre = $operador && $operador->identificador ? $operador->identificador->Nombre : 'Desconocido';
        
        // =============================================
        // ENRIQUECER DETALLES CON PRODUCTOS
        // =============================================
        foreach ($venta->detalles as $detalle) {
            $producto = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->first();
            
            $detalle->producto_nombre = $producto ? $producto->Detalle : 'Producto eliminado';
            $detalle->producto_codigo = $producto ? $producto->Codigo : '';
        }
        
        // =============================================
        // CATÁLOGOS PARA SELECTS
        // =============================================
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Detalle')
            ->get(['IdDetalleProducto as id', 'Detalle as nombre', 'Codigo', 'PrecioVenta']);
        
        $estados = VentaEstado::all();
        
        // 🔥 LOG PARA VERIFICAR DATOS
        \Log::info('✅ Datos preparados para la vista:', [
            'venta_id' => $venta->IdVentas,
            'detalles_count' => $venta->detalles->count(),
            'productos_count' => $productos->count()
        ]);
        
        // =============================================
        // ENVIAR A LA VISTA
        // =============================================
        return Inertia::render('Gestion/Impuestos/VentaEditar/Edit', [
            'venta' => $venta,
            'productos' => $productos,
            'estados' => $estados,
        ]);
    }

    /**
     * 💾 ACTUALIZAR VENTA - SOLO LA FECHA
     */
    public function update(Request $request, $id)
    {
    try {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        \Log::info('=== ACTUALIZANDO FECHA ===');
        \Log::info('Venta ID: ' . $id);
        \Log::info('Nueva fecha: ' . $request->FechaVenta);
        
        $request->validate([
            'FechaVenta' => 'required|date'
        ]);
        
        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
        
        // 🔥 BUSCAR SOLO POR ID (SIN FILTROS DE CLIENTE/SUCURSAL)
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $id)
            ->first();
        
        if (!$venta) {
            throw new \Exception('Venta no encontrada con ID: ' . $id);
        }
        
        // 🔥 ACTUALIZAR SOLO LA FECHA
        DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $id)
            ->update([
                'FechaVenta' => $request->FechaVenta,
                'IdOperadorActualiza' => $operadorId,
                'FechaUltimaActualizcion' => now(),
            ]);
        
        DB::connection('mysql_gestion_comercial_alimentos')->commit();
        
        \Log::info('✅ Fecha actualizada correctamente');
        
        return response()->json([
            'success' => true,
            'message' => 'Fecha actualizada correctamente'
        ]);
        
    } catch (\Exception $e) {
        DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
        \Log::error('❌ Error actualizando fecha: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * 🔥 Buscar productos para autocomplete (edición de ventas)
     */
    public function buscarProductos(Request $request)
    {
        try {
            $clienteId = session('cliente_id');
            $search = $request->search;
            
            if (!$search || strlen($search) < 2) {
                return response()->json([]);
            }
            
            $productos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario as p')
                ->leftJoin('inventario_menu_categoria as c', 'p.id_categoria', '=', 'c.id_categoria')
                ->where('p.IdCliente', $clienteId)
                ->where('p.ActivoInactivo', 0)
                ->where(function($q) use ($search) {
                    $q->where('p.Detalle', 'like', "%{$search}%")
                    ->orWhere('p.Codigo', 'like', "%{$search}%");
                })
                ->select(
                    'p.IdDetalleProducto as id',
                    'p.Detalle as nombre',
                    'p.Codigo as codigo',
                    'p.PrecioVenta as precio',
                    'c.nombre as categoria'
                )
                ->limit(10)
                ->get();
            
            return response()->json($productos);
            
        } catch (\Exception $e) {
            \Log::error('Error buscando productos: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * 🔥 REPROCESAR INVENTARIO - Busca SOLO por ID (sin filtros de sesión)
     */
    public function reprocesarInventario($id)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');
            
            \Log::info('=== REPROCESANDO INVENTARIO ===');
            \Log::info('Venta ID: ' . $id);
            
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            // =============================================
            // 1. OBTENER LA VENTA - SOLO POR ID (SIN FILTROS)
            // =============================================
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->first();
            
            if (!$venta) {
                \Log::error('❌ Venta no encontrada con ID: ' . $id);
                throw new \Exception('Venta no encontrada con ID: ' . $id);
            }
            
            \Log::info('✅ Venta encontrada:', [
                'IdVentas' => $venta->IdVentas,
                'NumeroFactura' => $venta->NumeroFactura,
                'IdCliente' => $venta->IdCliente,
                'IdClienteSucursal' => $venta->IdClienteSucursal,
                'FechaVenta' => $venta->FechaVenta
            ]);
            
            // =============================================
            // 2. OBTENER FECHA ANTERIOR
            // =============================================
            $fechaAnterior = date('d/m/Y H:i', strtotime($venta->FechaVenta));
            
            // =============================================
            // 3. ELIMINAR MOVIMIENTOS DE INVENTARIO ANTERIORES
            // =============================================
            $eliminados = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->where('IdTipoDeOperacion', 2)
                ->where('IdDocumento', $id)
                ->delete();
            
            \Log::info("🗑️ Eliminados {$eliminados} movimientos de inventario");
            
            // =============================================
            // 4. USAR LA FECHA ACTUAL DE LA VENTA
            // =============================================
            $fechaNueva = date('d/m/Y H:i', strtotime($venta->FechaVenta));
            $fechaVenta = date('Y-m-d', strtotime($venta->FechaVenta));
            
            // =============================================
            // 5. OBTENER NOMBRE DEL OPERADOR
            // =============================================
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $venta->IdOperadorIngresa)
                ->first();
            
            $nombreOperador = $operador ? $operador->Nombre : 'Desconocido';
            
            // =============================================
            // 6. DETERMINAR SI ES FACTURA O RECIBO
            // =============================================
            $reciboFactura = "Factura";
            
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $venta->IdClienteSucursal)
                ->first();
            
            $nitCero = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('CI_NIT', '99')
                ->value('IdIdentificador');
            
            if ($sucursal && $sucursal->ActivaInactivaR == 0 && $venta->IdNIT == $nitCero) {
                $reciboFactura = "Recibo";
            }
            
            \Log::info("Tipo de documento: {$reciboFactura}");
            
            // =============================================
            // 7. OBTENER ID DE FECHA
            // =============================================
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
                \Log::info("📅 Fecha creada: {$fechaVenta} (ID: {$idFecha})");
            }
            
            // =============================================
            // 8. OBTENER ALMACÉN PRINCIPAL
            // =============================================
            $idAlmacen = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_almacen')
                ->where('IdCliente', $venta->IdCliente)
                ->where('IdSucursal', $venta->IdClienteSucursal)
                ->where('AlmacenPrincipal', 1)
                ->value('IdAlmacen');
            
            if (!$idAlmacen) {
                $idAlmacen = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_almacen')
                    ->where('IdCliente', $venta->IdCliente)
                    ->where('IdSucursal', $venta->IdClienteSucursal)
                    ->value('IdAlmacen');
            }
            
            \Log::info("🏪 Almacén ID: {$idAlmacen}");
            
            $idTipoOperacion = 2;
            
            // =============================================
            // 9. PROCESAR DETALLES
            // =============================================
            $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $id)
                ->get();
            
            if ($detalles->isEmpty()) {
                \Log::warning('⚠️ La venta no tiene detalles');
            }
            
            $totalMovimientos = 0;
            $productosAfectados = [];
            
            foreach ($detalles as $detalle) {
                \Log::info("Procesando detalle:", [
                    'idrelacionventainventario' => $detalle->idrelacionventainventario,
                    'unidades' => $detalle->unidades
                ]);
                
                $productosPorcion = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_detalle')
                    ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                    ->get();
                
                if ($productosPorcion->isEmpty()) {
                    \Log::warning('⚠️ Producto sin composición:', [
                        'IdDetalleProducto' => $detalle->idrelacionventainventario
                    ]);
                }
                
                foreach ($productosPorcion as $porcion) {
                    $cantidadDescontar = $porcion->Porcion * $detalle->unidades;
                    
                    $precioCosto = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_productodetalle_precio_costo')
                        ->where('IdProducto', $porcion->IdProducto)
                        ->orderBy('IdPrecioCosto', 'DESC')
                        ->value('PrecioCosto');
                    
                    $costoTotal = $cantidadDescontar * ($precioCosto ?? 0);
                    
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_propiamente')
                        ->insert([
                            'IdTipoDeOperacion' => $idTipoOperacion,
                            'IdDocumento' => $id,
                            'IdFecha' => $idFecha,
                            'IdAlmacen' => $idAlmacen,
                            'IdProducto' => $porcion->IdProducto,
                            'Glosa' => "{$reciboFactura} Ventas No {$venta->NumeroFactura}; Op.{$nombreOperador}",
                            'D_H' => 'H',
                            'Unidades' => $cantidadDescontar,
                            'Bolivianos' => $costoTotal,
                            'IdCliente' => $venta->IdCliente,
                            'IdSucursal' => $venta->IdClienteSucursal,
                        ]);
                    
                    $totalMovimientos++;
                    
                    // Guardar productos afectados para el modal
                    $nombreProducto = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_productodetalle')
                        ->where('IdProducto', $porcion->IdProducto)
                        ->value('Descripcion');
                    
                    $productosAfectados[] = [
                        'id' => $porcion->IdProducto,
                        'nombre' => $nombreProducto ?? 'Producto #' . $porcion->IdProducto,
                        'cantidad' => $cantidadDescontar
                    ];
                }
            }
            
            \Log::info("✅ Insertados {$totalMovimientos} movimientos de inventario");
            
            // =============================================
            // 10. ACTUALIZAR VENTA (marcar como procesada)
            // =============================================
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->update([
                    'IdEstado' => 1,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaUltimaActualizcion' => now(),
                ]);
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            \Log::info('✅ REPROCESO COMPLETADO CON ÉXITO');
            
            return response()->json([
                'success' => true,
                'message' => '✅ La venta fue reprocesada correctamente',
                'movimientos' => $totalMovimientos,
                'fecha' => $fechaNueva,
                'detalles' => [
                    'eliminados' => $eliminados,
                    'insertados' => $totalMovimientos,
                    'fecha_anterior' => $fechaAnterior,
                    'fecha_nueva' => $fechaNueva,
                    'productos' => $productosAfectados
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            \Log::error('❌ Error reprocesando inventario: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }



}