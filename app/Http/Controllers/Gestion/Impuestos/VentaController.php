<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class VentaController extends Controller
{
    /**
     * 📋 GESTIÓN DE ESTADOS - Listado agrupado por sucursal (CONTROL INTERNO)
     * Solo para Super Usuario - Puede ver y activar facturas de todos los operadores
     */
    public function gestionEstado(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->leftJoin('todos_identificador as c', 'v.IdCliente', '=', 'c.IdIdentificador')
            ->leftJoin('todos_cliente_sucursal as s', 'v.IdClienteSucursal', '=', 's.IdClienteSucursal')
            ->leftJoin('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
            ->leftJoin('todos_identificador as oi', 'o.IdIdentificador', '=', 'oi.IdIdentificador')
            ->where('v.IdCliente', $clienteId);
        
        // 🔥 Filtro por sucursal
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('v.IdClienteSucursal', $request->sucursal_id);
        } else {
            $query->where('v.IdClienteSucursal', $sucursalId);
        }
        
        // 🔥 Filtro por operador (vendedor)
        if ($request->filled('operador_id') && $request->operador_id !== '') {
            $query->where('v.IdOperadorIngresa', $request->operador_id);
        }
        
        // 🔥 Filtro por estado
        if ($request->filled('estado') && $request->estado !== '') {
            if ($request->estado === 'activos') {
                $query->where('v.ActivoInactivo', 0);
            } elseif ($request->estado === 'inactivos') {
                $query->where('v.ActivoInactivo', 1);
            }
        }
        
        // 🔥 Filtro por número de factura
        if ($request->filled('buscar') && $request->buscar !== '') {
            $query->where('v.NumeroFactura', 'like', '%' . $request->buscar . '%');
        }
        
        // 🔥🔥🔥 SELECCIONAR EXPLÍCITAMENTE LOS CAMPOS
        $ventas = $query->select([
                'v.IdVentas',
                'v.NumeroFactura',
                'v.NumeroAutorizacion',
                'v.FechaVenta',
                'v.ImporteVenta',
                'v.ActivoInactivo as venta_activo',
                'v.LiquidadoVendedor',
                'v.IdClienteSucursal',
                'c.Nombre as cliente_nombre',
                's.Nombre as sucursal_nombre',
                'oi.Nombre as vendedor_nombre'
            ])
            ->orderBy('v.IdVentas', 'desc')
            ->paginate(20);
        
        // 🔥 TRANSFORMAR LOS DATOS
        $ventas->getCollection()->transform(function ($venta) {
            return [
                'IdVentas' => $venta->IdVentas,
                'NumeroFactura' => $venta->NumeroFactura,
                'NumeroAutorizacion' => $venta->NumeroAutorizacion,
                'FechaVenta' => $venta->FechaVenta,
                'ImporteVenta' => (float) $venta->ImporteVenta,
                'ActivoInactivo' => $venta->venta_activo,
                'LiquidadoVendedor' => $venta->LiquidadoVendedor,
                'cliente_nombre' => $venta->cliente_nombre ?? 'Sin cliente',
                'sucursal_nombre' => $venta->sucursal_nombre ?? 'Sin sucursal',
                'vendedor_nombre' => $venta->vendedor_nombre ?? 'Sin vendedor',
                'IdClienteSucursal' => $venta->IdClienteSucursal,
            ];
        });
        
        // 🔥 Obtener sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // 🔥🔥🔥 OBTENER OPERADORES FILTRADOS POR SUCURSAL - CORREGIDO 🔥🔥🔥
        $sucursalFiltro = $request->filled('sucursal_id') ? $request->sucursal_id : $sucursalId;
        
        $operadores = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->join('todos_identificador', 'todos_operador.IdIdentificador', '=', 'todos_identificador.IdIdentificador')
            ->join('todos_operador_sucursaldb', 'todos_operador.IdOperador', '=', 'todos_operador_sucursaldb.IdOperador')
            ->where('todos_operador.ActivoInactivo', 0)  // 🔥🔥🔥 CAMBIADO DE 1 a 0
            ->where('todos_operador_sucursaldb.IdCliente', $clienteId)
            ->where('todos_operador_sucursaldb.IdSucursal', $sucursalFiltro)
            ->select(
                'todos_operador.IdOperador as id',
                DB::raw("CONCAT(todos_identificador.CI_NIT, ' - ', todos_identificador.Nombre) as nombre_completo")
            )
            ->orderBy('todos_identificador.Nombre')
            ->get();
        
        return Inertia::render('Gestion/Impuestos/Ventas/GestionEstado', [
            'ventas' => $ventas,
            'sucursales' => $sucursales,
            'operadores' => $operadores,
            'sucursalActual' => $sucursalId,
            'sucursalSeleccionada' => $request->sucursal_id,
            'filtroEstado' => $request->estado,
            'filtroOperador' => $request->operador_id,
            'buscar' => $request->buscar,
        ]);
    }
    
    /**
     * 🔥 CAMBIAR ESTADO DE FACTURA (Solo de Inactivo a Activo)
     * Para Super Usuario en Control Interno
     */
    public function cambiarEstado(Request $request, $id)
    {
        try {
            $clienteId = session('cliente_id');
            $operadorId = session('operador_id');
            
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->where('IdCliente', $clienteId)
                ->first();
            
            if (!$venta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Venta no encontrada'
                ], 404);
            }
            
            // 🔥 Solo permitir si está INACTIVO (ActivoInactivo = 1)
            if ($venta->ActivoInactivo != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta factura ya está activa o no se puede modificar'
                ], 400);
            }
            
            // 🔥 No permitir si está liquidada
            if ($venta->LiquidadoVendedor > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta factura ya fue liquidada y no se puede modificar'
                ], 400);
            }
            
            // Cambiar estado a ACTIVO (0)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->update([
                    'ActivoInactivo' => 0,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaUltimaActualizcion' => now(),
                ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Factura activada correctamente. El operador ahora puede editarla.'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error cambiando estado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 📋 MIS FACTURAS - Solo las del operador logueado
     */
    public function misFacturas(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        $query = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->leftJoin('todos_identificador as c', 'v.IdCliente', '=', 'c.IdIdentificador')
            ->leftJoin('todos_cliente_sucursal as s', 'v.IdClienteSucursal', '=', 's.IdClienteSucursal')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->where('v.IdClienteSucursal', $sucursalId);
        
        // 🔥 FILTRO POR ESTADO
        if ($request->filled('estado') && $request->estado !== '') {
            if ($request->estado === 'activos') {
                $query->where('v.ActivoInactivo', 0);
            } elseif ($request->estado === 'inactivos') {
                $query->where('v.ActivoInactivo', 1);
            }
        } else {
            // 🔥 Por defecto, solo activas
            $query->where('v.ActivoInactivo', 0);
        }
        
        // 🔥 Filtro por número de factura
        if ($request->filled('buscar') && $request->buscar !== '') {
            $query->where('v.NumeroFactura', 'like', '%' . $request->buscar . '%');
        }
        
        // 🔥🔥🔥 SELECCIONAR EXPLÍCITAMENTE LOS CAMPOS 🔥🔥🔥
        $ventas = $query->select([
                'v.IdVentas',
                'v.NumeroFactura',
                'v.NumeroAutorizacion',
                'v.FechaVenta',
                'v.ImporteVenta',
                'v.ActivoInactivo',
                'v.LiquidadoVendedor',
                'v.IdClienteSucursal',
                'c.Nombre as cliente_nombre',
                's.Nombre as sucursal_nombre'
            ])
            ->orderBy('v.IdVentas', 'desc')
            ->paginate(20);
        
        // 🔥 TRANSFORMAR LOS DATOS PARA QUE TENGAN EL FORMATO CORRECTO
        $ventasTransformadas = $ventas->toArray();
        $ventasTransformadas['data'] = collect($ventas->items())->map(function ($venta) {
            // Verificar si tiene personalización
            $tienePersonalizacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $venta->IdVentas)
                ->whereNotNull('personalizacion')
                ->where('personalizacion', '!=', 'null')
                ->where('personalizacion', '!=', '[]')
                ->exists();
            
            return [
                'IdVentas' => $venta->IdVentas,
                'NumeroFactura' => $venta->NumeroFactura ?? 'N/A',
                'NumeroAutorizacion' => $venta->NumeroAutorizacion ?? '',
                'FechaVenta' => $venta->FechaVenta,
                'ImporteVenta' => (float) ($venta->ImporteVenta ?? 0),
                'ActivoInactivo' => (int) ($venta->ActivoInactivo ?? 0),
                'LiquidadoVendedor' => (float) ($venta->LiquidadoVendedor ?? 0),
                'cliente_nombre' => $venta->cliente_nombre ?? 'Sin cliente',
                'sucursal_nombre' => $venta->sucursal_nombre ?? 'Sin sucursal',
                'tiene_personalizacion' => $tienePersonalizacion,
            ];
        })->toArray();
        
        // 🔥 OBTENER NOMBRE DEL OPERADOR
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $operadorId)
            ->first();
        
        // 🔥 OBTENER NOMBRE DE LA SUCURSAL ACTUAL
        $sucursalActual = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $sucursalId)
            ->first();
        
        return Inertia::render('Gestion/Impuestos/Ventas/MisFacturas', [
            'ventas' => $ventasTransformadas, // 🔥 Usar los datos transformados
            'operadorNombre' => $operador->Nombre ?? 'Operador',
            'sucursalNombre' => $sucursalActual->Nombre ?? 'Actual',
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
        ]);
    }

    /**
     * ✏️ EDITAR OPCIONES DE FACTURA - SOLO DEL OPERADOR
     */
    public function editMisFacturas($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        // 🔥 VERIFICAR QUE LA FACTURA SEA DEL OPERADOR - CON VENDEDOR
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->leftJoin('todos_identificador as c', 'v.IdCliente', '=', 'c.IdIdentificador')
            ->leftJoin('todos_cliente_sucursal as s', 'v.IdClienteSucursal', '=', 's.IdClienteSucursal')
            ->leftJoin('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
            ->leftJoin('todos_identificador as oi', 'o.IdIdentificador', '=', 'oi.IdIdentificador')
            ->where('v.IdVentas', $id)
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->first([
                'v.*',
                'c.Nombre as cliente_nombre',
                'c.CI_NIT as cliente_nit',
                's.Nombre as sucursal_nombre',
                'oi.Nombre as vendedor_nombre'
            ]);
        
        if (!$venta) {
            return redirect()->route('gestion.mis-facturas')
                ->with('error', 'Factura no encontrada o no pertenece a este operador');
        }
        
        // 🔥 VERIFICAR QUE ESTÁ ACTIVA (ActivoInactivo = 0)
        if ($venta->ActivoInactivo != 0) {
            return redirect()->route('gestion.mis-facturas')
                ->with('error', 'Esta factura no está activa para editar');
        }
        
        // 🔥 VERIFICAR QUE NO ESTÁ LIQUIDADA
        if ($venta->LiquidadoVendedor > 0) {
            return redirect()->route('gestion.mis-facturas')
                ->with('error', 'Esta factura ya fue liquidada y no se puede editar');
        }
        
        // 🔥 OBTENER DETALLES CON PRODUCTOS
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle as d')
            ->leftJoin('inventario_relacion_ventainventario as r', 'd.idrelacionventainventario', '=', 'r.IdDetalleProducto')
            ->where('d.idventas', $id)
            ->get([
                'd.*',
                'r.Detalle as producto_nombre',
                'r.Codigo as producto_codigo',
                'r.PrecioVenta as producto_precio'
            ]);
        
        // 🔥 FORMATear DETALLES Y CARGAR OPCIONES
        $detallesFormateados = $detalles->map(function($detalle) use ($clienteId) {
            $personalizacion = null;
            if ($detalle->personalizacion && $detalle->personalizacion != 'null') {
                $personalizacion = json_decode($detalle->personalizacion, true);
            }
            
            // 🔥 VERIFICAR SI TIENE OPCIONES (combo/pack)
            $tieneOpciones = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_combo_opcion')
                ->where('id_producto_combo', $detalle->idrelacionventainventario)
                ->where('activo', 1)
                ->exists();
            
            $tieneComposicion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->exists();
            
            // 🔥 OBTENER COMPOSICIÓN
            $composicion = [];
            if ($tieneComposicion) {
                $composicionDetalles = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_detalle')
                    ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                    ->get();
                
                foreach ($composicionDetalles as $comp) {
                    $producto = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_productodetalle')
                        ->where('IdProducto', $comp->IdProducto)
                        ->first();
                    
                    $composicion[] = [
                        'id_producto' => $comp->IdProducto,
                        'nombre' => $producto->Descripcion ?? 'Producto',
                        'porcion' => (float) $comp->Porcion,
                    ];
                }
            }
            
            // 🔥 OBTENER OPCIONES DISPONIBLES
            $opcionesDisponibles = [];
            if ($tieneOpciones) {
                $opciones = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_combo_opcion')
                    ->where('id_producto_combo', $detalle->idrelacionventainventario)
                    ->where('activo', 1)
                    ->orderBy('orden')
                    ->get();
                
                foreach ($opciones as $opcion) {
                    $original = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_productodetalle')
                        ->where('IdProducto', $opcion->id_producto_original)
                        ->first();
                    
                    $sustituto = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_productodetalle')
                        ->where('IdProducto', $opcion->id_producto_sustituto)
                        ->first();
                    
                    $opcionesDisponibles[] = [
                        'id_combo_opcion' => $opcion->id_combo_opcion,
                        'id_producto_original' => $opcion->id_producto_original,
                        'nombre_original' => $original->Descripcion ?? 'Producto Original',
                        'id_producto_sustituto' => $opcion->id_producto_sustituto,
                        'nombre_sustituto' => $sustituto->Descripcion ?? 'Sustituto',
                        'codigo_sustituto' => $sustituto->Codigo ?? '',
                        'cantidad_maxima' => (int) ($opcion->cantidad ?? 1),
                    ];
                }
            }
            
            return [
                'IdDetalleVenta' => $detalle->idventasdetalle,
                'idrelacionventainventario' => $detalle->idrelacionventainventario,
                'producto_nombre' => $detalle->producto_nombre ?? 'Producto eliminado',
                'producto_codigo' => $detalle->producto_codigo ?? '',
                'producto_precio' => (float) ($detalle->producto_precio ?? 0),
                'unidades' => (float) $detalle->unidades,
                'preciounidades' => (float) $detalle->preciounidades,
                'totalbolivianos' => (float) $detalle->totalbolivianos,
                'personalizacion' => $personalizacion,
                'tiene_personalizacion' => !empty($personalizacion),
                'tiene_opciones' => $tieneOpciones,
                'tiene_composicion' => $tieneComposicion,
                'es_agrupado' => $tieneComposicion || $tieneOpciones,
                'composicion' => $composicion,
                'opciones_disponibles' => $opcionesDisponibles,
            ];
        });
        
        return Inertia::render('Gestion/Impuestos/Ventas/EditMisFactura', [
            'venta' => $venta,
            'detalles' => $detallesFormateados,
            'modo' => 'mis_facturas',
        ]);
    }
    /**
     * 💾 ACTUALIZAR FACTURA Y REPROCESAR INVENTARIO - SOLO DEL OPERADOR
     */
    public function updateMisFacturas(Request $request, $id)
    {
        try {
            $clienteId = session('cliente_id');
            $operadorId = session('operador_id');
            $sucursalId = session('cliente_sucursal_id');
            
            $request->validate([
                'detalles' => 'required|array',
                'detalles.*.idrelacionventainventario' => 'required|integer',
                'detalles.*.unidades' => 'required|numeric|min:0.01',
                'detalles.*.preciounidades' => 'required|numeric|min:0',
                'detalles.*.personalizacion' => 'nullable|array',
            ]);
            
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            // 🔥 VERIFICAR QUE SEA SU FACTURA
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->where('IdCliente', $clienteId)
                ->where('IdOperadorIngresa', $operadorId)
                ->first();
            
            if (!$venta) {
                throw new \Exception('Factura no encontrada o no pertenece a este operador');
            }
            
            if ($venta->ActivoInactivo != 0) {
                throw new \Exception('Esta factura no está activa para editar');
            }
            
            if ($venta->LiquidadoVendedor > 0) {
                throw new \Exception('Esta factura ya fue liquidada y no se puede modificar');
            }
            
            // 🔥 OBTENER SI SE QUIERE FINALIZAR
            $finalizar = $request->input('finalizar', false);
            
            // 🔥🔥🔥 OBTENER DETALLES EXISTENTES PARA MANTENER IDs 🔥🔥🔥
            $detallesExistentes = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $id)
                ->get()
                ->keyBy('idrelacionventainventario');
            
            // 🔥🔥🔥 ACTUALIZAR O INSERTAR DETALLES 🔥🔥🔥
            $totalVenta = 0;
            $idsDetallesActualizados = [];
            
            foreach ($request->detalles as $detalle) {
                $total = $detalle['unidades'] * $detalle['preciounidades'];
                $totalVenta += $total;
                
                $data = [
                    'idventas' => $id,
                    'IdVentaGrupo' => 0,
                    'idrelacionventainventario' => $detalle['idrelacionventainventario'],
                    'unidades' => $detalle['unidades'],
                    'preciounidades' => $detalle['preciounidades'],
                    'totalbolivianos' => $total,
                    'PorcentajeDescuento' => 0,
                    'Descuento' => 0,
                    'TotalBolivianosFacturado' => 0,
                    'entregado' => 0,
                ];
                
                // 🔥 GUARDAR PERSONALIZACIÓN
                if (isset($detalle['personalizacion']) && $detalle['personalizacion'] !== null) {
                    if (is_array($detalle['personalizacion'])) {
                        if (count($detalle['personalizacion']) > 0) {
                            $data['personalizacion'] = json_encode($detalle['personalizacion']);
                        } else {
                            $data['personalizacion'] = null;
                        }
                    } else {
                        $data['personalizacion'] = $detalle['personalizacion'];
                    }
                } else {
                    // Si no tiene personalización, verificar si es combo/pack
                    $tieneComposicion = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_relacion_ventainventario_detalle')
                        ->where('IdDetalleProducto', $detalle['idrelacionventainventario'])
                        ->exists();
                    
                    if ($tieneComposicion) {
                        $composicion = DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('inventario_relacion_ventainventario_detalle')
                            ->where('IdDetalleProducto', $detalle['idrelacionventainventario'])
                            ->get();
                        
                        $personalizacion = [];
                        $unidades = (int) $detalle['unidades'];
                        
                        for ($i = 0; $i < $unidades; $i++) {
                            $sustitutos = [];
                            foreach ($composicion as $comp) {
                                $sustitutos[] = [
                                    'id_producto_original' => $comp->IdProducto,
                                    'id_producto_sustituto' => $comp->IdProducto,
                                    'cantidad' => (int) $comp->Porcion
                                ];
                            }
                            $personalizacion[] = ['sustitutos' => $sustitutos];
                        }
                        
                        $data['personalizacion'] = json_encode($personalizacion);
                    }
                }
                
                // 🔥🔥🔥 BUSCAR SI YA EXISTE UN DETALLE CON ESTE PRODUCTO 🔥🔥🔥
                $detalleExistente = $detallesExistentes->get($detalle['idrelacionventainventario']);
                
                if ($detalleExistente) {
                    // 🔥 ACTUALIZAR DETALLE EXISTENTE
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_detalle')
                        ->where('idventasdetalle', $detalleExistente->idventasdetalle)
                        ->update($data);
                    
                    $idsDetallesActualizados[] = $detalleExistente->idventasdetalle;
                } else {
                    // 🔥 INSERTAR NUEVO DETALLE
                    $idNuevo = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_detalle')
                        ->insertGetId($data);
                    
                    $idsDetallesActualizados[] = $idNuevo;
                }
            }
            
            // 🔥🔥🔥 ELIMINAR DETALLES QUE YA NO EXISTEN 🔥🔥🔥
            if (!empty($idsDetallesActualizados)) {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->where('idventas', $id)
                    ->whereNotIn('idventasdetalle', $idsDetallesActualizados)
                    ->delete();
            } else {
                // Si no hay detalles, eliminar todos
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->where('idventas', $id)
                    ->delete();
            }
            
            // 🔥 ACTUALIZAR TOTAL DE LA VENTA
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->update([
                    'ImporteVenta' => $totalVenta,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaUltimaActualizcion' => now(),
                    'ActivoInactivo' => $finalizar ? 1 : 0,
                ]);
            
            // 🔥🔥🔥 REPROCESAR INVENTARIO DE LA FACTURA 🔥🔥🔥
            $this->reprocesarFacturaIndividual($id, $clienteId, $sucursalId, $operadorId);
            
            DB::connection('mysql_gestion_comercial_alimentos')->commit();
            
            return response()->json([
                'success' => true,
                'message' => $finalizar ? 'Factura finalizada correctamente' : 'Factura actualizada y reprocesada correctamente',
                'total' => $totalVenta,
                'finalizada' => $finalizar
            ]);
            
        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            \Log::error('Error actualizando factura: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * 🔥 REPROCESAR UNA SOLA FACTURA (para cuando se edita)
     * CORREGIDO: Lógica correcta de combos y personalización
     */
    private function reprocesarFacturaIndividual($idVentas, $clienteId, $sucursalId, $operadorId)
    {
        \Log::info('=== REPROCESANDO FACTURA INDIVIDUAL ===', [
            'id_ventas' => $idVentas,
            'cliente' => $clienteId,
            'sucursal' => $sucursalId,
            'operador' => $operadorId
        ]);
        
        // 🔥 OBTENER LA VENTA
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $idVentas)
            ->first();
        
        if (!$venta) {
            throw new \Exception('Venta no encontrada');
        }
        
        // 🔥 OBTENER TIPO DE OPERACIÓN "VENTAS"
        $idTipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('Detalle', 'Ventas')
            ->where('ActivoInactivo', 0)
            ->value('IdTipoOperacion');

        if (!$idTipoOperacion) {
            throw new \Exception('No se encontró el tipo de operación "Ventas"');
        }
        
        // 🔥🔥🔥 PASO 1: ELIMINAR TODOS LOS MOVIMIENTOS DE ESTA FACTURA 🔥🔥🔥
        $eliminados = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_propiamente')
            ->where('IdDocumento', $idVentas)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->delete();
        
        \Log::info('🗑️ Eliminados ' . $eliminados . ' movimientos de la factura');
        
        // 🔥 OBTENER FECHA
        $fechaVenta = date('Y-m-d', strtotime($venta->FechaVenta));
        
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
        
        // 🔥 OBTENER ALMACÉN
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
        
        // 🔥 OBTENER NOMBRE DEL OPERADOR
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $venta->IdOperadorIngresa)
            ->first();
        $nombreOperador = $operador ? $operador->Nombre : 'Desconocido';
        
        // 🔥 DETERMINAR FACTURA O RECIBO
        $reciboFactura = "Factura";
        $nitCero = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_identificador')
            ->where('CI_NIT', '99')
            ->value('IdIdentificador');
        
        if ($venta->IdNIT == $nitCero) {
            $reciboFactura = "Recibo";
        }
        
        // 🔥🔥🔥 PASO 2: OBTENER DETALLES ACTUALIZADOS 🔥🔥🔥
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle')
            ->where('idventas', $idVentas)
            ->get();
        
        // 🔥 CANASTA ACUMULADORA - productos que se descontarán
        $productosADescontar = [];
        
        foreach ($detalles as $detalle) {
            $unidadesDetalle = (float) $detalle->unidades;
            
            \Log::info('📦 Procesando detalle:', [
                'id' => $detalle->idrelacionventainventario,
                'unidades' => $unidadesDetalle,
                'personalizacion' => $detalle->personalizacion
            ]);
            
            // 🔥 OBTENER LA COMPOSICIÓN ORIGINAL DEL PRODUCTO
            $composicionOriginal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->get();
            
            if ($composicionOriginal->isEmpty()) {
                \Log::warning('⚠️ Producto sin composición, no se procesa en inventario');
                continue;
            }
            
            // 🔥 DECODIFICAR PERSONALIZACIÓN
            $personalizacion = null;
            if ($detalle->personalizacion && $detalle->personalizacion != 'null' && $detalle->personalizacion != '[]') {
                $personalizacion = json_decode($detalle->personalizacion, true);
            }
            
            // 🔥 Si tiene personalización, procesar los sustitutos
            if ($personalizacion && is_array($personalizacion) && count($personalizacion) > 0) {
                \Log::info('✅ Producto con personalización');
                
                // 🔥 Para cada combo (si hay 3 combos, hay 3 elementos en personalizacion)
                foreach ($personalizacion as $comboIndex => $comboData) {
                    $sustitutos = $comboData['sustitutos'] ?? [];
                    
                    // 🔥 Procesar cada sustituto del combo
                    foreach ($sustitutos as $sust) {
                        $idProducto = $sust['id_producto_sustituto'];
                        $cantidad = (float) ($sust['cantidad'] ?? 0);
                        
                        if ($cantidad > 0) {
                            if (!isset($productosADescontar[$idProducto])) {
                                $productosADescontar[$idProducto] = 0;
                            }
                            $productosADescontar[$idProducto] += $cantidad;
                        }
                    }
                }
            } else {
                // 🔥 SIN PERSONALIZACIÓN - usar composición original
                \Log::info('📦 Producto sin personalización, usando composición original');
                
                foreach ($composicionOriginal as $comp) {
                    $idProducto = $comp->IdProducto;
                    $cantidad = (float) $comp->Porcion * $unidadesDetalle;
                    
                    if (!isset($productosADescontar[$idProducto])) {
                        $productosADescontar[$idProducto] = 0;
                    }
                    $productosADescontar[$idProducto] += $cantidad;
                }
            }
        }
        
        // 🔥🔥🔥 PASO 3: CREAR NUEVOS MOVIMIENTOS 🔥🔥🔥
        \Log::info('📦 Productos a descontar:', $productosADescontar);
        
        foreach ($productosADescontar as $idProducto => $cantidadTotal) {
            if ($cantidadTotal <= 0) continue;
            
            $cantidadTotal = round($cantidadTotal, 2);
            
            $nombreProducto = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->where('IdProducto', $idProducto)
                ->value('Descripcion');
            
            $precioCosto = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle_precio_costo')
                ->where('IdProducto', $idProducto)
                ->orderBy('IdPrecioCosto', 'DESC')
                ->value('PrecioCosto');
            
            $precioCosto = (float) ($precioCosto ?? 0);
            $costoTotal = $cantidadTotal * $precioCosto;
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->insert([
                    'IdTipoDeOperacion' => $idTipoOperacion,
                    'IdDocumento' => $idVentas,
                    'IdFecha' => $idFecha,
                    'IdAlmacen' => $idAlmacen,
                    'IdProducto' => $idProducto,
                    'Glosa' => "{$reciboFactura} Ventas No {$venta->NumeroFactura}; Op.{$nombreOperador} (Editado)",
                    'D_H' => 'H',
                    'Unidades' => $cantidadTotal,
                    'Bolivianos' => $costoTotal,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                ]);
            
            \Log::info('✅ Descontado:', [
                'producto' => $nombreProducto ?? 'Producto #' . $idProducto,
                'unidades' => $cantidadTotal
            ]);
        }
        
        \Log::info('✅ Factura ' . $venta->NumeroFactura . ' reprocesada correctamente');
    }   
    /**
     * 🖨️ REIMPRIMIR FACTURA
     */
    public function reimprimir($id)
    {
        return app(\App\Http\Controllers\PuntoVenta\PagoVentaController::class)
            ->facturaPdf($id);
    }
    
    /**
     * 🔍 BUSCAR PRODUCTOS PARA AUTOCOMPLETE
     */
    public function buscarProductos(Request $request)
    {
        try {
            $clienteId = session('cliente_id');
            $termino = $request->get('q', '');
            
            if (strlen($termino) < 2) {
                return response()->json([]);
            }
            
            // 🔥 CORREGIDO - Usar solo inventario_relacion_ventainventario
            $productos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario as r')
                ->where('r.IdCliente', $clienteId)
                ->where('r.ActivoInactivo', 0)
                ->where(function($q) use ($termino) {
                    $q->where('r.Detalle', 'like', '%' . $termino . '%')
                    ->orWhere('r.Codigo', 'like', '%' . $termino . '%');
                })
                ->limit(20)
                ->get([
                    'r.IdDetalleProducto as id',
                    'r.Detalle as nombre',
                    'r.Codigo as codigo',
                    'r.PrecioVenta as precio'
                ]);
            
            return response()->json($productos);
            
        } catch (\Exception $e) {
            \Log::error('Error buscando productos: ' . $e->getMessage());
            return response()->json([]);
        }
    }
    
    /**
     * 🔥 OBTENER OPCIONES DE UN PRODUCTO (COMBO/PACK)
     */
    public function getOpcionesProducto($idProducto)
    {
        try {
            $clienteId = session('cliente_id');
            
            // Verificar si tiene opciones
            $opciones = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_combo_opcion')
                ->where('id_producto_combo', $idProducto)
                ->where('activo', 1)
                ->orderBy('orden')
                ->get();
            
            if ($opciones->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'tiene_opciones' => false,
                    'opciones' => []
                ]);
            }
            
            $opcionesFormateadas = [];
            foreach ($opciones as $opcion) {
                $original = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdProducto', $opcion->id_producto_original)
                    ->where('IdCliente', $clienteId)
                    ->first();
                
                $sustituto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdProducto', $opcion->id_producto_sustituto)
                    ->where('IdCliente', $clienteId)
                    ->first();
                
                $opcionesFormateadas[] = [
                    'id_combo_opcion' => $opcion->id_combo_opcion,
                    'id_producto_original' => $opcion->id_producto_original,
                    'nombre_original' => $original->Descripcion ?? 'Producto Original',
                    'id_producto_sustituto' => $opcion->id_producto_sustituto,
                    'nombre_sustituto' => $sustituto->Descripcion ?? 'Sustituto',
                    'codigo_sustituto' => $sustituto->Codigo ?? '',
                    'cantidad_maxima' => (int) ($opcion->cantidad ?? 1),
                ];
            }
            
            // Verificar si tiene composición
            $tieneComposicion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $idProducto)
                ->exists();
            
            return response()->json([
                'success' => true,
                'tiene_opciones' => true,
                'tiene_composicion' => $tieneComposicion,
                'opciones' => $opcionesFormateadas,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error obteniendo opciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 📊 ESTADÍSTICAS RÁPIDAS
     */
    public function estadisticas()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $totalVentas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->count();
        
        $totalHoy = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->whereDate('FechaVenta', now()->toDateString())
            ->count();
        
        $totalMonto = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->sum('ImporteVenta');
        
        return response()->json([
            'total' => $totalVentas,
            'hoy' => $totalHoy,
            'monto_total' => (float) $totalMonto,
        ]);
    }

    /**
     * 👁️ VER DETALLE DE FACTURA - SOLO DEL OPERADOR
     */
    public function showMisFacturas($id)
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');
        
        // 🔥 VERIFICAR QUE LA FACTURA SEA DEL OPERADOR
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->leftJoin('todos_identificador as c', 'v.IdCliente', '=', 'c.IdIdentificador')
            ->leftJoin('todos_cliente_sucursal as s', 'v.IdClienteSucursal', '=', 's.IdClienteSucursal')
            ->leftJoin('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
            ->leftJoin('todos_identificador as oi', 'o.IdIdentificador', '=', 'oi.IdIdentificador')
            ->where('v.IdVentas', $id)
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->first([
                'v.*',
                'c.Nombre as cliente_nombre',
                'c.CI_NIT as cliente_nit',
                's.Nombre as sucursal_nombre',
                'oi.Nombre as vendedor_nombre'
            ]);
        
        if (!$venta) {
            return redirect()->route('gestion.mis-facturas')
                ->with('error', 'Factura no encontrada o no pertenece a este operador');
        }
        
        // 🔥 OBTENER DETALLES CON PERSONALIZACIÓN
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle as d')
            ->leftJoin('inventario_relacion_ventainventario as r', 'd.idrelacionventainventario', '=', 'r.IdDetalleProducto')
            ->where('d.idventas', $id)
            ->get([
                'd.*',
                'r.Detalle as producto_nombre',
                'r.Codigo as producto_codigo',
                'r.PrecioVenta as producto_precio'
            ]);
        
        // 🔥 FORMATear DETALLES CON AGRUPACIÓN DE PERSONALIZACIÓN
        $detallesFormateados = $detalles->map(function($detalle) {
            $personalizacion = null;
            if ($detalle->personalizacion && $detalle->personalizacion != 'null') {
                $personalizacion = json_decode($detalle->personalizacion, true);
            }
            
            $tieneComposicion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->exists();
            
            $composicion = [];
            if ($tieneComposicion) {
                $composicionDetalles = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_detalle')
                    ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                    ->get();
                
                foreach ($composicionDetalles as $comp) {
                    $producto = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_productodetalle')
                        ->where('IdProducto', $comp->IdProducto)
                        ->first();
                    
                    $composicion[] = [
                        'id_producto' => $comp->IdProducto,
                        'nombre' => $producto->Descripcion ?? 'Producto',
                        'porcion' => (float) $comp->Porcion,
                    ];
                }
            }
            
            // 🔥 AGRUPAR PERSONALIZACIÓN POR PRODUCTO
            $personalizacionAgrupada = [];
            if ($personalizacion && is_array($personalizacion)) {
                $grupos = [];
                
                foreach ($personalizacion as $p) {
                    if (isset($p['sustitutos']) && is_array($p['sustitutos'])) {
                        foreach ($p['sustitutos'] as $sust) {
                            $key = $sust['id_producto_original'] . '-' . $sust['id_producto_sustituto'];
                            
                            if (!isset($grupos[$key])) {
                                $nombreSustituto = DB::connection('mysql_gestion_comercial_alimentos')
                                    ->table('inventario_productodetalle')
                                    ->where('IdProducto', $sust['id_producto_sustituto'])
                                    ->value('Descripcion');
                                
                                $nombreOriginal = DB::connection('mysql_gestion_comercial_alimentos')
                                    ->table('inventario_productodetalle')
                                    ->where('IdProducto', $sust['id_producto_original'])
                                    ->value('Descripcion');
                                
                                $grupos[$key] = [
                                    'id_producto_sustituto' => $sust['id_producto_sustituto'],
                                    'nombre_sustituto' => $nombreSustituto ?? 'Producto',
                                    'id_producto_original' => $sust['id_producto_original'],
                                    'nombre_original' => $nombreOriginal ?? 'Original',
                                    'cantidad_total' => 0,
                                ];
                            }
                            $grupos[$key]['cantidad_total'] += (int) ($sust['cantidad'] ?? 1);
                        }
                    }
                }
                
                $personalizacionAgrupada = array_values($grupos);
            }
            
            return [
                'IdDetalleVenta' => $detalle->idventasdetalle,
                'idrelacionventainventario' => $detalle->idrelacionventainventario,
                'producto_nombre' => $detalle->producto_nombre ?? 'Producto eliminado',
                'producto_codigo' => $detalle->producto_codigo ?? '',
                'producto_precio' => (float) ($detalle->producto_precio ?? 0),
                'unidades' => (float) $detalle->unidades,
                'preciounidades' => (float) $detalle->preciounidades,
                'totalbolivianos' => (float) $detalle->totalbolivianos,
                'personalizacion' => $personalizacionAgrupada,
                'tiene_personalizacion' => !empty($personalizacionAgrupada),
                'es_agrupado' => $tieneComposicion,
                'composicion' => $composicion,
            ];
        });
        
        return Inertia::render('Gestion/Impuestos/Ventas/ShowMisFactura', [
            'venta' => $venta,
            'detalles' => $detallesFormateados,
            'modo' => 'show',
        ]);
    }
    /**
     * 👁️ VER DETALLE DE FACTURA - SUPER USUARIO (Control Interno)
     */
    public function showGestionEstado($id)
    {
    $clienteId = session('cliente_id');
    
    // 🔥 OBTENER LA VENTA (sin filtrar por operador)
    $venta = DB::connection('mysql_gestion_comercial_alimentos')
        ->table('impuestos_ventas as v')
        ->leftJoin('todos_identificador as c', 'v.IdCliente', '=', 'c.IdIdentificador')
        ->leftJoin('todos_cliente_sucursal as s', 'v.IdClienteSucursal', '=', 's.IdClienteSucursal')
        ->leftJoin('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
        ->leftJoin('todos_identificador as oi', 'o.IdIdentificador', '=', 'oi.IdIdentificador')
        ->where('v.IdVentas', $id)
        ->where('v.IdCliente', $clienteId)
        ->first([
            'v.*',
            'c.Nombre as cliente_nombre',
            'c.CI_NIT as cliente_nit',
            's.Nombre as sucursal_nombre',
            'oi.Nombre as vendedor_nombre'
        ]);
    
    if (!$venta) {
        return redirect()->route('gestion.ventas.gestion-estado')
            ->with('error', 'Factura no encontrada');
    }
    
    // 🔥 OBTENER DETALLES CON PERSONALIZACIÓN
    $detalles = DB::connection('mysql_gestion_comercial_alimentos')
        ->table('impuestos_ventas_detalle as d')
        ->leftJoin('inventario_relacion_ventainventario as r', 'd.idrelacionventainventario', '=', 'r.IdDetalleProducto')
        ->where('d.idventas', $id)
        ->get([
            'd.*',
            'r.Detalle as producto_nombre',
            'r.Codigo as producto_codigo',
            'r.PrecioVenta as producto_precio'
        ]);
    
    // 🔥 FORMATear DETALLES CON AGRUPACIÓN DE PERSONALIZACIÓN
    $detallesFormateados = $detalles->map(function($detalle) {
        $personalizacion = null;
        if ($detalle->personalizacion && $detalle->personalizacion != 'null') {
            $personalizacion = json_decode($detalle->personalizacion, true);
        }
        
        $tieneComposicion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario_detalle')
            ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
            ->exists();
        
        $composicion = [];
        if ($tieneComposicion) {
            $composicionDetalles = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->get();
            
            foreach ($composicionDetalles as $comp) {
                $producto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle')
                    ->where('IdProducto', $comp->IdProducto)
                    ->first();
                
                $composicion[] = [
                    'id_producto' => $comp->IdProducto,
                    'nombre' => $producto->Descripcion ?? 'Producto',
                    'porcion' => (float) $comp->Porcion,
                ];
            }
        }
        
        // 🔥 AGRUPAR PERSONALIZACIÓN POR PRODUCTO
        $personalizacionAgrupada = [];
        if ($personalizacion && is_array($personalizacion)) {
            $grupos = [];
            
            foreach ($personalizacion as $p) {
                if (isset($p['sustitutos']) && is_array($p['sustitutos'])) {
                    foreach ($p['sustitutos'] as $sust) {
                        $key = $sust['id_producto_original'] . '-' . $sust['id_producto_sustituto'];
                        
                        if (!isset($grupos[$key])) {
                            $nombreSustituto = DB::connection('mysql_gestion_comercial_alimentos')
                                ->table('inventario_productodetalle')
                                ->where('IdProducto', $sust['id_producto_sustituto'])
                                ->value('Descripcion');
                            
                            $nombreOriginal = DB::connection('mysql_gestion_comercial_alimentos')
                                ->table('inventario_productodetalle')
                                ->where('IdProducto', $sust['id_producto_original'])
                                ->value('Descripcion');
                            
                            $grupos[$key] = [
                                'id_producto_sustituto' => $sust['id_producto_sustituto'],
                                'nombre_sustituto' => $nombreSustituto ?? 'Producto',
                                'id_producto_original' => $sust['id_producto_original'],
                                'nombre_original' => $nombreOriginal ?? 'Original',
                                'cantidad_total' => 0,
                            ];
                        }
                        $grupos[$key]['cantidad_total'] += (int) ($sust['cantidad'] ?? 1);
                    }
                }
            }
            
            $personalizacionAgrupada = array_values($grupos);
        }
        
        return [
            'IdDetalleVenta' => $detalle->idventasdetalle,
            'idrelacionventainventario' => $detalle->idrelacionventainventario,
            'producto_nombre' => $detalle->producto_nombre ?? 'Producto eliminado',
            'producto_codigo' => $detalle->producto_codigo ?? '',
            'producto_precio' => (float) ($detalle->producto_precio ?? 0),
            'unidades' => (float) $detalle->unidades,
            'preciounidades' => (float) $detalle->preciounidades,
            'totalbolivianos' => (float) $detalle->totalbolivianos,
            'personalizacion' => $personalizacionAgrupada,
            'tiene_personalizacion' => !empty($personalizacionAgrupada),
            'es_agrupado' => $tieneComposicion,
            'composicion' => $composicion,
        ];
    });
    
    return Inertia::render('Gestion/Impuestos/Ventas/ShowGestionEstado', [
        'venta' => $venta,
        'detalles' => $detallesFormateados,
        'modo' => 'show_gestion_estado',
    ]);
    }
    /**
     * 🔥 OBTENER DETALLE DE FACTURA PARA MODAL (Super Usuario)
     */
    public function getDetalleFacturaModal($id)
    {
        try {
            $clienteId = session('cliente_id');
            
            // =============================================
            // 1. OBTENER CABECERA DE LA VENTA
            // =============================================
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas as v')
                ->leftJoin('todos_identificador as c', 'v.IdCliente', '=', 'c.IdIdentificador')
                ->leftJoin('todos_cliente_sucursal as s', 'v.IdClienteSucursal', '=', 's.IdClienteSucursal')
                ->leftJoin('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
                ->leftJoin('todos_identificador as oi', 'o.IdIdentificador', '=', 'oi.IdIdentificador')
                ->where('v.IdVentas', $id)
                ->where('v.IdCliente', $clienteId)
                ->first([
                    'v.*',
                    'c.Nombre as cliente_nombre',
                    'c.CI_NIT as cliente_nit',
                    's.Nombre as sucursal_nombre',
                    'oi.Nombre as vendedor_nombre'
                ]);
            
            if (!$venta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Factura no encontrada'
                ], 404);
            }
            
            // =============================================
            // 2. OBTENER DETALLES CON PRODUCTOS
            // =============================================
            $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle as d')
                ->leftJoin('inventario_relacion_ventainventario as r', 'd.idrelacionventainventario', '=', 'r.IdDetalleProducto')
                ->where('d.idventas', $id)
                ->get([
                    'd.*',
                    'r.Detalle as producto_nombre',
                    'r.Codigo as producto_codigo',
                    'r.PrecioVenta as producto_precio'
                ]);
            
            // =============================================
            // 3. FORMATEAR DETALLES CON ORIGINALES Y SUSTITUTOS
            // =============================================
            $detallesFormateados = $detalles->map(function($detalle) use ($clienteId) {
                // 🔥 Decodificar personalización
                $personalizacion = null;
                $tienePersonalizacion = false;
                
                if ($detalle->personalizacion && $detalle->personalizacion != 'null' && $detalle->personalizacion != '[]') {
                    $personalizacion = json_decode($detalle->personalizacion, true);
                    $tienePersonalizacion = !empty($personalizacion) && is_array($personalizacion);
                }
                
                // 🔥 Unidades del detalle (cantidad de packs vendidos)
                $unidadesDetalle = (float) $detalle->unidades;
                
                // 🔥 Verificar si tiene composición
                $tieneComposicion = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_detalle')
                    ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                    ->exists();
                
                // 🔥 Obtener composición original
                $composicion = [];
                $mapaComposicion = []; // Para búsqueda rápida
                if ($tieneComposicion) {
                    $composicionDetalles = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_relacion_ventainventario_detalle')
                        ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                        ->get();
                    
                    foreach ($composicionDetalles as $comp) {
                        $producto = DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('inventario_productodetalle')
                            ->where('IdProducto', $comp->IdProducto)
                            ->first();
                        
                        $composicion[] = [
                            'id_producto' => $comp->IdProducto,
                            'nombre' => $producto->Descripcion ?? 'Producto',
                            'porcion' => (float) $comp->Porcion,
                        ];
                        $mapaComposicion[$comp->IdProducto] = [
                            'nombre' => $producto->Descripcion ?? 'Producto',
                            'porcion' => (float) $comp->Porcion
                        ];
                    }
                }
                
                // 🔥 CONSTRUIR MAPA DE SUSTITUTOS DESDE PERSONALIZACIÓN
                $sustitutosMap = [];
                if ($tienePersonalizacion && $personalizacion) {
                    foreach ($personalizacion as $combo) {
                        if (isset($combo['sustitutos']) && is_array($combo['sustitutos'])) {
                            foreach ($combo['sustitutos'] as $sust) {
                                $key = $sust['id_producto_original'];
                                $subKey = $sust['id_producto_sustituto'];
                                if (!isset($sustitutosMap[$key])) {
                                    $sustitutosMap[$key] = [];
                                }
                                $sustitutosMap[$key][$subKey] = ($sustitutosMap[$key][$subKey] ?? 0) + (float) ($sust['cantidad'] ?? 0);
                            }
                        }
                    }
                }
                
                // 🔥 CALCULAR PRODUCTOS DETALLE (ORIGINALES Y SUSTITUTOS)
                $productosDetalle = [];
                
                // 🔥 Obtener opciones disponibles
                $opcionesDisponibles = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_relacion_ventainventario_combo_opcion')
                    ->where('id_producto_combo', $detalle->idrelacionventainventario)
                    ->where('activo', 1)
                    ->get();
                
                // Crear mapa de sustitutos permitidos
                $sustitutosPermitidos = [];
                foreach ($opcionesDisponibles as $op) {
                    if ($op->id_producto_original != $op->id_producto_sustituto) {
                        $sustitutosPermitidos[$op->id_producto_original][] = $op->id_producto_sustituto;
                    }
                }
                
                // Procesar cada producto de la composición
                foreach ($composicion as $comp) {
                    $idOriginal = $comp['id_producto'];
                    $nombreOriginal = $comp['nombre'];
                    $cantidadTotal = $comp['porcion'] * $unidadesDetalle;
                    
                    // Verificar si tiene sustitutos
                    $sustitutosDelProducto = $sustitutosMap[$idOriginal] ?? [];
                    
                    if (empty($sustitutosDelProducto)) {
                        // 🔥 SIN SUSTITUTOS - Mostrar solo el original
                        if ($cantidadTotal > 0) {
                            $productosDetalle[] = [
                                'tipo' => 'original',
                                'id_producto' => $idOriginal,
                                'nombre' => $nombreOriginal,
                                'cantidad' => round($cantidadTotal, 2),
                                'icon' => '📦',
                                'color' => 'text-blue-600'
                            ];
                        }
                    } else {
                        // 🔥 CON SUSTITUTOS - Mostrar originales y sustitutos
                        $totalSustitutos = 0;
                        
                        // Primero, procesar los sustitutos
                        foreach ($sustitutosDelProducto as $idSustituto => $cantidadSustituto) {
                            if ($idSustituto == $idOriginal) continue; // Saltar el original
                            
                            $nombreSustituto = DB::connection('mysql_gestion_comercial_alimentos')
                                ->table('inventario_productodetalle')
                                ->where('IdProducto', $idSustituto)
                                ->value('Descripcion');
                            
                            if ($cantidadSustituto > 0) {
                                $productosDetalle[] = [
                                    'tipo' => 'sustituto',
                                    'id_producto' => $idSustituto,
                                    'nombre' => $nombreSustituto ?? 'Producto',
                                    'cantidad' => round($cantidadSustituto, 2),
                                    'icon' => '🔄',
                                    'color' => 'text-amber-600'
                                ];
                                $totalSustitutos += $cantidadSustituto;
                            }
                        }
                        
                        // Cantidad original restante
                        $cantidadOriginalRestante = $cantidadTotal - $totalSustitutos;
                        if ($cantidadOriginalRestante > 0) {
                            $productosDetalle[] = [
                                'tipo' => 'original',
                                'id_producto' => $idOriginal,
                                'nombre' => $nombreOriginal,
                                'cantidad' => round($cantidadOriginalRestante, 2),
                                'icon' => '📦',
                                'color' => 'text-blue-600'
                            ];
                        }
                    }
                }
                
                // 🔥 Si no hay composición, pero hay personalización (caso especial)
                if (empty($composicion) && $tienePersonalizacion) {
                    foreach ($personalizacion as $combo) {
                        if (isset($combo['sustitutos'])) {
                            foreach ($combo['sustitutos'] as $sust) {
                                $nombreSustituto = DB::connection('mysql_gestion_comercial_alimentos')
                                    ->table('inventario_productodetalle')
                                    ->where('IdProducto', $sust['id_producto_sustituto'])
                                    ->value('Descripcion');
                                
                                $productosDetalle[] = [
                                    'tipo' => 'sustituto',
                                    'id_producto' => $sust['id_producto_sustituto'],
                                    'nombre' => $nombreSustituto ?? 'Producto',
                                    'cantidad' => (float) ($sust['cantidad'] ?? 0) * $unidadesDetalle,
                                    'icon' => '🔄',
                                    'color' => 'text-amber-600'
                                ];
                            }
                        }
                    }
                }
                
                return [
                    'IdDetalleVenta' => $detalle->idventasdetalle,
                    'idrelacionventainventario' => $detalle->idrelacionventainventario,
                    'producto_nombre' => $detalle->producto_nombre ?? 'Producto eliminado',
                    'producto_codigo' => $detalle->producto_codigo ?? '',
                    'producto_precio' => (float) ($detalle->producto_precio ?? 0),
                    'unidades' => $unidadesDetalle,
                    'preciounidades' => (float) $detalle->preciounidades,
                    'totalbolivianos' => (float) $detalle->totalbolivianos,
                    'tiene_personalizacion' => $tienePersonalizacion,
                    'es_agrupado' => $tieneComposicion,
                    'productos_detalle' => $productosDetalle,
                    'composicion' => $composicion,
                    'personalizacion' => $personalizacion, // Para fallback
                ];
            });
            
            return response()->json([
                'success' => true,
                'venta' => $venta,
                'detalles' => $detallesFormateados
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error cargando detalle de factura: ' . $e->getMessage());
            \Log::error('Trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

}