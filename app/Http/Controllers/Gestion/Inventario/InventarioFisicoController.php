<?php
// app/Http/Controllers/Gestion/Inventario/InventarioFisicoController.php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\InventarioFisico;
use App\Models\Gestion\Inventario\InventarioFisicoDetalle;
use App\Models\Gestion\Inventario\InventarioPropiamente;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Todos\Fecha;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Todos\Identificador;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventarioFisicoController extends Controller
{
    const TIPO_OPERACION_AJUSTE = 22;

    /**
     * Entrada principal - Crea un borrador (NumeroCorrelativo = 0)
     */
    public function create()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        if (!$clienteId || !$operadorId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debe seleccionar una empresa primero');
        }

        // Buscar borrador existente
        $borrador = InventarioFisico::where('IdCliente', $clienteId)
            ->where('IdOperador', $operadorId)
            ->where('ActivoInactivo', 0)
            ->where('NumeroCorrelativo', 0)
            ->first();

        if ($borrador) {
            return redirect()->route('gestion.inventario-fisico.edit', $borrador->IdFisico)
                ->with('info', 'Continúe con el inventario físico en progreso');
        }

        // 🔥 CORREGIDO: Crear borrador con valores por defecto (0 para campos NOT NULL)
        $inventarioFisico = InventarioFisico::create([
            'NumeroCorrelativo' => 0,
            'IdFecha' => 0,  // Valor temporal, se actualizará en edición
            'IdAlmacen' => 0,  // Valor temporal
            'IdRealizadoPor' => 0,  // Valor temporal
            'IdEncargadoSucursal' => 0,  // Valor temporal
            'Observacion' => '',
            'ActivoInactivo' => 0,
            'IdCliente' => $clienteId,
            'IdSucursal' => $sucursalId ?? 0,
            'IdOperador' => $operadorId,
        ]);

        return redirect()->route('gestion.inventario-fisico.edit', $inventarioFisico->IdFisico);
    }

    /**
     * Editar inventario físico
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');

        $inventarioFisico = InventarioFisico::where('IdCliente', $clienteId)
            ->with(['fecha', 'sucursal', 'almacen', 'realizadoPor', 'encargadoSucursal'])
            ->findOrFail($id);
        
        $esBorrador = $inventarioFisico->NumeroCorrelativo == 0;
        $esContabilizado = $inventarioFisico->NumeroCorrelativo > 0;

        // Cargar detalles
        $detalles = InventarioFisicoDetalle::where('IdFisico', $id)
            ->with('producto')
            ->orderBy('IdFisicoPropiamente')
            ->get()
            ->map(function($detalle) {
                return [
                    'IdFisicoPropiamente' => $detalle->IdFisicoPropiamente,
                    'IdProducto' => $detalle->IdProducto,
                    'Codigo' => $detalle->producto?->Codigo,
                    'Descripcion' => $detalle->producto?->Descripcion,
                    'UnidadesSaldo' => (float) $detalle->UnidadesSaldo,
                    'Unidades' => (float) $detalle->Unidades,
                    'UnidadesAjuste' => (float) $detalle->UnidadesAjuste,
                ];
            });

        // Fechas (solo las que tienen IdFecha > 0 para evitar mostrar el 0)
        $fechas = Fecha::where('IdFecha', '>', 0)
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha as id', DB::raw("DATE_FORMAT(Fecha, '%d-%m-%Y') as fecha_display")]);

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        $identificadores = Identificador::orderBy('CI_NIT')
            ->get(['IdIdentificador as id', 'CI_NIT', 'Nombre'])
            ->map(fn($i) => ['id' => $i->id, 'texto' => "{$i->CI_NIT} - {$i->Nombre}"]);

        return Inertia::render('Gestion/Inventario/InventarioFisico/Create', [
            'inventarioFisico' => $inventarioFisico,
            'detalles' => $detalles,
            'fechas' => $fechas,
            'sucursales' => $sucursales,
            'identificadores' => $identificadores,
            'esBorrador' => $esBorrador,
            'esContabilizado' => $esContabilizado,
        ]);
    }

    /**
     * Guardar cabecera
     */
    public function updateCabecera(Request $request, $id)
    {
        $request->validate([
            'IdFecha' => 'required|exists:todos_fecha,IdFecha',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'IdAlmacen' => 'required|exists:inventario_almacen,IdAlmacen',
            'IdRealizadoPor' => 'required|exists:todos_identificador,IdIdentificador',
            'IdEncargadoSucursal' => 'required|exists:todos_identificador,IdIdentificador',
            'Observacion' => 'nullable|string',
        ]);

        $inventarioFisico = InventarioFisico::findOrFail($id);
        
        if ($inventarioFisico->NumeroCorrelativo != 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar un inventario ya contabilizado'
            ], 400);
        }
        
        $inventarioFisico->update([
            'IdFecha' => $request->IdFecha,
            'IdSucursal' => $request->IdSucursal,
            'IdAlmacen' => $request->IdAlmacen,
            'IdRealizadoPor' => $request->IdRealizadoPor,
            'IdEncargadoSucursal' => $request->IdEncargadoSucursal,
            'Observacion' => $request->Observacion,
        ]);

        return response()->json(['success' => true, 'message' => 'Cabecera guardada correctamente']);
    }

    /**
     * Obtener almacenes por sucursal (AJAX)
     */
    public function getAlmacenes($sucursalId)
    {
        $clienteId = session('cliente_id');
        $almacenes = Almacen::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->orderBy('Almacen')
            ->get(['IdAlmacen as id', 'Almacen as nombre']);
        
        return response()->json($almacenes);
    }

    /**
     * Sincronizar productos (ONAFTERINSERT / ACTUALIZA)
     */
    public function sincronizarProductos($id)
    {
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $inventarioFisico = InventarioFisico::findOrFail($id);
            
            if ($inventarioFisico->NumeroCorrelativo != 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede sincronizar un inventario ya contabilizado'
                ], 400);
            }
            
            $fechaId = $inventarioFisico->IdFecha;
            $sucursalId = $inventarioFisico->IdSucursal;
            $clienteId = $inventarioFisico->IdCliente;

            // Eliminar registros basura (Unidades = 0)
            InventarioFisicoDetalle::where('IdFisico', $id)
                ->where('Unidades', 0)
                ->delete();

            // Eliminar registros anteriores del mismo documento
            InventarioPropiamente::where('IdTipoDeOperacion', self::TIPO_OPERACION_AJUSTE)
                ->where('IdDocumento', $id)
                ->delete();

            // Resetear UnidadesAjuste y UnidadesSaldo
            InventarioFisicoDetalle::where('IdFisico', $id)
                ->update(['UnidadesAjuste' => 0, 'UnidadesSaldo' => 0]);

            // Recorrer todos los productos
            $todosProductos = ProductoDetalle::where('IdCliente', $clienteId)
                ->orderBy('Descripcion')
                ->get();

            foreach ($todosProductos as $producto) {
                // Calcular saldo actual
                $saldoCalculado = InventarioPropiamente::where('IdProducto', $producto->IdProducto)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdFecha', '<=', $fechaId)
                    ->where(function($q) use ($id) {
                        $q->where('IdTipoDeOperacion', '!=', self::TIPO_OPERACION_AJUSTE)
                          ->orWhere('IdDocumento', '!=', $id);
                    })
                    ->selectRaw("COALESCE(SUM(CASE D_H WHEN 'D' THEN Unidades WHEN 'H' THEN -Unidades ELSE 0 END), 0) as saldo")
                    ->value('saldo') ?? 0;

                $detalleExistente = InventarioFisicoDetalle::where('IdFisico', $id)
                    ->where('IdProducto', $producto->IdProducto)
                    ->first();

                $unidadesExistentes = $detalleExistente ? (float) $detalleExistente->Unidades : 0;

                // Regla de negocio
                $debeEstarEnDetalle = false;
                if ($producto->ActivoInactivo == 0) {
                    $debeEstarEnDetalle = true;
                } else {
                    if ($saldoCalculado != 0 || $unidadesExistentes > 0) {
                        $debeEstarEnDetalle = true;
                    }
                }

                if ($debeEstarEnDetalle) {
                    if ($detalleExistente) {
                        $detalleExistente->update(['UnidadesSaldo' => $saldoCalculado]);
                    } else {
                        InventarioFisicoDetalle::create([
                            'IdFisico' => $id,
                            'IdProducto' => $producto->IdProducto,
                            'UnidadesSaldo' => $saldoCalculado,
                            'Unidades' => 0,
                            'UnidadesAjuste' => 0,
                        ]);
                    }
                } else {
                    if ($detalleExistente) {
                        $detalleExistente->delete();
                    }
                }
            }

            // Recalcular UnidadesAjuste
            $detalles = InventarioFisicoDetalle::where('IdFisico', $id)->get();
            foreach ($detalles as $detalle) {
                $ajuste = $detalle->Unidades - $detalle->UnidadesSaldo;
                $detalle->update(['UnidadesAjuste' => $ajuste]);
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Productos sincronizados correctamente'
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error sincronizando productos: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar unidades de un producto (con cálculo de ajuste)
     */
    public function actualizarUnidades(Request $request, $id, $detalleId)
    {
        $request->validate(['Unidades' => 'required|numeric|min:0']);

        $inventarioFisico = InventarioFisico::findOrFail($id);
        
        if ($inventarioFisico->NumeroCorrelativo != 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar un inventario ya contabilizado'
            ], 400);
        }

        $detalle = InventarioFisicoDetalle::where('IdFisico', $id)
            ->where('IdFisicoPropiamente', $detalleId)
            ->firstOrFail();

        $ajuste = $request->Unidades - $detalle->UnidadesSaldo;
        
        $detalle->update([
            'Unidades' => $request->Unidades,
            'UnidadesAjuste' => $ajuste,
        ]);

        return response()->json([
            'success' => true,
            'unidades_ajuste' => $ajuste
        ]);
    }

    /**
     * CONTABILIZAR (como Scriptcase)
     */
    public function contabilizar($id)
    {
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $inventarioFisico = InventarioFisico::findOrFail($id);
            
            if (!$inventarioFisico->IdFecha || $inventarioFisico->IdFecha == 0 || 
                !$inventarioFisico->IdSucursal || $inventarioFisico->IdSucursal == 0 || 
                !$inventarioFisico->IdAlmacen || $inventarioFisico->IdAlmacen == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Complete la cabecera antes de contabilizar'
                ], 400);
            }

            $tipoOperacionData = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdTipoOperacion', self::TIPO_OPERACION_AJUSTE)
                ->first();
            
            $detalleTipoOperacion = $tipoOperacionData->Detalle ?? 'Ajuste Inventario Físico';
            $numeroCorrelativoActual = $inventarioFisico->NumeroCorrelativo;
            $esReprocesamiento = $numeroCorrelativoActual > 0;

            if (!$esReprocesamiento) {
                $maxCorrelativo = InventarioFisico::where('IdSucursal', $inventarioFisico->IdSucursal)
                    ->max('NumeroCorrelativo');
                $numeroCorrelativo = ($maxCorrelativo ?? 0) + 1;
                
                $inventarioFisico->update([
                    'NumeroCorrelativo' => $numeroCorrelativo,
                    'ActivoInactivo' => 1,
                ]);
            } else {
                $numeroCorrelativo = $numeroCorrelativoActual;
                $inventarioFisico->update(['ActivoInactivo' => 1]);
                
                InventarioPropiamente::where('IdTipoDeOperacion', self::TIPO_OPERACION_AJUSTE)
                    ->where('IdDocumento', $id)
                    ->delete();
            }

            $detalles = InventarioFisicoDetalle::where('IdFisico', $id)->get();

            foreach ($detalles as $detalle) {
                $ajuste = $detalle->UnidadesAjuste;
                
                if ($ajuste != 0) {
                    $d_h = $ajuste > 0 ? 'D' : 'H';
                    $glosa = "Ajuste por Inventario Físico No. {$numeroCorrelativo} - {$detalleTipoOperacion}";
                    
                    InventarioPropiamente::create([
                        'IdTipoDeOperacion' => self::TIPO_OPERACION_AJUSTE,
                        'IdDocumento' => $id,
                        'IdFecha' => $inventarioFisico->IdFecha,
                        'IdAlmacen' => $inventarioFisico->IdAlmacen,
                        'IdProducto' => $detalle->IdProducto,
                        'Glosa' => $glosa,
                        'D_H' => $d_h,
                        'Unidades' => abs($ajuste),
                        'Bolivianos' => 0,
                        'IdCliente' => $inventarioFisico->IdCliente,
                        'IdSucursal' => $inventarioFisico->IdSucursal,
                    ]);
                }
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventario Físico contabilizado correctamente',
                'numero_correlativo' => $numeroCorrelativo,
                'redirect_url' => route('gestion.inventario-fisico.edit', $id)
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error contabilizando: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * REPROCESAR (como Scriptcase - actualiza sin cambiar correlativo)
     */
    public function reprocesar($id)
    {
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $inventarioFisico = InventarioFisico::findOrFail($id);
            
            if ($inventarioFisico->NumeroCorrelativo == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede reprocesar un borrador. Primero contabilice.'
                ], 400);
            }

            $fechaId = $inventarioFisico->IdFecha;
            $sucursalId = $inventarioFisico->IdSucursal;
            $clienteId = $inventarioFisico->IdCliente;

            // Eliminar registros basura
            InventarioFisicoDetalle::where('IdFisico', $id)
                ->where('Unidades', 0)
                ->delete();

            // Eliminar registros anteriores en inventario_propiamente
            InventarioPropiamente::where('IdTipoDeOperacion', self::TIPO_OPERACION_AJUSTE)
                ->where('IdDocumento', $id)
                ->delete();

            // Resetear UnidadesAjuste y UnidadesSaldo
            InventarioFisicoDetalle::where('IdFisico', $id)
                ->update(['UnidadesAjuste' => 0, 'UnidadesSaldo' => 0]);

            // Recorrer productos y actualizar
            $todosProductos = ProductoDetalle::where('IdCliente', $clienteId)
                ->orderBy('Descripcion')
                ->get();

            foreach ($todosProductos as $producto) {
                $saldoCalculado = InventarioPropiamente::where('IdProducto', $producto->IdProducto)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdFecha', '<=', $fechaId)
                    ->where(function($q) use ($id) {
                        $q->where('IdTipoDeOperacion', '!=', self::TIPO_OPERACION_AJUSTE)
                          ->orWhere('IdDocumento', '!=', $id);
                    })
                    ->selectRaw("COALESCE(SUM(CASE D_H WHEN 'D' THEN Unidades WHEN 'H' THEN -Unidades ELSE 0 END), 0) as saldo")
                    ->value('saldo') ?? 0;

                $detalleExistente = InventarioFisicoDetalle::where('IdFisico', $id)
                    ->where('IdProducto', $producto->IdProducto)
                    ->first();

                $unidadesExistentes = $detalleExistente ? (float) $detalleExistente->Unidades : 0;

                $debeEstarEnDetalle = false;
                if ($producto->ActivoInactivo == 0) {
                    $debeEstarEnDetalle = true;
                } else {
                    if ($saldoCalculado != 0 || $unidadesExistentes > 0) {
                        $debeEstarEnDetalle = true;
                    }
                }

                if ($debeEstarEnDetalle) {
                    if ($detalleExistente) {
                        $detalleExistente->update(['UnidadesSaldo' => $saldoCalculado]);
                    } else {
                        InventarioFisicoDetalle::create([
                            'IdFisico' => $id,
                            'IdProducto' => $producto->IdProducto,
                            'UnidadesSaldo' => $saldoCalculado,
                            'Unidades' => 0,
                            'UnidadesAjuste' => 0,
                        ]);
                    }
                } else {
                    if ($detalleExistente) {
                        $detalleExistente->delete();
                    }
                }
            }

            // Recalcular ajustes
            $detalles = InventarioFisicoDetalle::where('IdFisico', $id)->get();
            foreach ($detalles as $detalle) {
                $ajuste = $detalle->Unidades - $detalle->UnidadesSaldo;
                $detalle->update(['UnidadesAjuste' => $ajuste]);
            }

            // Regenerar movimientos en inventario_propiamente
            foreach ($detalles as $detalle) {
                $ajuste = $detalle->UnidadesAjuste;
                
                if ($ajuste != 0) {
                    $d_h = $ajuste > 0 ? 'D' : 'H';
                    $glosa = "Ajuste por Inventario Físico No. {$inventarioFisico->NumeroCorrelativo} - Reprocesado";
                    
                    InventarioPropiamente::create([
                        'IdTipoDeOperacion' => self::TIPO_OPERACION_AJUSTE,
                        'IdDocumento' => $id,
                        'IdFecha' => $inventarioFisico->IdFecha,
                        'IdAlmacen' => $inventarioFisico->IdAlmacen,
                        'IdProducto' => $detalle->IdProducto,
                        'Glosa' => $glosa,
                        'D_H' => $d_h,
                        'Unidades' => abs($ajuste),
                        'Bolivianos' => 0,
                        'IdCliente' => $inventarioFisico->IdCliente,
                        'IdSucursal' => $inventarioFisico->IdSucursal,
                    ]);
                }
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventario Físico reprocesado correctamente',
                'redirect_url' => route('gestion.inventario-fisico.edit', $id)
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error reprocesando: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener detalles (AJAX)
     */
    public function getDetalles($id)
    {
        $detalles = InventarioFisicoDetalle::where('IdFisico', $id)
            ->with('producto')
            ->get()
            ->map(function($detalle) {
                return [
                    'IdFisicoPropiamente' => $detalle->IdFisicoPropiamente,
                    'IdProducto' => $detalle->IdProducto,
                    'Codigo' => $detalle->producto?->Codigo,
                    'Descripcion' => $detalle->producto?->Descripcion,
                    'UnidadesSaldo' => (float) $detalle->UnidadesSaldo,
                    'Unidades' => (float) $detalle->Unidades,
                    'UnidadesAjuste' => (float) $detalle->UnidadesAjuste,
                ];
            });
        
        return response()->json(['success' => true, 'detalles' => $detalles]);
    }

    /**
     * Eliminar borrador
     */
    public function destroy($id)
    {
        $inventarioFisico = InventarioFisico::findOrFail($id);
        
        if ($inventarioFisico->NumeroCorrelativo != 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un inventario ya contabilizado'
            ], 400);
        }

        InventarioFisicoDetalle::where('IdFisico', $id)->delete();
        $inventarioFisico->delete();

        return response()->json(['success' => true, 'message' => 'Borrador eliminado correctamente']);
    }
}