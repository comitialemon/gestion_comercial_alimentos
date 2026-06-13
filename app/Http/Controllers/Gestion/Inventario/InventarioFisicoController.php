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
    /**
     * Entrada principal - Crea un borrador (NumeroCorrelativo = 0) como en Scriptcase
     */
    public function create()
    {
        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        // Verificar contexto
        if (!$clienteId || !$operadorId) {
            return redirect()->route('contexto.index')
                ->with('error', 'Debe seleccionar una empresa primero');
        }

        // Buscar si hay un borrador existente (NumeroCorrelativo = 0)
        $borrador = InventarioFisico::where('IdCliente', $clienteId)
            ->where('IdOperador', $operadorId)
            ->where('ActivoInactivo', 0)
            ->where('NumeroCorrelativo', 0)
            ->first();

        if ($borrador) {
            return redirect()->route('gestion.inventario-fisico.edit', $borrador->IdFisico)
                ->with('info', 'Continúe con el inventario físico en progreso');
        }

        // Crear nuevo borrador (IdFecha, IdSucursal, etc. se asignan en edición)
        $inventarioFisico = InventarioFisico::create([
            'NumeroCorrelativo' => 0,
            'IdCliente' => $clienteId,
            'IdOperador' => $operadorId,
            'ActivoInactivo' => 0,
        ]);

        return redirect()->route('gestion.inventario-fisico.edit', $inventarioFisico->IdFisico);
    }

    /**
     * Editar inventario físico (formulario maestro-detalle)
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');

        $inventarioFisico = InventarioFisico::where('IdCliente', $clienteId)
            ->with(['fecha', 'sucursal', 'almacen', 'realizadoPor', 'encargadoSucursal'])
            ->findOrFail($id);
        
        // Si ya está contabilizado (NumeroCorrelativo != 0), solo lectura
        $esBorrador = $inventarioFisico->NumeroCorrelativo == 0;

        // Cargar detalles (grid maestro-detalle)
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

        // 🔥 SELECTS - FECHAS EN ORDEN DESCENDENTE (más reciente primero)
        $fechas = Fecha::orderBy('Fecha', 'desc')
            ->get([
                'IdFecha as id',
                DB::raw("DATE_FORMAT(Fecha, '%d-%m-%Y') as fecha_display")
            ]);

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        // Almacenes se carga vía AJAX según IdSucursal
        $almacenes = [];

        $identificadores = Identificador::orderBy('CI_NIT')
            ->get(['IdIdentificador as id', 'CI_NIT', 'Nombre'])
            ->map(fn($i) => [
                'id' => $i->id,
                'texto' => "{$i->CI_NIT} - {$i->Nombre}"
            ]);

        return Inertia::render('Gestion/Inventario/InventarioFisico/Create', [
            'inventarioFisico' => $inventarioFisico,
            'detalles' => $detalles,
            'fechas' => $fechas,
            'sucursales' => $sucursales,
            'almacenes' => $almacenes,
            'identificadores' => $identificadores,
            'esBorrador' => $esBorrador,
        ]);
    }

    /**
     * Guardar cabecera (IdFecha, IdSucursal, IdAlmacen, etc.)
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
        
        // Solo permitir si es borrador
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

        return response()->json([
            'success' => true,
            'message' => 'Cabecera guardada correctamente'
        ]);
    }

    /**
     * Sincronizar productos (ONAFTERINSERT / ACTUALIZA en Scriptcase)
     * Recorre todo el catálogo y actualiza el detalle
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

            // PASO 1: Eliminar registros basura (Unidades = 0)
            InventarioFisicoDetalle::where('IdFisico', $id)
                ->where('Unidades', 0)
                ->delete();

            // PASO 2: Eliminar registros anteriores del mismo documento en inventario_propiamente
            InventarioPropiamente::where('IdTipoDeOperacion', InventarioFisico::TIPO_OPERACION_AJUSTE)
                ->where('IdDocumento', $id)
                ->delete();

            // PASO 3: Poner en cero UnidadesAjuste y UnidadesSaldo
            InventarioFisicoDetalle::where('IdFisico', $id)
                ->update(['UnidadesAjuste' => 0, 'UnidadesSaldo' => 0]);

            // PASO 4: Recorrer todo el catálogo de productos
            $todosProductos = ProductoDetalle::where('IdCliente', $clienteId)
                ->orderBy('Descripcion')
                ->get();

            foreach ($todosProductos as $producto) {
                // Calcular saldo actual (excluyendo este inventario físico)
                $saldoCalculado = InventarioPropiamente::where('IdProducto', $producto->IdProducto)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdFecha', '<=', $fechaId)
                    ->where(function($q) use ($id) {
                        $q->where('IdTipoDeOperacion', '!=', InventarioFisico::TIPO_OPERACION_AJUSTE)
                          ->orWhere('IdDocumento', '!=', $id);
                    })
                    ->selectRaw("COALESCE(SUM(CASE D_H WHEN 'D' THEN Unidades WHEN 'H' THEN -Unidades ELSE 0 END), 0) as saldo")
                    ->value('saldo') ?? 0;

                // Verificar si ya existe en detalle
                $detalleExistente = InventarioFisicoDetalle::where('IdFisico', $id)
                    ->where('IdProducto', $producto->IdProducto)
                    ->first();

                $unidadesExistentes = $detalleExistente ? (float) $detalleExistente->Unidades : 0;

                // Regla de negocio (como en Scriptcase)
                $debeEstarEnDetalle = false;

                if ($producto->ActivoInactivo == 0) {
                    // ACTIVO: Siempre debe estar
                    $debeEstarEnDetalle = true;
                } else {
                    // INACTIVO: Solo si tiene saldo O ya tiene conteo físico
                    if ($saldoCalculado != 0 || $unidadesExistentes > 0) {
                        $debeEstarEnDetalle = true;
                    }
                }

                if ($debeEstarEnDetalle) {
                    if ($detalleExistente) {
                        // YA EXISTE → actualizar solo UnidadesSaldo
                        $detalleExistente->update([
                            'UnidadesSaldo' => $saldoCalculado
                        ]);
                    } else {
                        // NO EXISTE → insertar nuevo
                        InventarioFisicoDetalle::create([
                            'IdFisico' => $id,
                            'IdProducto' => $producto->IdProducto,
                            'UnidadesSaldo' => $saldoCalculado,
                            'Unidades' => 0,
                            'UnidadesAjuste' => 0,
                        ]);
                    }
                } else {
                    // NO DEBE ESTAR → eliminar si existe
                    if ($detalleExistente) {
                        $detalleExistente->delete();
                    }
                }
            }

            // PASO 5: Recalcular UnidadesAjuste para todos los registros
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar unidades de un producto (conteo físico)
     * Calcula automáticamente el ajuste como en Scriptcase
     */
    public function actualizarUnidades(Request $request, $id, $detalleId)
    {
        $request->validate([
            'Unidades' => 'required|numeric|min:0',
        ]);

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

        // Calcular ajuste (como en Scriptcase)
        $ajuste = $request->Unidades - $detalle->UnidadesSaldo;
        
        $detalle->update([
            'Unidades' => $request->Unidades,
            'UnidadesAjuste' => $ajuste,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Unidades actualizadas',
            'unidades_ajuste' => $ajuste
        ]);
    }

    /**
     * CONTABILIZAR (como el botón CONTABILIZA en Scriptcase)
     * Genera correlativo y movimientos en inventario_propiamente
     */
    public function contabilizar($id)
    {
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $inventarioFisico = InventarioFisico::findOrFail($id);
            
            // Verificar que tenga cabecera completa
            if (!$inventarioFisico->IdFecha || !$inventarioFisico->IdSucursal || !$inventarioFisico->IdAlmacen) {
                return response()->json([
                    'success' => false,
                    'message' => 'Complete la cabecera antes de contabilizar'
                ], 400);
            }

            $tipoOperacion = InventarioFisico::TIPO_OPERACION_AJUSTE;
            
            // Obtener detalle del tipo de operación
            $tipoOperacionData = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdTipoOperacion', $tipoOperacion)
                ->first();
            
            $detalleTipoOperacion = $tipoOperacionData->Detalle ?? 'Ajuste Inventario Físico';

            // Manejo de correlativo y reprocesamiento
            $numeroCorrelativoActual = $inventarioFisico->NumeroCorrelativo;
            $esReprocesamiento = $numeroCorrelativoActual > 0;

            if (!$esReprocesamiento) {
                // NUEVO DOCUMENTO - Generar nuevo correlativo
                $maxCorrelativo = InventarioFisico::where('IdSucursal', $inventarioFisico->IdSucursal)
                    ->max('NumeroCorrelativo');
                $numeroCorrelativo = ($maxCorrelativo ?? 0) + 1;
                
                $inventarioFisico->update([
                    'NumeroCorrelativo' => $numeroCorrelativo,
                    'ActivoInactivo' => 1,
                ]);
            } else {
                // REPROCESAMIENTO - Mantener el correlativo existente
                $numeroCorrelativo = $numeroCorrelativoActual;
                
                $inventarioFisico->update([
                    'ActivoInactivo' => 1,
                ]);

                // Eliminar registros anteriores del mismo documento
                InventarioPropiamente::where('IdTipoDeOperacion', $tipoOperacion)
                    ->where('IdDocumento', $id)
                    ->delete();
            }

            // Obtener detalles del inventario físico
            $detalles = InventarioFisicoDetalle::where('IdFisico', $id)->get();

            foreach ($detalles as $detalle) {
                $ajuste = $detalle->UnidadesAjuste;
                
                if ($ajuste != 0) {
                    $esAumento = $ajuste > 0;
                    $d_h = $esAumento ? 'D' : 'H';
                    $unidadesAjuste = abs($ajuste);
                    
                    $glosa = "Ajuste por Inventario Físico No. {$numeroCorrelativo} - {$detalleTipoOperacion}";
                    $bolivianos = 0; // Pendiente cálculo de costo
                    
                    InventarioPropiamente::create([
                        'IdTipoDeOperacion' => $tipoOperacion,
                        'IdDocumento' => $id,
                        'IdFecha' => $inventarioFisico->IdFecha,
                        'IdAlmacen' => $inventarioFisico->IdAlmacen,
                        'IdProducto' => $detalle->IdProducto,
                        'Glosa' => $glosa,
                        'D_H' => $d_h,
                        'Unidades' => $unidadesAjuste,
                        'Bolivianos' => $bolivianos,
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalles del inventario físico (AJAX)
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
        
        return response()->json([
            'success' => true,
            'detalles' => $detalles
        ]);
    }

    /**
     * Eliminar inventario físico (solo si es borrador)
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

        // Eliminar detalles
        InventarioFisicoDetalle::where('IdFisico', $id)->delete();
        
        // Eliminar cabecera
        $inventarioFisico->delete();

        return response()->json([
            'success' => true,
            'message' => 'Borrador eliminado correctamente'
        ]);
    }
}