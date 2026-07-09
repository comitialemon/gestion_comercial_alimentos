<?php

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
use TCPDF;

class InventarioFisicoController extends Controller
{
    /**
     * Obtener el IdTipoOperacion para "Inventario Fisico" del cliente logueado
     */
    private function getTipoOperacionInventarioFisico()
    {
        $clienteId = session('cliente_id');
        
        $tipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('Detalle', 'Inventario Fisico')
            ->where('ActivoInactivo', 0)
            ->first();
        
        if (!$tipoOperacion) {
            throw new \Exception('No se encontró el tipo de operación "Inventario Fisico" para el cliente');
        }
        
        return $tipoOperacion->IdTipoOperacion;
    }

    /**
     * Obtener el detalle del tipo de operación
     */
    private function getDetalleTipoOperacion($idTipoOperacion)
    {
        $tipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_tipooperacion')
            ->where('IdTipoOperacion', $idTipoOperacion)
            ->first();
        
        return $tipoOperacion->Detalle ?? 'Inventario Físico';
    }

    /**
     * Entrada principal - Busca borrador pendiente o muestra formulario vacío
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

        // Buscar borrador pendiente
        $borrador = InventarioFisico::where('IdCliente', $clienteId)
            ->where('IdOperador', $operadorId)
            ->where('ActivoInactivo', 0)
            ->where('NumeroCorrelativo', 0)
            ->first();

        // Datos para selects
        $fechas = Fecha::where('IdFecha', '>', 0)
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha as id', DB::raw("DATE_FORMAT(Fecha, '%d-%m-%Y') as fecha_display")]);

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        $identificadores = Identificador::orderBy('CI_NIT')
            ->get(['IdIdentificador as id', 'CI_NIT', 'Nombre'])
            ->map(fn($i) => ['id' => $i->id, 'texto' => "{$i->CI_NIT} - {$i->Nombre}"]);

        // Cargar almacenes si hay sucursal en el borrador
        $almacenes = [];
        if ($borrador && $borrador->IdSucursal) {
            $almacenes = Almacen::where('IdCliente', $clienteId)
                ->where('IdSucursal', $borrador->IdSucursal)
                ->orderBy('Almacen')
                ->get(['IdAlmacen as id', 'Almacen as nombre']);
        }

        // Cargar detalles si existe borrador
        $detalles = [];
        if ($borrador) {
            $detalles = InventarioFisicoDetalle::where('IdFisico', $borrador->IdFisico)
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
        }

        return Inertia::render('Gestion/Inventario/InventarioFisico/Create', [
            'inventarioFisico' => $borrador,
            'detalles' => $detalles,
            'fechas' => $fechas,
            'sucursales' => $sucursales,
            'almacenes' => $almacenes,
            'identificadores' => $identificadores,
            'esBorrador' => true,
        ]);
    }

    /**
     * Editar inventario físico (para contabilizados - solo lectura)
     */
    public function edit($id)
    {
        $clienteId = session('cliente_id');

        $inventarioFisico = InventarioFisico::where('IdCliente', $clienteId)
            ->with(['fecha', 'sucursal', 'almacen', 'realizadoPor', 'encargadoSucursal'])
            ->findOrFail($id);
        
        $esBorrador = $inventarioFisico->NumeroCorrelativo == 0;
        $esContabilizado = $inventarioFisico->NumeroCorrelativo > 0;

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

        $fechas = Fecha::where('IdFecha', '>', 0)
            ->orderBy('Fecha', 'desc')
            ->get(['IdFecha as id', DB::raw("DATE_FORMAT(Fecha, '%d-%m-%Y') as fecha_display")]);

        $sucursales = ClienteSucursal::where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        $identificadores = Identificador::orderBy('CI_NIT')
            ->get(['IdIdentificador as id', 'CI_NIT', 'Nombre'])
            ->map(fn($i) => ['id' => $i->id, 'texto' => "{$i->CI_NIT} - {$i->Nombre}"]);

        // Obtener almacenes si hay sucursal seleccionada
        $almacenes = [];
        if ($inventarioFisico->IdSucursal) {
            $almacenes = Almacen::where('IdCliente', $clienteId)
                ->where('IdSucursal', $inventarioFisico->IdSucursal)
                ->orderBy('Almacen')
                ->get(['IdAlmacen as id', 'Almacen as nombre']);
        }

        return Inertia::render('Gestion/Inventario/InventarioFisico/Create', [
            'inventarioFisico' => $inventarioFisico,
            'detalles' => $detalles,
            'fechas' => $fechas,
            'sucursales' => $sucursales,
            'almacenes' => $almacenes,
            'identificadores' => $identificadores,
            'esBorrador' => $esBorrador,
            'esContabilizado' => $esContabilizado,
        ]);
    }

