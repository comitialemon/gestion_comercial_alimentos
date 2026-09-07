<?php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Inventario\InventarioFisicoDiarioConfig;
use App\Models\Gestion\Inventario\InventarioFisicoDiarioCabecera;
use App\Models\Gestion\Inventario\InventarioFisicoDiarioDetalle;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\InventarioPropiamente;
use App\Models\Gestion\Todos\Fecha;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InventarioFisicoDiarioController extends Controller
{
    /**
     * Listado de inventarios físicos diarios (con filtros)
     * 🔥 SOLO DE LA SUCURSAL LOGUEADA
     */
    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $query = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->with(['operador', 'sucursal', 'fecha']);

        if ($request->filled('estado')) {
            if ($request->estado === 'completados') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'borradores') {
                $query->where('ActivoInactivo', 0);
            } elseif ($request->estado === 'anulados') {
                $query->where('ActivoInactivo', 2);
            }
        }

        if ($request->filled('buscar')) {
            $query->where('NumeroCorrelativo', 'LIKE', '%' . $request->buscar . '%');
        }

        $inventarios = $query->orderBy('IdFisicoDiario', 'desc')
            ->paginate(20)
            ->appends($request->all());

        $inventarios->getCollection()->transform(function ($item) {
            if ($item->fecha && $item->fecha->Fecha) {
                $item->fecha_formateada = date('d/m/Y', strtotime($item->fecha->Fecha));
            } else {
                $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_fecha')
                    ->where('IdFecha', $item->IdFecha)
                    ->first();
                
                if ($fechaData) {
                    $item->fecha_formateada = date('d/m/Y', strtotime($fechaData->Fecha));
                } else {
                    $item->fecha_formateada = '-';
                }
            }
            
            $item->nombre_operador = $item->operador?->identificador?->Nombre ?? 'N/A';
            $item->sucursal_nombre = $item->sucursal?->Nombre ?? 'N/A';
            
            return $item;
        });

        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        return Inertia::render('Gestion/Inventario/InventarioFisicoDiario/Index', [
            'inventarios' => $inventarios,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalId,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
        ]);
    }

    /**
     * Listado administrativo de inventarios físicos diarios (con agrupación por sucursal)
     */
    public function indexAdmin(Request $request)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $sucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->where('ActivoInactivo', 0)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre', 'NumeroSucursal as numero']);
        
        $query = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
            ->with(['operador', 'sucursal', 'fecha']);
        
        if ($request->filled('sucursal_id')) {
            $query->where('IdSucursal', $request->sucursal_id);
        } else {
            $query->where('IdSucursal', $sucursalId);
        }
        
        if ($request->filled('estado')) {
            if ($request->estado === 'completados') {
                $query->where('ActivoInactivo', 1);
            } elseif ($request->estado === 'borradores') {
                $query->where('ActivoInactivo', 0);
            } elseif ($request->estado === 'anulados') {
                $query->where('ActivoInactivo', 2);
            }
        }
        
        if ($request->filled('buscar')) {
            $query->where('NumeroCorrelativo', 'LIKE', '%' . $request->buscar . '%');
        }
        
        $inventarios = $query->orderBy('IdFisicoDiario', 'desc')
            ->paginate(20)
            ->appends($request->all());
        
        $inventarios->getCollection()->transform(function ($item) {
            if ($item->fecha && $item->fecha->Fecha) {
                $item->fecha_formateada = date('d/m/Y', strtotime($item->fecha->Fecha));
            } else {
                $fechaData = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_fecha')
                    ->where('IdFecha', $item->IdFecha)
                    ->first();
                
                if ($fechaData) {
                    $item->fecha_formateada = date('d/m/Y', strtotime($fechaData->Fecha));
                } else {
                    $item->fecha_formateada = '-';
                }
            }
            
            $item->nombre_operador = $item->operador?->identificador?->Nombre ?? 'N/A';
            $item->sucursal_nombre = $item->sucursal?->Nombre ?? 'N/A';
            
            return $item;
        });

        return Inertia::render('Gestion/Inventario/InventarioFisicoDiario/IndexAdmin', [
            'inventarios' => $inventarios,
            'sucursales' => $sucursales,
            'sucursalActual' => $sucursalId,
            'filtroEstado' => $request->estado,
            'buscar' => $request->buscar,
            'sucursalSeleccionada' => $request->sucursal_id,
        ]);
    }

    /**
     * Generar PDF del inventario físico diario
     */
    public function pdf($id)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            
            $cabecera = InventarioFisicoDiarioCabecera::where('IdFisicoDiario', $id)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->firstOrFail();
            
            $detalles = InventarioFisicoDiarioDetalle::where('IdFisicoDiario', $id)
                ->with('producto')
                ->get();
            
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first();
            
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $sucursalId)
                ->first();
            
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $cabecera->IdOperador)
                ->first();
            
            $fecha = Fecha::find($cabecera->IdFecha);
            $fechaFormateada = $fecha ? date('d/m/Y', strtotime($fecha->Fecha)) : '-';
            
            $pdf = new \TCPDF('P', 'mm', 'A4');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(10, 15, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 10);
            
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 8, 'INVENTARIO FÍSICO DIARIO', 0, 1, 'C');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 6, 'N° ' . ($cabecera->NumeroCorrelativo ?? 'SIN NÚMERO'), 0, 1, 'C');
            $pdf->Ln(4);
            
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 5, 'DATOS DEL INVENTARIO', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, 'Empresa: ' . ($empresa->Nombre ?? '-'), 0, 1, 'L');
            $pdf->Cell(0, 5, 'Sucursal: ' . ($sucursal->Nombre ?? '-'), 0, 1, 'L');
            $pdf->Cell(0, 5, 'Fecha: ' . $fechaFormateada, 0, 1, 'L');
            $pdf->Cell(0, 5, 'Operador: ' . ($operador->Nombre ?? 'N/A'), 0, 1, 'L');
            $pdf->Ln(4);
            
            $w = [10, 65, 25, 25, 25];
            $header = ['#', 'Producto', 'Sistema', 'Contado', 'Diferencia'];
            
            $pdf->SetFont('helvetica', 'B', 8);
            for ($i = 0; $i < count($header); $i++) {
                $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
            }
            $pdf->Ln();
            
            $pdf->SetFont('helvetica', '', 8);
            foreach ($detalles as $index => $detalle) {
                $producto = $detalle->producto->Descripcion ?? 'Sin nombre';
                $codigo = $detalle->producto->Codigo ?? '-';
                $sistema = number_format($detalle->CantidadSistema, 2);
                $contado = number_format($detalle->CantidadContada, 2);
                $diferencia = number_format($detalle->Diferencia, 2);
                
                $y = $pdf->GetY();
                if ($y > 250) {
                    $pdf->AddPage();
                    $pdf->SetFont('helvetica', 'B', 8);
                    for ($i = 0; $i < count($header); $i++) {
                        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
                    }
                    $pdf->Ln();
                    $pdf->SetFont('helvetica', '', 8);
                }
                
                $pdf->Cell($w[0], 6, $index + 1, 1, 0, 'C');
                $pdf->Cell($w[1], 6, $producto, 1, 0, 'L');
                $pdf->Cell($w[2], 6, $sistema, 1, 0, 'R');
                $pdf->Cell($w[3], 6, $contado, 1, 0, 'R');
                
                if ($detalle->Diferencia > 0) {
                    $pdf->SetTextColor(0, 150, 0);
                } elseif ($detalle->Diferencia < 0) {
                    $pdf->SetTextColor(200, 0, 0);
                }
                $pdf->Cell($w[4], 6, $diferencia, 1, 0, 'R');
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Ln();
            }
            
            $pdf->Ln(5);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Resumen:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 5, 'Total productos: ' . $detalles->count(), 0, 1, 'L');
            $pdf->Cell(0, 5, 'Productos con diferencia: ' . $detalles->filter(fn($d) => $d->Diferencia != 0)->count(), 0, 1, 'L');
            
            $pdf->Output("inventario_diario_{$cabecera->IdFisicoDiario}.pdf", 'I');
            exit;
            
        } catch (\Exception $e) {
            Log::error('Error generando PDF inventario diario: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener el IdTipoOperacion para "Inventario Fisico Diario" del cliente logueado
     */
    private function getTipoOperacionInventarioFisicoDiario()
    {
        $clienteId = session('cliente_id');
        
        $tipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_tipooperacion')
            ->where('IdCliente', $clienteId)
            ->where('Detalle', 'Inventario Fisico Diario')
            ->where('ActivoInactivo', 0)
            ->first();
        
        if (!$tipoOperacion) {
            $tipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdCliente', $clienteId)
                ->where('Concepto', 'InventarioFisico')
                ->where('ActivoInactivo', 0)
                ->first();
        }
        
        if (!$tipoOperacion) {
            throw new \Exception('No se encontró el tipo de operación "Inventario Fisico Diario" para el cliente ' . $clienteId);
        }
        
        return $tipoOperacion->IdTipoOperacion;
    }

    /**
     * Obtener almacén principal de la sucursal
     */
    private function getAlmacenPrincipal($clienteId, $sucursalId)
    {
        $almacen = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_almacen')
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('AlmacenPrincipal', 1)
            ->first();

        if (!$almacen) {
            $almacen = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_almacen')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->first();
        }

        return $almacen ? $almacen->IdAlmacen : null;
    }

    /**
     * Generar número correlativo por sucursal
     */
    private function generarNumeroCorrelativo($sucursalId)
    {
        $maxCorrelativo = InventarioFisicoDiarioCabecera::where('IdSucursal', $sucursalId)
            ->max('NumeroCorrelativo') ?? 0;
        
        return $maxCorrelativo + 1;
    }

    /**
     * Calcular saldo de un producto de inventario
     */
    private function calcularSaldoProducto($idProducto, $clienteId, $sucursalId, $fechaId)
    {
        $saldo = InventarioPropiamente::where('IdProducto', $idProducto)
            ->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdFecha', '<=', $fechaId)
            ->selectRaw("
                COALESCE(
                    SUM(CASE 
                        WHEN D_H = 'D' THEN Unidades 
                        WHEN D_H = 'H' THEN -Unidades 
                        ELSE 0 
                    END), 
                    0
                ) as saldo
            ")
            ->value('saldo') ?? 0;

        return (float) $saldo;
    }

    /**
     * 🔥 OBTENER PRODUCTOS BASE PARA EL INVENTARIO FÍSICO
     * 1. Obtiene productos de venta habilitados en la sucursal
     * 2. Obtiene los productos base (ingredientes) de esos productos de venta
     * 3. Solo muestra productos base únicos
     */
    private function obtenerProductosParaInventario($clienteId, $sucursalId)
    {
        // 🔥 PASO 1: Obtener productos de venta habilitados en la sucursal
        $productosVentaHabilitados = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_relacion_ventainventario as rv')
            ->join('inventario_producto_categoria as pc', 'rv.IdDetalleProducto', '=', 'pc.id_detalle_producto')
            ->where('rv.IdCliente', $clienteId)
            ->where('rv.IdSucursal', $sucursalId)
            ->where('rv.ActivoInactivo', 0)
            ->where('pc.id_sucursal', $sucursalId)
            ->select('rv.IdDetalleProducto')
            ->pluck('IdDetalleProducto')
            ->toArray();

        if (empty($productosVentaHabilitados)) {
            Log::warning('⚠️ No hay productos de venta habilitados en la sucursal', [
                'cliente' => $clienteId,
                'sucursal' => $sucursalId
            ]);
            return collect();
        }

        // 🔥 PASO 2: Obtener los productos base (ingredientes) de esos productos de venta
        $productosBase = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('inventario_productodetalle as p')
            ->join('inventario_relacion_ventainventario_detalle as det', 'p.IdProducto', '=', 'det.IdProducto')
            ->where('p.IdCliente', $clienteId)
            ->where('p.ActivoInactivo', 0)
            ->whereIn('det.IdDetalleProducto', $productosVentaHabilitados)
            ->select('p.IdProducto', 'p.Codigo', 'p.Descripcion')
            ->distinct()
            ->inRandomOrder()
            ->get();

        // 🔥 FALLBACK: Si no hay ingredientes, usar productos de venta como base
        if ($productosBase->isEmpty()) {
            Log::warning('⚠️ No hay productos base (ingredientes) asociados, usando productos de venta', [
                'cliente' => $clienteId,
                'sucursal' => $sucursalId
            ]);

            $productosBase = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario as rv')
                ->where('rv.IdCliente', $clienteId)
                ->where('rv.IdSucursal', $sucursalId)
                ->where('rv.ActivoInactivo', 0)
                ->whereIn('rv.IdDetalleProducto', $productosVentaHabilitados)
                ->select('rv.IdDetalleProducto as IdProducto', 'rv.Codigo', 'rv.Detalle as Descripcion')
                ->inRandomOrder()
                ->get();
        }

        // 🔥 FALLBACK 2: Si no hay nada, usar todos los productos de la sucursal
        if ($productosBase->isEmpty()) {
            Log::warning('⚠️ No hay productos, usando todos los productos de la sucursal', [
                'cliente' => $clienteId,
                'sucursal' => $sucursalId
            ]);

            $productosBase = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->where('IdCliente', $clienteId)
                ->where('ActivoInactivo', 0)
                ->select('IdProducto', 'Codigo', 'Descripcion')
                ->inRandomOrder()
                ->get();
        }

        return $productosBase;
    }

    /**
     * Crear nuevo borrador con productos aleatorios
     */
    private function crearNuevoBorrador($fechaId, $fecha, $clienteId, $sucursalId, $operadorId)
    {
        // 1. Obtener configuración activa
        $config = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('ActivoInactivo', 1)
            ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'No hay configuración activa para esta sucursal. Por favor, configure la cantidad de productos.'
            ], 404);
        }

        // 2. Eliminar borradores anteriores
        InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdOperador', $operadorId)
            ->where('IdFecha', $fechaId)
            ->where('ActivoInactivo', 0)
            ->delete();

        // 3. Obtener productos base de la sucursal
        $productosDisponibles = $this->obtenerProductosParaInventario($clienteId, $sucursalId);

        if ($productosDisponibles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay productos base disponibles para contar en esta sucursal. 
                              Por favor, asegúrese de que los productos de venta tengan ingredientes asociados.'
            ], 404);
        }

        // 4. 🔥 LIMITAR A LA CANTIDAD CONFIGURADA
        $cantidadConfigurada = $config->CantidadProductos;
        $cantidadReal = $productosDisponibles->count();
        $cantidadSeleccionada = min($cantidadConfigurada, $cantidadReal);

        if ($cantidadReal < $cantidadConfigurada) {
            Log::info('⚠️ Hay menos productos base disponibles que los configurados', [
                'configurados' => $cantidadConfigurada,
                'disponibles' => $cantidadReal,
                'seleccionados' => $cantidadSeleccionada,
                'sucursal' => $sucursalId,
                'cliente' => $clienteId
            ]);
        }

        // 5. Seleccionar los primeros N productos
        $productosSeleccionados = $productosDisponibles->take($cantidadSeleccionada);

        // 6. Calcular saldos
        $productos = [];
        foreach ($productosSeleccionados as $producto) {
            $saldo = $this->calcularSaldoProducto($producto->IdProducto, $clienteId, $sucursalId, $fechaId);

            $productos[] = (object) [
                'IdProducto' => $producto->IdProducto,
                'Codigo' => $producto->Codigo ?? '-',
                'Descripcion' => $producto->Descripcion ?? 'Sin nombre',
                'saldo_sistema' => (float) $saldo,
                'cantidad_contada' => null,
            ];
        }

        // 7. Obtener tipo de operación
        $idTipoOperacion = $this->getTipoOperacionInventarioFisicoDiario();

        // 8. Generar número correlativo
        $numeroCorrelativo = $this->generarNumeroCorrelativo($sucursalId);

        // 9. Crear cabecera
        $cabecera = InventarioFisicoDiarioCabecera::create([
            'IdFecha' => $fechaId,
            'IdCliente' => $clienteId,
            'IdSucursal' => $sucursalId,
            'IdOperador' => $operadorId,
            'CantidadTotalProductos' => $cantidadSeleccionada,
            'CantidadContados' => 0,
            'FechaRegistro' => now(),
            'ActivoInactivo' => 0,
            'NumeroCorrelativo' => null,
            'IdTipoOperacion' => $idTipoOperacion,
        ]);

        Log::info('📝 Borrador de mini inventario creado', [
            'id_cabecera' => $cabecera->IdFisicoDiario,
            'productos_disponibles' => $cantidadReal,
            'productos_configurados' => $cantidadConfigurada,
            'productos_seleccionados' => $cantidadSeleccionada,
            'operador' => $operadorId,
            'sucursal' => $sucursalId
        ]);

        // 10. Crear detalles
        foreach ($productos as $producto) {
            InventarioFisicoDiarioDetalle::create([
                'IdFisicoDiario' => $cabecera->IdFisicoDiario,
                'IdProducto' => $producto->IdProducto,
                'CantidadContada' => null,
                'CantidadSistema' => $producto->saldo_sistema,
                'Diferencia' => 0,
                'FechaRegistro' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'already_done' => false,
            'es_borrador' => false,
            'id_cabecera' => $cabecera->IdFisicoDiario,
            'productos' => $productos,
            'cantidad_requerida' => $cantidadSeleccionada,
            'fecha_str' => date('d/m/Y', strtotime($fecha->Fecha)),
            'fecha_id' => $fechaId,
            'numero_correlativo' => null,
            'cantidad_configurada' => $cantidadConfigurada,
            'total_disponibles' => $cantidadReal,
        ]);
    }

    /**
     * Obtener el estado del mini inventario para una fecha
     */
    public function getEstado($fechaId)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');

            $config = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->first();

            if (!$config) {
                return response()->json([
                    'success' => true,
                    'requiereMiniInventario' => false,
                    'motivo' => 'sin_configuracion',
                    'message' => 'No hay configuración activa para esta sucursal'
                ]);
            }

            $tieneBorradorActivo = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdOperador', $operadorId)
                ->where('IdFecha', $fechaId)
                ->where('ActivoInactivo', 0)
                ->exists();

            $tieneCompletado = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdOperador', $operadorId)
                ->where('IdFecha', $fechaId)
                ->where('ActivoInactivo', 1)
                ->exists();

            if ($tieneCompletado) {
                return response()->json([
                    'success' => true,
                    'requiereMiniInventario' => false,
                    'motivo' => 'ya_completado',
                    'message' => 'Ya has completado el inventario físico para esta fecha'
                ]);
            }

            return response()->json([
                'success' => true,
                'requiereMiniInventario' => true,
                'motivo' => $tieneBorradorActivo ? 'tiene_borrador' : 'nuevo',
                'cantidad_productos' => $config->CantidadProductos,
                'tiene_borrador' => $tieneBorradorActivo,
                'message' => $tieneBorradorActivo ? 'Tienes un inventario en progreso' : 'Debes realizar el inventario físico'
            ]);

        } catch (\Exception $e) {
            Log::error('Error obteniendo estado del mini inventario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener productos aleatorios para el mini inventario
     */
    public function obtenerProductos($fechaId)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');

            $config = InventarioFisicoDiarioConfig::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay configuración activa para esta sucursal'
                ], 404);
            }

            $borrador = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdOperador', $operadorId)
                ->where('IdFecha', $fechaId)
                ->where('ActivoInactivo', 0)
                ->first();

            $fecha = Fecha::find($fechaId);
            if (!$fecha) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fecha no encontrada'
                ], 404);
            }

            if ($borrador) {
                $detalles = InventarioFisicoDiarioDetalle::where('IdFisicoDiario', $borrador->IdFisicoDiario)
                    ->with('producto')
                    ->get();

                if ($detalles->isEmpty()) {
                    $borrador->delete();
                    return $this->crearNuevoBorrador($fechaId, $fecha, $clienteId, $sucursalId, $operadorId);
                }

                $productos = [];
                foreach ($detalles as $detalle) {
                    $saldoActual = $this->calcularSaldoProducto(
                        $detalle->IdProducto,
                        $clienteId,
                        $sucursalId,
                        $fechaId
                    );

                    $detalle->update(['CantidadSistema' => $saldoActual]);

                    $cantidadContada = null;
                    if ($detalle->CantidadContada !== null) {
                        $cantidadContada = (float) $detalle->CantidadContada;
                    }

                    $productos[] = (object) [
                        'IdProducto' => $detalle->IdProducto,
                        'Codigo' => $detalle->producto->Codigo ?? '',
                        'Descripcion' => $detalle->producto->Descripcion ?? '',
                        'saldo_sistema' => (float) $saldoActual,
                        'cantidad_contada' => $cantidadContada,
                    ];
                }

                return response()->json([
                    'success' => true,
                    'already_done' => false,
                    'es_borrador' => true,
                    'id_cabecera' => $borrador->IdFisicoDiario,
                    'productos' => $productos,
                    'cantidad_requerida' => $borrador->CantidadTotalProductos,
                    'fecha_str' => date('d/m/Y', strtotime($fecha->Fecha)),
                    'fecha_id' => $fechaId,
                    'numero_correlativo' => $borrador->NumeroCorrelativo,
                    'cantidad_configurada' => $config->CantidadProductos,
                ]);
            }

            return $this->crearNuevoBorrador($fechaId, $fecha, $clienteId, $sucursalId, $operadorId);

        } catch (\Exception $e) {
            Log::error('Error obteniendo productos: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar progreso del mini inventario (borrador automático)
     */
    public function guardarProgreso(Request $request)
    {
        $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.IdProducto' => 'required|integer',
            'productos.*.CantidadContada' => 'nullable|numeric|min:0',
            'IdFecha' => 'required|integer|exists:todos_fecha,IdFecha',
            'CantidadTotal' => 'required|integer|min:1',
            'id_cabecera' => 'nullable|integer',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $cabecera = null;
            
            if ($request->id_cabecera) {
                $cabecera = InventarioFisicoDiarioCabecera::where('IdFisicoDiario', $request->id_cabecera)
                    ->where('IdCliente', $clienteId)
                    ->where('IdOperador', $operadorId)
                    ->first();
            }

            if (!$cabecera) {
                $cabecera = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdOperador', $operadorId)
                    ->where('IdFecha', $request->IdFecha)
                    ->where('ActivoInactivo', 0)
                    ->first();
            }

            if (!$cabecera) {
                $idTipoOperacion = $this->getTipoOperacionInventarioFisicoDiario();
                
                $cabecera = InventarioFisicoDiarioCabecera::create([
                    'IdFecha' => $request->IdFecha,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'IdOperador' => $operadorId,
                    'CantidadTotalProductos' => $request->CantidadTotal,
                    'CantidadContados' => 0,
                    'FechaRegistro' => now(),
                    'ActivoInactivo' => 0,
                    'NumeroCorrelativo' => null,
                    'IdTipoOperacion' => $idTipoOperacion,
                ]);
            }

            $cantidadContados = 0;
            foreach ($request->productos as $productoData) {
                $cantidadContada = $productoData['CantidadContada'] !== null && $productoData['CantidadContada'] !== '' 
                    ? (float) $productoData['CantidadContada'] 
                    : null;
                
                $cantidadSistema = (float) ($productoData['CantidadSistema'] ?? 0);
                $diferencia = $cantidadContada !== null ? ($cantidadContada - $cantidadSistema) : 0;

                if ($cantidadContada !== null && $cantidadContada > 0) {
                    $cantidadContados++;
                }

                $detalle = InventarioFisicoDiarioDetalle::where('IdFisicoDiario', $cabecera->IdFisicoDiario)
                    ->where('IdProducto', $productoData['IdProducto'])
                    ->first();

                if ($detalle) {
                    $detalle->update([
                        'CantidadContada' => $cantidadContada,
                        'CantidadSistema' => $cantidadSistema,
                        'Diferencia' => $diferencia,
                        'FechaRegistro' => now(),
                    ]);
                } else {
                    InventarioFisicoDiarioDetalle::create([
                        'IdFisicoDiario' => $cabecera->IdFisicoDiario,
                        'IdProducto' => $productoData['IdProducto'],
                        'CantidadContada' => $cantidadContada,
                        'CantidadSistema' => $cantidadSistema,
                        'Diferencia' => $diferencia,
                        'FechaRegistro' => now(),
                    ]);
                }
            }

            $cabecera->update([
                'CantidadContados' => $cantidadContados,
                'FechaRegistro' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Progreso guardado correctamente',
                'id_cabecera' => $cabecera->IdFisicoDiario,
                'cantidad_contados' => $cantidadContados
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error guardando progreso: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar mini inventario COMPLETO y registrar en inventario_propiamente
     */
    public function guardarMiniInventario(Request $request)
    {
        $request->validate([
            'productos' => 'required|array|min:1',
            'productos.*.IdProducto' => 'required|integer',
            'productos.*.CantidadContada' => 'nullable|numeric|min:0',
            'IdFecha' => 'required|integer|exists:todos_fecha,IdFecha',
            'CantidadTotal' => 'required|integer|min:1',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        try {
            DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

            $cabecera = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdOperador', $operadorId)
                ->where('IdFecha', $request->IdFecha)
                ->where('ActivoInactivo', 0)
                ->first();

            if (!$cabecera) {
                $idTipoOperacion = $this->getTipoOperacionInventarioFisicoDiario();
                
                $cabecera = InventarioFisicoDiarioCabecera::create([
                    'IdFecha' => $request->IdFecha,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'IdOperador' => $operadorId,
                    'CantidadTotalProductos' => $request->CantidadTotal,
                    'CantidadContados' => 0,
                    'FechaRegistro' => now(),
                    'ActivoInactivo' => 0,
                    'NumeroCorrelativo' => null,
                    'IdTipoOperacion' => $idTipoOperacion,
                ]);
            }

            $idTipoOperacion = $cabecera->IdTipoOperacion;
            
            if (!$idTipoOperacion) {
                $idTipoOperacion = $this->getTipoOperacionInventarioFisicoDiario();
                $cabecera->update(['IdTipoOperacion' => $idTipoOperacion]);
            }

            $idAlmacen = $this->getAlmacenPrincipal($clienteId, $sucursalId);

            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $operadorId)
                ->first();

            $nombreOperador = $operador ? $operador->Nombre : 'Desconocido';
            $numeroCorrelativo = $this->generarNumeroCorrelativo($sucursalId);

            foreach ($request->productos as $productoData) {
                $cantidadContada = (float) ($productoData['CantidadContada'] ?? 0);
                $cantidadSistema = (float) ($productoData['CantidadSistema'] ?? 0);
                $diferencia = $cantidadContada - $cantidadSistema;

                $detalle = InventarioFisicoDiarioDetalle::where('IdFisicoDiario', $cabecera->IdFisicoDiario)
                    ->where('IdProducto', $productoData['IdProducto'])
                    ->first();

                if ($detalle) {
                    $detalle->update([
                        'CantidadContada' => $cantidadContada,
                        'CantidadSistema' => $cantidadSistema,
                        'Diferencia' => $diferencia,
                        'FechaRegistro' => now(),
                    ]);
                } else {
                    InventarioFisicoDiarioDetalle::create([
                        'IdFisicoDiario' => $cabecera->IdFisicoDiario,
                        'IdProducto' => $productoData['IdProducto'],
                        'CantidadContada' => $cantidadContada,
                        'CantidadSistema' => $cantidadSistema,
                        'Diferencia' => $diferencia,
                        'FechaRegistro' => now(),
                    ]);
                }

                if ($diferencia != 0) {
                    $d_h = $diferencia > 0 ? 'D' : 'H';
                    $glosa = "Inventario Fisico Diario N° {$numeroCorrelativo}; Op.{$nombreOperador}";
                    
                    InventarioPropiamente::create([
                        'IdTipoDeOperacion' => $idTipoOperacion,
                        'IdDocumento' => $cabecera->IdFisicoDiario,
                        'IdFecha' => $request->IdFecha,
                        'IdAlmacen' => $idAlmacen,
                        'IdProducto' => $productoData['IdProducto'],
                        'Glosa' => $glosa,
                        'D_H' => $d_h,
                        'Unidades' => abs($diferencia),
                        'Bolivianos' => 0,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                    ]);

                    Log::info('📦 Mini inventario - Ajuste registrado', [
                        'producto' => $productoData['IdProducto'],
                        'diferencia' => $diferencia,
                        'tipo' => $d_h,
                        'unidades' => abs($diferencia),
                        'id_cabecera' => $cabecera->IdFisicoDiario,
                        'numero_correlativo' => $numeroCorrelativo,
                        'operador' => $nombreOperador
                    ]);
                }
            }

            $cabecera->update([
                'CantidadContados' => count($request->productos),
                'ActivoInactivo' => 1,
                'NumeroCorrelativo' => $numeroCorrelativo,
                'FechaRegistro' => now(),
            ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('✅ Mini inventario COMPLETADO', [
                'id_cabecera' => $cabecera->IdFisicoDiario,
                'numero_correlativo' => $numeroCorrelativo,
                'operador' => $nombreOperador
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inventario físico completado correctamente',
                'id_cabecera' => $cabecera->IdFisicoDiario,
                'numero_correlativo' => $numeroCorrelativo,
                'continuar' => true
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error guardando mini inventario: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener inventario diario por fecha (para el modal)
     */
    public function obtenerPorFecha($fechaId)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');

            $cabecera = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdOperador', $operadorId)
                ->where('IdFecha', $fechaId)
                ->where('ActivoInactivo', 1)
                ->orderBy('IdFisicoDiario', 'desc')
                ->first();

            if (!$cabecera) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró inventario diario para esta fecha'
                ], 404);
            }

            return $this->obtenerPorId($cabecera->IdFisicoDiario);

        } catch (\Exception $e) {
            Log::error('Error al obtener inventario diario por fecha: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener inventario diario por ID (para el modal)
     */
    public function obtenerPorId($id)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');

            $cabecera = InventarioFisicoDiarioCabecera::where('IdFisicoDiario', $id)
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 1)
                ->first();

            if (!$cabecera) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró inventario diario'
                ], 404);
            }

            $detalles = InventarioFisicoDiarioDetalle::where('IdFisicoDiario', $cabecera->IdFisicoDiario)
                ->with('producto')
                ->get();

            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $cabecera->IdOperador)
                ->first();

            $fecha = Fecha::find($cabecera->IdFecha);
            $fechaFormateada = $fecha ? date('d/m/Y', strtotime($fecha->Fecha)) : '-';

            $detallesFormateados = $detalles->map(function($detalle) {
                return [
                    'producto' => $detalle->producto->Descripcion ?? 'Sin nombre',
                    'codigo' => $detalle->producto->Codigo ?? '-',
                    'sistema' => (float) $detalle->CantidadSistema,
                    'contado' => (float) $detalle->CantidadContada,
                    'diferencia' => (float) $detalle->Diferencia,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'id_cabecera' => $cabecera->IdFisicoDiario,
                    'numero_correlativo' => $cabecera->NumeroCorrelativo,
                    'fecha' => $fechaFormateada,
                    'operador' => $operador->Nombre ?? 'N/A',
                    'total_productos' => $detalles->count(),
                    'con_diferencia' => $detalles->filter(function($d) {
                        return $d->Diferencia != 0;
                    })->count(),
                    'detalles' => $detallesFormateados,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener inventario diario por ID: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el último inventario diario completado para una fecha
     */
    public function obtenerUltimoPorFecha($fechaId)
    {
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');

            $cabecera = InventarioFisicoDiarioCabecera::where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('IdOperador', $operadorId)
                ->where('IdFecha', $fechaId)
                ->where('ActivoInactivo', 1)
                ->orderBy('IdFisicoDiario', 'desc')
                ->first();

            if (!$cabecera) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró inventario diario para esta fecha'
                ], 404);
            }

            return $this->obtenerPorId($cabecera->IdFisicoDiario);

        } catch (\Exception $e) {
            Log::error('Error al obtener último inventario diario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}