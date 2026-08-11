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
            ->where('v.IdOperadorIngresa', $operadorId);
        
        // 🔥 Filtro por sucursal
        if ($request->filled('sucursal_id') && $request->sucursal_id !== '') {
            $query->where('v.IdClienteSucursal', $request->sucursal_id);
        } else {
            $query->where('v.IdClienteSucursal', $sucursalId);
        }
        
        // 🔥 Filtro por estado
        if ($request->filled('estado') && $request->estado !== '') {
            if ($request->estado === 'activos') {
                $query->where('v.ActivoInactivo', 0);
            } elseif ($request->estado === 'inactivos') {
                $query->where('v.ActivoInactivo', 1);
            }
        } else {
            // Por defecto, solo activas
            $query->where('v.ActivoInactivo', 0);
        }
        
        // 🔥 Filtro por número de factura
        if ($request->filled('buscar') && $request->buscar !== '') {
            $query->where('v.NumeroFactura', 'like', '%' . $request->buscar . '%');
        }
        
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
        
        // 🔥 Transformar datos y verificar si tiene personalización
        $ventas->getCollection()->transform(function ($venta) {
            // Verificar si tiene detalles con personalización
            $tienePersonalizacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $venta->IdVentas)
                ->whereNotNull('personalizacion')
                ->where('personalizacion', '!=', 'null')
                ->exists();
            
            return [
                'IdVentas' => $venta->IdVentas,
                'NumeroFactura' => $venta->NumeroFactura,
                'NumeroAutorizacion' => $venta->NumeroAutorizacion,
                'FechaVenta' => $venta->FechaVenta,
                'ImporteVenta' => (float) $venta->ImporteVenta,
                'ActivoInactivo' => $venta->ActivoInactivo,
                'LiquidadoVendedor' => $venta->LiquidadoVendedor,
                'cliente_nombre' => $venta->cliente_nombre ?? 'Sin cliente',
                'sucursal_nombre' => $venta->sucursal_nombre ?? 'Sin sucursal',
                'tiene_personalizacion' => $tienePersonalizacion,
            ];
        });
        
        // 🔥 Obtener sucursales del cliente
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        // 🔥 Obtener nombre del operador
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $operadorId)
            ->first();
        
        return Inertia::render('Gestion/Impuestos/Ventas/MisFacturas', [
            'ventas' => $ventas,
            'sucursales' => $sucursales,
            'sucursalActual' => $sucursalId,
            'operadorNombre' => $operador->Nombre ?? 'Operador',
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
            
            // 🔥 ELIMINAR DETALLES EXISTENTES
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle')
                ->where('idventas', $id)
                ->delete();
            
            // 🔥 INSERTAR NUEVOS DETALLES
            $totalVenta = 0;
            
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
                
                // 🔥 GUARDAR PERSONALIZACIÓN SI EXISTE
                if (!empty($detalle['personalizacion']) && is_array($detalle['personalizacion'])) {
                    $data['personalizacion'] = json_encode($detalle['personalizacion']);
                } else {
                    // 🔥 Si no tiene personalización, verificar si es combo/pack
                    $tieneComposicion = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('inventario_relacion_ventainventario_detalle')
                        ->where('IdDetalleProducto', $detalle['idrelacionventainventario'])
                        ->exists();
                    
                    if ($tieneComposicion) {
                        // 🔥 CREAR PERSONALIZACIÓN AUTOMÁTICA CON PRODUCTOS ORIGINALES
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
                        
                        \Log::info('🔄 Personalización creada automáticamente para producto:', [
                            'id' => $detalle['idrelacionventainventario'],
                            'unidades' => $unidades
                        ]);
                    }
                }
                
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_detalle')
                    ->insert($data);
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
            
            // 🔥🔥🔥 REPROCESAR INVENTARIO DE LA FACTURA (SOLO ESTA)
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
            $idTipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdCliente', $clienteId)
                ->where(DB::raw('UPPER(Detalle)'), 'VENTAS')
                ->where('ActivoInactivo', 0)
                ->value('IdTipoOperacion');
        }

        if (!$idTipoOperacion) {
            throw new \Exception('No se encontró el tipo de operación "Ventas"');
        }
        
        // 🔥🔥🔥 PASO 1: ELIMINAR TODOS LOS MOVIMIENTOS VIEJOS 🔥🔥🔥
        $eliminados = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_propiamente')
            ->where('IdDocumento', $idVentas)
            ->where('IdCliente', $clienteId)
            ->where('IdTipoDeOperacion', $idTipoOperacion)
            ->delete();
        
        \Log::info('🗑️ Eliminados ' . $eliminados . ' movimientos viejos de VENTAS');
        
        // 🔥 También eliminar movimientos de ANULACIÓN si existen
        $idTipoAnulacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('Detalle', 'Anulación Venta')
            ->where('ActivoInactivo', 0)
            ->value('IdTipoOperacion');

        if ($idTipoAnulacion) {
            $eliminadosAnulacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->where('IdDocumento', $idVentas)
                ->where('IdCliente', $clienteId)
                ->where('IdTipoDeOperacion', $idTipoAnulacion)
                ->delete();
            
            \Log::info('🗑️ Eliminados ' . $eliminadosAnulacion . ' movimientos de ANULACIÓN');
        }
        
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
        
        // 🔥 CANASTA ACUMULADORA
        $productosADescontar = [];
        
        foreach ($detalles as $detalle) {
            \Log::info('📦 Procesando detalle:', [
                'id' => $detalle->idrelacionventainventario,
                'unidades' => $detalle->unidades,
                'personalizacion' => $detalle->personalizacion
            ]);
            
            $unidadesDetalle = floatval($detalle->unidades);
            
            // 🔥 VERIFICAR SI TIENE PERSONALIZACIÓN
            $tienePersonalizacion = false;
            $personalizacion = null;
            
            if ($detalle->personalizacion && $detalle->personalizacion != 'null' && $detalle->personalizacion != '[]') {
                $personalizacion = json_decode($detalle->personalizacion, true);
                $tienePersonalizacion = !empty($personalizacion) && is_array($personalizacion);
            }
            
            // 🔥 OBTENER LA COMPOSICIÓN ORIGINAL
            $composicionOriginal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->get()
                ->keyBy('IdProducto');
            
            // 🔥 SI TIENE PERSONALIZACIÓN
            if ($tienePersonalizacion && $personalizacion) {
                \Log::info('✅ Producto con personalización');
                
                foreach ($personalizacion as $index => $p) {
                    if (!is_array($p)) continue;
                    
                    $sustitutos = isset($p['sustitutos']) && is_array($p['sustitutos']) ? $p['sustitutos'] : [];
                    
                    // 🔥 Procesar cada producto de la composición original
                    foreach ($composicionOriginal as $idProductoOriginal => $composicion) {
                        $cantidadOriginal = floatval($composicion->Porcion);
                        
                        // 🔥 Calcular cuánto se reemplazó
                        $totalReemplazado = 0.0;
                        foreach ($sustitutos as $sustituto) {
                            if (isset($sustituto['id_producto_original']) && $sustituto['id_producto_original'] == $idProductoOriginal) {
                                $totalReemplazado += floatval($sustituto['cantidad'] ?? 0);
                            }
                        }
                        
                        // 🔥 Productos originales que quedan (NO reemplazados)
                        $quedanOriginales = $cantidadOriginal - $totalReemplazado;
                        if ($quedanOriginales > 0) {
                            $cantidad = $quedanOriginales * $unidadesDetalle;
                            if (!isset($productosADescontar[$idProductoOriginal])) {
                                $productosADescontar[$idProductoOriginal] = 0.0;
                            }
                            $productosADescontar[$idProductoOriginal] += floatval($cantidad);
                        }
                        
                        // 🔥 Sustitutos (productos que se agregaron en reemplazo)
                        foreach ($sustitutos as $sustituto) {
                            if (isset($sustituto['id_producto_original']) && $sustituto['id_producto_original'] == $idProductoOriginal) {
                                $idSustituto = $sustituto['id_producto_sustituto'] ?? null;
                                $cantidadSustituto = floatval($sustituto['cantidad'] ?? 0);
                                
                                if ($idSustituto && $cantidadSustituto > 0) {
                                    $cantidad = $cantidadSustituto * $unidadesDetalle;
                                    if (!isset($productosADescontar[$idSustituto])) {
                                        $productosADescontar[$idSustituto] = 0.0;
                                    }
                                    $productosADescontar[$idSustituto] += floatval($cantidad);
                                }
                            }
                        }
                    }
                }
                
            } else {
                // 🔥 SIN PERSONALIZACIÓN - COMPOSICIÓN NORMAL
                \Log::info('📦 Producto sin personalización');
                
                foreach ($composicionOriginal as $idProductoOriginal => $composicion) {
                    $cantidad = floatval($composicion->Porcion) * $unidadesDetalle;
                    if (!isset($productosADescontar[$idProductoOriginal])) {
                        $productosADescontar[$idProductoOriginal] = 0.0;
                    }
                    $productosADescontar[$idProductoOriginal] += floatval($cantidad);
                }
            }
        }
        
        // 🔥🔥🔥 PASO 3: CREAR NUEVOS MOVIMIENTOS 🔥🔥🔥
        \Log::info('📦 Nuevos productos a descontar:', $productosADescontar);
        
        foreach ($productosADescontar as $idProducto => $cantidadTotal) {
            $cantidadTotal = floatval($cantidadTotal);
            
            if ($cantidadTotal <= 0) continue;
            
            // Redondear a 2 decimales
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
            
            $precioCosto = floatval($precioCosto ?? 0);
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
                'unidades' => $cantidadTotal,
                'costo_total' => $costoTotal
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
            // 2. OBTENER DETALLES
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
                
                // 🔥 CALCULAR ORIGINALES Y SUSTITUTOS
                $productosDetalle = [];
                
                // Para cada producto en la composición
                foreach ($composicion as $comp) {
                    $idProducto = $comp['id_producto'];
                    $nombreProducto = $comp['nombre'];
                    $cantidadTotal = $comp['porcion'] * $unidadesDetalle;
                    
                    // Buscar si este producto fue reemplazado
                    $cantidadSustituto = 0;
                    $nombreSustituto = null;
                    $idSustituto = null;
                    
                    if ($tienePersonalizacion && $personalizacion) {
                        foreach ($personalizacion as $p) {
                            if (isset($p['sustitutos']) && is_array($p['sustitutos'])) {
                                foreach ($p['sustitutos'] as $sust) {
                                    if ($sust['id_producto_original'] == $idProducto) {
                                        $cantidadSustituto += (float) ($sust['cantidad'] ?? 0);
                                        $idSustituto = $sust['id_producto_sustituto'];
                                        
                                        // Obtener nombre del sustituto
                                        if ($idSustituto && $idSustituto != $idProducto) {
                                            $nombreSustituto = DB::connection('mysql_gestion_comercial_alimentos')
                                                ->table('inventario_productodetalle')
                                                ->where('IdProducto', $idSustituto)
                                                ->value('Descripcion');
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // 🔥 Calcular cantidad original (la que NO fue reemplazada)
                    $cantidadOriginal = $cantidadTotal - $cantidadSustituto;
                    
                    // 🔥 AGREGAR PRODUCTO ORIGINAL (si queda)
                    if ($cantidadOriginal > 0) {
                        $productosDetalle[] = [
                            'tipo' => 'original',
                            'id_producto' => $idProducto,
                            'nombre' => $nombreProducto,
                            'cantidad' => $cantidadOriginal,
                            'icon' => '📦',
                            'color' => 'text-blue-600'
                        ];
                    }
                    
                    // 🔥 AGREGAR SUSTITUTO (si existe)
                    if ($cantidadSustituto > 0 && $idSustituto && $idSustituto != $idProducto) {
                        $productosDetalle[] = [
                            'tipo' => 'sustituto',
                            'id_producto' => $idSustituto,
                            'nombre' => $nombreSustituto ?? 'Producto',
                            'cantidad' => $cantidadSustituto,
                            'icon' => '🔄',
                            'color' => 'text-amber-600'
                        ];
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
                ];
            });
            
            return response()->json([
                'success' => true,
                'venta' => $venta,
                'detalles' => $detallesFormateados
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error cargando detalle de factura: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

}