    /**
     * Guardar cabecera (CREA o ACTUALIZA) - NUEVO MÉTODO UNIFICADO
     */
    public function storeCabecera(Request $request)
    {
        $request->validate([
            'IdFecha' => 'required|exists:todos_fecha,IdFecha',
            'IdSucursal' => 'required|exists:todos_cliente_sucursal,IdClienteSucursal',
            'IdAlmacen' => 'required|exists:inventario_almacen,IdAlmacen',
            'IdRealizadoPor' => 'required|exists:todos_identificador,IdIdentificador',
            'IdEncargadoSucursal' => 'required|exists:todos_identificador,IdIdentificador',
            'Observacion' => 'nullable|string',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $id = $request->IdFisico;

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            if ($id) {
                // 🔥 ACTUALIZAR: Buscar y actualizar
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
            } else {
                // 🔥 CREAR: Buscar borrador o crear nuevo
                $borrador = InventarioFisico::where('IdCliente', $clienteId)
                    ->where('IdOperador', $operadorId)
                    ->where('ActivoInactivo', 0)
                    ->where('NumeroCorrelativo', 0)
                    ->first();

                if ($borrador) {
                    $borrador->update([
                        'IdFecha' => $request->IdFecha,
                        'IdSucursal' => $request->IdSucursal,
                        'IdAlmacen' => $request->IdAlmacen,
                        'IdRealizadoPor' => $request->IdRealizadoPor,
                        'IdEncargadoSucursal' => $request->IdEncargadoSucursal,
                        'Observacion' => $request->Observacion,
                    ]);
                    $id = $borrador->IdFisico;
                } else {
                    $borrador = InventarioFisico::create([
                        'NumeroCorrelativo' => 0,
                        'IdFecha' => $request->IdFecha,
                        'IdSucursal' => $request->IdSucursal,
                        'IdAlmacen' => $request->IdAlmacen,
                        'IdRealizadoPor' => $request->IdRealizadoPor,
                        'IdEncargadoSucursal' => $request->IdEncargadoSucursal,
                        'Observacion' => $request->Observacion,
                        'ActivoInactivo' => 0,
                        'IdCliente' => $clienteId,
                        'IdOperador' => $operadorId,
                    ]);
                    $id = $borrador->IdFisico;
                }
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            // Sincronizar productos automáticamente (FUERA de la transacción)
            $this->sincronizarProductosLogic($id);

            return response()->json([
                'success' => true,
                'id' => $id,
                'message' => 'Cabecera guardada y productos sincronizados'
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error guardando cabecera: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar cabecera (SOLO ACTUALIZA - mantiene compatibilidad con PUT)
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

        // Sincronizar productos automáticamente
        $this->sincronizarProductosLogic($id);

        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'Cabecera actualizada y productos sincronizados'
        ]);
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
     * Lógica de sincronización de productos (reutilizable)
     */
    private function sincronizarProductosLogic($id)
    {
        try {
            $inventarioFisico = InventarioFisico::findOrFail($id);
            
            if ($inventarioFisico->NumeroCorrelativo != 0) {
                return;
            }
            
            $fechaId = $inventarioFisico->IdFecha;
            $sucursalId = $inventarioFisico->IdSucursal;
            $clienteId = $inventarioFisico->IdCliente;

            // Obtener el IdTipoOperacion para "Inventario Fisico"
            $idTipoOperacion = $this->getTipoOperacionInventarioFisico();

            // Guardar unidades actuales (conteo físico) para preservarlas
            $unidadesActuales = [];
            $detallesExistentes = InventarioFisicoDetalle::where('IdFisico', $id)->get();
            foreach ($detallesExistentes as $det) {
                if ($det->Unidades > 0) {
                    $unidadesActuales[$det->IdProducto] = $det->Unidades;
                }
            }

            // ELIMINAR REGISTROS BASURA (Unidades = 0)
            InventarioFisicoDetalle::where('IdFisico', $id)
                ->where('Unidades', 0)
                ->delete();

            // ELIMINAR REGISTROS ANTERIORES DEL MISMO DOCUMENTO
            InventarioPropiamente::where('IdTipoDeOperacion', $idTipoOperacion)
                ->where('IdDocumento', $id)
                ->delete();

            // Resetear UnidadesAjuste y UnidadesSaldo
            InventarioFisicoDetalle::where('IdFisico', $id)
                ->update(['UnidadesAjuste' => 0, 'UnidadesSaldo' => 0]);

            // RECORRER TODOS LOS PRODUCTOS
            $todosProductos = ProductoDetalle::where('IdCliente', $clienteId)
                ->orderBy('Descripcion')
                ->get();

            foreach ($todosProductos as $producto) {
                // CALCULAR SALDO ACTUAL (excluyendo el inventario fisico actual)
                $saldoCalculado = InventarioPropiamente::where('IdProducto', $producto->IdProducto)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdFecha', '<=', $fechaId)
                    ->where(function($q) use ($id, $idTipoOperacion) {
                        $q->where('IdTipoDeOperacion', '!=', $idTipoOperacion)
                          ->orWhere('IdDocumento', '!=', $id);
                    })
                    ->selectRaw("COALESCE(SUM(CASE D_H WHEN 'D' THEN Unidades WHEN 'H' THEN -Unidades ELSE 0 END), 0) as saldo")
                    ->value('saldo') ?? 0;

                $detalleExistente = InventarioFisicoDetalle::where('IdFisico', $id)
                    ->where('IdProducto', $producto->IdProducto)
                    ->first();

                $conteoPreservado = $unidadesActuales[$producto->IdProducto] ?? 0;

                // REGLA DE NEGOCIO
                $debeEstarEnDetalle = false;
                if ($producto->ActivoInactivo == 0) {
                    // ACTIVO: Siempre debe estar
                    $debeEstarEnDetalle = true;
                } else {
                    // INACTIVO: Solo si tiene saldo O ya tiene conteo fisico
                    if ($saldoCalculado != 0 || $conteoPreservado > 0) {
                        $debeEstarEnDetalle = true;
                    }
                }

                if ($debeEstarEnDetalle) {
                    if ($detalleExistente) {
                        // YA EXISTE → SOLO ACTUALIZAR UNIDADESSALDO (PRESERVAR TODO LO DEMAS)
                        $detalleExistente->update([
                            'UnidadesSaldo' => $saldoCalculado,
                            'Unidades' => $conteoPreservado > 0 ? $conteoPreservado : $detalleExistente->Unidades,
                        ]);
                    } else {
                        // NO EXISTE → INSERTAR COMPLETO
                        InventarioFisicoDetalle::create([
                            'IdFisico' => $id,
                            'IdProducto' => $producto->IdProducto,
                            'UnidadesSaldo' => $saldoCalculado,
                            'Unidades' => $conteoPreservado,
                            'UnidadesAjuste' => 0,
                        ]);
                    }
                } else {
                    // NO DEBE ESTAR EN DETALLE → ELIMINAR SI EXISTE
                    if ($detalleExistente) {
                        $detalleExistente->delete();
                    }
                }
            }

            // RECALCULAR UNIDADESAJUSTE
            $detalles = InventarioFisicoDetalle::where('IdFisico', $id)->get();
            foreach ($detalles as $detalle) {
                $ajuste = $detalle->Unidades - $detalle->UnidadesSaldo;
                $detalle->update(['UnidadesAjuste' => $ajuste]);
            }

        } catch (\Exception $e) {
            Log::error('Error en sincronizarProductosLogic: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sincronizar productos (llamada manual desde el frontend)
     */
    public function sincronizarProductos($id)
    {
        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();
            
            $this->sincronizarProductosLogic($id);
            
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
        
        if ($inventarioFisico->NumeroCorrelativo > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar un inventario ya contabilizado'
            ], 400);
        }

        $detalle = InventarioFisicoDetalle::where('IdFisico', $id)
            ->where('IdFisicoPropiamente', $detalleId)
            ->firstOrFail();

        // UnidadesAjuste = Unidades - UnidadesSaldo
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
         * CONTABILIZAR (IGUAL A SCRIPTCASE)
         */
        public function contabilizar($id)
        {
            try {
                DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

                $inventarioFisico = InventarioFisico::findOrFail($id);
                
                // Validar que la cabecera esté completa
                if (!$inventarioFisico->IdFecha || $inventarioFisico->IdFecha == 0 || 
                    !$inventarioFisico->IdSucursal || $inventarioFisico->IdSucursal == 0 || 
                    !$inventarioFisico->IdAlmacen || $inventarioFisico->IdAlmacen == 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Complete la cabecera antes de contabilizar'
                    ], 400);
                }

                // Obtener el IdTipoOperacion para "Inventario Fisico"
                $idTipoOperacion = $this->getTipoOperacionInventarioFisico();
                $detalleTipoOperacion = $this->getDetalleTipoOperacion($idTipoOperacion);
                
                // ===== MANEJO DE CORRELATIVO =====
                $numeroCorrelativoActual = $inventarioFisico->NumeroCorrelativo;

                if ($numeroCorrelativoActual == 0) {
                    // NUEVO DOCUMENTO - Generar nuevo correlativo
                    $maxCorrelativo = InventarioFisico::where('IdSucursal', $inventarioFisico->IdSucursal)
                        ->max('NumeroCorrelativo');
                    $numeroCorrelativo = ($maxCorrelativo ?? 0) + 1;
                    
                    $inventarioFisico->update([
                        'NumeroCorrelativo' => $numeroCorrelativo,
                        'ActivoInactivo' => 1,
                    ]);
                    $mensaje = "Inventario Físico contabilizado correctamente. N° {$numeroCorrelativo}";
                } else {
                    // REPROCESAMIENTO - Mantener el correlativo existente
                    $numeroCorrelativo = $numeroCorrelativoActual;
                    $inventarioFisico->update(['ActivoInactivo' => 1]);
                    $mensaje = "Inventario Físico reprocesado correctamente. N° {$numeroCorrelativo}";
                }
                
                // 🔥 ELIMINAR REGISTROS ANTERIORES DEL MISMO DOCUMENTO
                InventarioPropiamente::where('IdTipoDeOperacion', $idTipoOperacion)
                    ->where('IdDocumento', $id)
                    ->delete();

                // 🔥 OBTENER DETALLES EXISTENTES (NO RECALCULAR)
                $detalles = InventarioFisicoDetalle::where('IdFisico', $id)->get();

                // 🔥 GENERAR NUEVOS MOVIMIENTOS CON LOS DETALLES EXISTENTES
                foreach ($detalles as $detalle) {
                    // Calcular saldo en libros hasta la fecha del inventario
                    $saldoLibrosFecha = InventarioPropiamente::where('IdProducto', $detalle->IdProducto)
                        ->where('IdCliente', $inventarioFisico->IdCliente)
                        ->where('IdSucursal', $inventarioFisico->IdSucursal)
                        ->where('IdFecha', '<=', $inventarioFisico->IdFecha)
                        ->selectRaw("COALESCE(SUM(CASE D_H WHEN 'D' THEN Unidades WHEN 'H' THEN -Unidades ELSE 0 END), 0) as saldo")
                        ->value('saldo') ?? 0;

                    // Recalcular ajuste (por si cambió el saldo)
                    $ajuste = $detalle->Unidades - $saldoLibrosFecha;
                    
                    // Actualizar UnidadesAjuste en el detalle
                    $detalle->update(['UnidadesAjuste' => $ajuste]);
                    
                    // Solo insertar en inventario_propiamente si hay ajuste
                    if ($ajuste != 0) {
                        $d_h = $ajuste > 0 ? 'D' : 'H';
                        $glosa = "Ajuste por Inventario Físico No. {$numeroCorrelativo} - {$detalleTipoOperacion}";
                        
                        InventarioPropiamente::create([
                            'IdTipoDeOperacion' => $idTipoOperacion,
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
                    'message' => $mensaje,
                    'numero_correlativo' => $numeroCorrelativo,
                    'pdf_url' => route('gestion.inventario-fisico.pdf', $id),
                ]);

            } catch (\Exception $e) {
                DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
                Log::error('Error en contabilizar: ' . $e->getMessage());
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
        }

    /**
     * REPROCESAR (solo actualiza lista, NO genera movimientos)
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

            // Guardar unidades actuales para preservarlas
            $unidadesActuales = [];
            $detallesExistentes = InventarioFisicoDetalle::where('IdFisico', $id)->get();
            foreach ($detallesExistentes as $det) {
                if ($det->Unidades > 0) {
                    $unidadesActuales[$det->IdProducto] = $det->Unidades;
                }
            }

            // Recalcular saldos y actualizar detalles (sin generar movimientos)
            $this->recalcularSaldosYDetalles($id, $unidadesActuales);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Lista de productos actualizada correctamente'
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error reprocesando: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Recalcular saldos y detalles (método auxiliar)
     */
    private function recalcularSaldosYDetalles($id, $unidadesActuales = [])
    {
        $inventarioFisico = InventarioFisico::findOrFail($id);
        
        $fechaId = $inventarioFisico->IdFecha;
        $sucursalId = $inventarioFisico->IdSucursal;
        $clienteId = $inventarioFisico->IdCliente;

        // Obtener el IdTipoOperacion para "Inventario Fisico"
        $idTipoOperacion = $this->getTipoOperacionInventarioFisico();

        // ELIMINAR REGISTROS BASURA (Unidades = 0)
        InventarioFisicoDetalle::where('IdFisico', $id)
            ->where('Unidades', 0)
            ->delete();

        // Resetear UnidadesAjuste y UnidadesSaldo
        InventarioFisicoDetalle::where('IdFisico', $id)
            ->update(['UnidadesAjuste' => 0, 'UnidadesSaldo' => 0]);

        // RECORRER TODOS LOS PRODUCTOS
        $todosProductos = ProductoDetalle::where('IdCliente', $clienteId)
            ->orderBy('Descripcion')
            ->get();

        foreach ($todosProductos as $producto) {
            // CALCULAR SALDO ACTUAL (excluyendo el inventario fisico actual)
            $saldoCalculado = InventarioPropiamente::where('IdProducto', $producto->IdProducto)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdFecha', '<=', $fechaId)
                ->where(function($q) use ($id, $idTipoOperacion) {
                    $q->where('IdTipoDeOperacion', '!=', $idTipoOperacion)
                      ->orWhere('IdDocumento', '!=', $id);
                })
                ->selectRaw("COALESCE(SUM(CASE D_H WHEN 'D' THEN Unidades WHEN 'H' THEN -Unidades ELSE 0 END), 0) as saldo")
                ->value('saldo') ?? 0;

            $detalleExistente = InventarioFisicoDetalle::where('IdFisico', $id)
                ->where('IdProducto', $producto->IdProducto)
                ->first();

            $conteoPreservado = $unidadesActuales[$producto->IdProducto] ?? 0;

            // REGLA DE NEGOCIO
            $debeEstarEnDetalle = false;
            if ($producto->ActivoInactivo == 0) {
                $debeEstarEnDetalle = true;
            } else {
                if ($saldoCalculado != 0 || $conteoPreservado > 0) {
                    $debeEstarEnDetalle = true;
                }
            }

            if ($debeEstarEnDetalle) {
                if ($detalleExistente) {
                    $detalleExistente->update([
                        'UnidadesSaldo' => $saldoCalculado,
                        'Unidades' => $conteoPreservado > 0 ? $conteoPreservado : $detalleExistente->Unidades,
                    ]);
                } else {
                    InventarioFisicoDetalle::create([
                        'IdFisico' => $id,
                        'IdProducto' => $producto->IdProducto,
                        'UnidadesSaldo' => $saldoCalculado,
                        'Unidades' => $conteoPreservado,
                        'UnidadesAjuste' => 0,
                    ]);
                }
            } else {
                if ($detalleExistente) {
                    $detalleExistente->delete();
                }
            }
        }

        // RECALCULAR UNIDADESAJUSTE
        $detalles = InventarioFisicoDetalle::where('IdFisico', $id)->get();
        foreach ($detalles as $detalle) {
            $ajuste = $detalle->Unidades - $detalle->UnidadesSaldo;
            $detalle->update(['UnidadesAjuste' => $ajuste]);
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

    /**
     * Actualizar inventario (para compatibilidad con rutas RESTful)
     */
    public function update(Request $request, $id)
    {
        return $this->updateCabecera($request, $id);
    }

    /**
     * Generar PDF del inventario físico
     */
    public function pdf($id)
    {
        $inventarioFisico = InventarioFisico::findOrFail($id);
        
        $empresa = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente')
            ->where('IdCliente', $inventarioFisico->IdCliente)
            ->first();

        $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdClienteSucursal', $inventarioFisico->IdSucursal)
            ->first();

        $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('IdFecha', $inventarioFisico->IdFecha)
            ->first();
        
        $fechaFormateada = $fechaData ? date('d-m-Y', strtotime($fechaData->Fecha)) : '-';

        $detalles = InventarioFisicoDetalle::where('IdFisico', $id)
            ->with('producto')
            ->orderBy('IdFisicoPropiamente')
            ->get();

        $realizadoPor = Identificador::find($inventarioFisico->IdRealizadoPor);
        $encargado = Identificador::find($inventarioFisico->IdEncargadoSucursal);

        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(8, 12, 8);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 9);

        // Título
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 6, 'INVENTARIO FÍSICO', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, 'N° ' . $inventarioFisico->NumeroCorrelativo, 0, 1, 'C');
        $pdf->Ln(4);

        // Datos de la empresa
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'DATOS DE LA EMPRESA', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Empresa: ' . ($empresa->Nombre ?? '-'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'NIT: ' . ($empresa->NIT ?? '-'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'Sucursal: ' . ($sucursal->Nombre ?? '-'), 0, 1, 'L');
        $pdf->Ln(3);

        // Datos del inventario
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 5, 'DATOS DEL INVENTARIO', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Fecha: ' . $fechaFormateada, 0, 1, 'L');
        $pdf->Cell(0, 5, 'Realizado por: ' . ($realizadoPor->Nombre ?? '-'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'Encargado Sucursal: ' . ($encargado->Nombre ?? '-'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'Observación: ' . ($inventarioFisico->Observacion ?: 'Ninguna'), 0, 1, 'L');
        $pdf->Ln(5);

        // Tabla
        $w = [65, 65, 15, 15, 15];
        $header = ['Código', 'Producto', 'Saldo', 'Conteo', 'Ajuste'];
        
        $pdf->SetFont('helvetica', 'B', 8);
        for ($i = 0; $i < count($header); $i++) {
            $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C');
        }
        $pdf->Ln();
        
        $pdf->SetFont('helvetica', '', 8);
        foreach ($detalles as $detalle) {
            if ($detalle->UnidadesAjuste != 0 || $detalle->Unidades > 0 || $detalle->UnidadesSaldo != 0) {
                
                $codigo = $detalle->producto->Codigo ?? '-';
                $producto = $detalle->producto->Descripcion ?? '-';
                $saldo = number_format($detalle->UnidadesSaldo, 2);
                $conteo = number_format($detalle->Unidades, 2);
                $ajuste = number_format($detalle->UnidadesAjuste, 2);
                
                $y = $pdf->GetY();
                if ($y > 250) {
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 8);
                    for ($i = 0; $i < count($header); $i++) {
                        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C');
                    }
                    $pdf->Ln();
                    $pdf->SetFont('helvetica', '', 8);
                }
                
                $x = $pdf->GetX();
                $y_ini = $pdf->GetY();
                $h_max = max($pdf->getStringHeight($w[0], $codigo), $pdf->getStringHeight($w[1], $producto), 6);
                
                $pdf->MultiCell($w[0], $h_max, $codigo, 1, 'L', 0, 0, $x, $y_ini);
                $x += $w[0];
                $pdf->MultiCell($w[1], $h_max, $producto, 1, 'L', 0, 0, $x, $y_ini);
                $x += $w[1];
                $pdf->MultiCell($w[2], $h_max, $saldo, 1, 'R', 0, 0, $x, $y_ini);
                $x += $w[2];
                $pdf->MultiCell($w[3], $h_max, $conteo, 1, 'R', 0, 0, $x, $y_ini);
                $x += $w[3];
                if ($detalle->UnidadesAjuste > 0) {
                    $pdf->SetTextColor(0, 128, 0);
                } elseif ($detalle->UnidadesAjuste < 0) {
                    $pdf->SetTextColor(255, 0, 0);
                }
                $pdf->MultiCell($w[4], $h_max, $ajuste, 1, 'R', 0, 0, $x, $y_ini);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetY($y_ini + $h_max);
            }
        }

        $totalProductos = $detalles->count();
        $totalConAjuste = $detalles->filter(fn($d) => $d->UnidadesAjuste != 0)->count();
        
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 5, 'Resumen:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, 'Total productos procesados: ' . $totalProductos, 0, 1, 'L');
        $pdf->Cell(0, 5, 'Productos con ajuste: ' . $totalConAjuste, 0, 1, 'L');
        
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(80, 5, '_________________________', 0, 0, 'C');
        $pdf->Cell(30, 5, '', 0, 0, 'C');
        $pdf->Cell(80, 5, '_________________________', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(80, 4, 'Realizado por', 0, 0, 'C');
        $pdf->Cell(30, 4, '', 0, 0, 'C');
        $pdf->Cell(80, 4, 'Encargado Sucursal', 0, 1, 'C');

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="inventario_fisico_' . $inventarioFisico->NumeroCorrelativo . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        $pdf->Output('inventario_fisico_' . $inventarioFisico->NumeroCorrelativo . '.pdf', 'I');
        exit;
    }
}