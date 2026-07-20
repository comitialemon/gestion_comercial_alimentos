<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Services\TimezoneService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnularFacturaAdminController extends Controller
{
    protected $timezoneService;

    public function __construct(TimezoneService $timezoneService)
    {
        $this->timezoneService = $timezoneService;
    }

    public function index(Request $request)
    {
        $clienteId = session('cliente_id');
        
        // 🔥 Obtener TODAS las sucursales del cliente
        $todasSucursales = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_cliente_sucursal')
            ->where('IdCliente', $clienteId)
            ->orderBy('Nombre')
            ->get(['IdClienteSucursal as id', 'Nombre as nombre']);

        $sucursales = $todasSucursales;
        $buscarSucursal = $request->buscar_sucursal;
        
        if ($buscarSucursal) {
            $sucursales = $todasSucursales->filter(function($s) use ($buscarSucursal) {
                return stripos($s->nombre, $buscarSucursal) !== false;
            });
        }

        $operadores = collect();
        $buscarOperador = $request->buscar_operador;
        $sucursalId = $request->sucursal_id;
        
        if ($sucursalId) {
            $query = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->join('todos_operador_sucursaldb as os', 'o.IdOperador', '=', 'os.IdOperador')
                ->where('os.IdCliente', $clienteId)
                ->where('os.IdSucursal', $sucursalId)
                ->where('o.ActivoInactivo', 0)
                ->select('o.IdOperador as id', 'i.Nombre as nombre', 'i.CI_NIT as ci')
                ->distinct();
            
            if ($buscarOperador) {
                $query->where(function($q) use ($buscarOperador) {
                    $q->where('i.Nombre', 'LIKE', "%{$buscarOperador}%")
                      ->orWhere('i.CI_NIT', 'LIKE', "%{$buscarOperador}%");
                });
            }
            
            $operadores = $query->orderBy('i.Nombre')->limit(50)->get();
        }

        $queryFacturas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('todos_cliente_sucursal as s', 'v.IdClienteSucursal', '=', 's.IdClienteSucursal')
            ->join('todos_operador as o', 'v.IdOperadorIngresa', '=', 'o.IdOperador')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdEstado', 1)
            ->where('v.ActivoInactivo', 1)
            ->where('v.LiquidadoVendedor', 0);

        if ($sucursalId) {
            $queryFacturas->where('v.IdClienteSucursal', $sucursalId);
        }

        if ($request->filled('operador_id')) {
            $queryFacturas->where('v.IdOperadorIngresa', $request->operador_id);
        }

        if ($request->filled('fecha')) {
            $queryFacturas->whereDate('v.FechaVenta', $request->fecha);
        }

        $facturas = $queryFacturas->select(
                'v.IdVentas', 
                'v.NumeroFactura', 
                'v.ImporteVenta', 
                'v.FechaVenta',
                'v.IdClienteSucursal',
                's.Nombre as sucursal_nombre',
                'i.Nombre as operador_nombre'
            )
            ->orderBy('v.FechaVenta', 'desc')
            ->orderBy('v.NumeroFactura', 'desc')
            ->get();

        return Inertia::render('Gestion/Impuestos/AnularFactura/AdminIndex', [
            'facturas' => $facturas,
            'sucursales' => $sucursales,
            'todasSucursales' => $todasSucursales,
            'operadores' => $operadores,
            'filtros' => [
                'sucursal_id' => $sucursalId,
                'operador_id' => $request->operador_id,
                'fecha' => $request->fecha,
                'buscar_sucursal' => $buscarSucursal,
                'buscar_operador' => $buscarOperador,
            ]
        ]);
    }
    /**
     * 🔥 ANULAR FACTURA CON REVERSIÓN DE INVENTARIO (ENTRADA)
     * 🔥 CORREGIDO: Busca IDs por nombre (dinámico) en lugar de IDs fijos
     */
    public function anular(Request $request)
    {
        Log::info('=== INICIO ANULAR FACTURA ADMIN ===');
        Log::info('ID Venta: ' . $request->IdVentas);
        Log::info('Operador ID: ' . session('operador_id'));
        
        $request->validate([
            'IdVentas' => 'required|exists:impuestos_ventas,IdVentas',
        ]);

        $clienteId = session('cliente_id');
        $operadorId = session('operador_id');

        // 🔥 OBTENER NOMBRE DEL OPERADOR QUE ANULA
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $operadorId)
            ->first();

        $nombreOperador = $operador ? $operador->Nombre : 'Desconocido';
        Log::info('Operador que anula: ' . $nombreOperador);

        $factura = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $request->IdVentas)
            ->where('IdCliente', $clienteId)
            ->where('IdEstado', 1)
            ->where('ActivoInactivo', 1)
            ->where('LiquidadoVendedor', 0)
            ->first();

        if (!$factura) {
            Log::error('❌ Factura no encontrada o no válida');
            return response()->json([
                'success' => false,
                'message' => 'La factura no existe, ya fue anulada o ya fue liquidada.'
            ], 422);
        }

        Log::info('✅ Factura encontrada:', [
            'IdVentas' => $factura->IdVentas,
            'NumeroFactura' => $factura->NumeroFactura,
            'IdEstado' => $factura->IdEstado,
            'LiquidadoVendedor' => $factura->LiquidadoVendedor,
        ]);

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // 🔥 1. OBTENER ID DE "Ventas" POR NOMBRE (DINÁMICO)
            $idTipoVentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdCliente', $clienteId)
                ->where('Detalle', 'Ventas')
                ->where('ActivoInactivo', 0)
                ->value('IdTipoOperacion');

            Log::info('🔑 ID de Ventas (por nombre): ' . ($idTipoVentas ?? 'NO ENCONTRADO'));

            if (!$idTipoVentas) {
                throw new \Exception('No se encontró el tipo de operación "Ventas" para este cliente');
            }

            // 🔥 2. OBTENER MOVIMIENTOS DE INVENTARIO
            $movimientos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->where('IdTipoDeOperacion', $idTipoVentas)
                ->where('IdDocumento', $request->IdVentas)
                ->get();

            Log::info('📦 Movimientos de inventario encontrados: ' . count($movimientos));
            
            if ($movimientos->isEmpty()) {
                Log::warning('⚠️ No se encontraron movimientos de inventario para esta factura');
            }

            // 🔥 3. OBTENER FECHA ACTUAL
            $fechaActual = $this->timezoneService->getFechaActual();
            Log::info('📅 Fecha actual: ' . $fechaActual);
            
            $idFecha = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->where('Fecha', $fechaActual)
                ->value('IdFecha');
            
            if (!$idFecha) {
                $idFecha = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_fecha')
                    ->insertGetId([
                        'Fecha' => $fechaActual,
                        'ActivoInactivo' => 1,
                        'CierreSucursal' => 0,
                        'CierrePermanente' => 0,
                    ]);
                Log::info('📅 Fecha creada: ' . $idFecha);
            }

            // 🔥 4. OBTENER ID DE "Anulación Venta" POR NOMBRE (DINÁMICO)
            $idTipoAnulacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdCliente', $clienteId)
                ->where('Detalle', 'Anulación Venta')
                ->where('ActivoInactivo', 0)
                ->value('IdTipoOperacion');

            Log::info('🔑 ID Anulación Venta (por nombre): ' . ($idTipoAnulacion ?? 'NO ENCONTRADO'));

            if (!$idTipoAnulacion) {
                throw new \Exception('No se encontró el tipo de operación "Anulación Venta" para este cliente');
            }

            // 🔥 5. ELIMINAR REVERSIÓN ANTERIOR (si existe)
            $eliminados = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->where('IdTipoDeOperacion', $idTipoAnulacion)
                ->where('IdDocumento', $request->IdVentas)
                ->delete();
            
            Log::info('🗑️ Reversiones anteriores eliminadas: ' . $eliminados);

            // 🔥 6. CREAR MOVIMIENTOS DE REVERSIÓN
            $movimientosCreados = 0;
            foreach ($movimientos as $mov) {
                Log::info('🔄 Creando reversión para producto ID: ' . $mov->IdProducto . ', Unidades: ' . $mov->Unidades);
                
                $insertado = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->insert([
                        'IdTipoDeOperacion' => $idTipoAnulacion,
                        'IdDocumento' => $request->IdVentas,
                        'IdFecha' => $idFecha,
                        'IdAlmacen' => $mov->IdAlmacen,
                        'IdProducto' => $mov->IdProducto,
                        'Glosa' => "ANULACIÓN Venta Factura No {$factura->NumeroFactura}; Op.{$nombreOperador}",
                        'D_H' => 'D',
                        'Unidades' => $mov->Unidades,
                        'Bolivianos' => $mov->Bolivianos,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $factura->IdClienteSucursal,
                    ]);
                
                if ($insertado) {
                    $movimientosCreados++;
                    Log::info('✅ Reversión creada correctamente');
                } else {
                    Log::error('❌ Error al crear reversión');
                }
            }

            // 🔥 7. ANULAR FACTURA
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $request->IdVentas)
                ->update([
                    'IdEstado' => 2,
                    'IdOperadorActualiza' => $operadorId,
                    'FechaUltimaActualizcion' => $this->timezoneService->getFechaHoraActual(),
                ]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            Log::info('✅ ANULACIÓN ADMIN COMPLETA: ' . $movimientosCreados . ' movimientos creados');

            return response()->json([
                'success' => true,
                'message' => "✅ Factura N° {$factura->NumeroFactura} anulada correctamente. Se crearon {$movimientosCreados} movimientos de reversión. Operador: {$nombreOperador}",
                'numero_factura' => $factura->NumeroFactura,
                'id_ventas' => $factura->IdVentas,
                'movimientos_revertidos' => $movimientosCreados,
                'operador' => $nombreOperador,
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('❌ Error anulando factura: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al anular la factura: ' . $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Generar PDF de factura anulada (con altura dinámica precisa)
     */
    public function pdf($id)
    {
        try {
            $clienteId = session('cliente_id');
            
            \Log::info('=== PDF ADMIN INICIADO ===', ['id' => $id]);
            
            // Obtener factura
            $factura = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->where('IdCliente', $clienteId)
                ->first();
            
            if (!$factura) {
                abort(404, 'Factura no encontrada');
            }
            
            // Obtener empresa
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first();
            
            // Obtener sucursal
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $factura->IdClienteSucursal)
                ->first();
            
            // Obtener cliente
            $clienteFactura = null;
            if ($factura->IdNIT) {
                $clienteFactura = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_identificador')
                    ->where('IdIdentificador', $factura->IdNIT)
                    ->first();
            }
            
            $nombreCliente = $clienteFactura ? $clienteFactura->Nombre : 'CONSUMIDOR FINAL';
            $nitCliente = $clienteFactura ? $clienteFactura->CI_NIT : '0';
            
            // Obtener detalles
            $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle as d')
                ->join('inventario_relacion_ventainventario as p', 'd.idrelacionventainventario', '=', 'p.IdDetalleProducto')
                ->where('d.idventas', $id)
                ->select('p.Detalle as nombre', 'd.unidades', 'd.preciounidades', 'd.totalbolivianos')
                ->get();
            
            // Obtener pagos
            $pagos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion as l')
                ->join('impuestos_ventas_liquidacion_concepto as c', 'l.IdCuenta', '=', 'c.IdCuenta')
                ->leftJoin('todos_identificador as i', 'l.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('l.IdVentas', $id)
                ->select('c.Concepto', 'l.Bolivianos', 'i.CI_NIT', 'i.Nombre as identificador_nombre')
                ->get();
            
            // Obtener comisionista
            $comisionista = null;
            if ($factura->IdComisionista && $factura->IdComisionista > 0) {
                $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_comisionitas as c')
                    ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                    ->where('c.IdComisionista', $factura->IdComisionista)
                    ->first();
            }
            
            // Obtener operador
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $factura->IdOperadorIngresa)
                ->first();
            
            // Obtener lugar de venta
            $lugarVenta = null;
            if ($factura->LugarVenta && $factura->LugarVenta > 0) {
                $lugar = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_lugar_venta')
                    ->where('IdLugar', $factura->LugarVenta)
                    ->first();
                $lugarVenta = $lugar ? $lugar->Lugar : null;
            }
            
            // Calcular totales
            $totalGeneral = $detalles->sum('totalbolivianos');
            $totalPagos = $pagos->sum('Bolivianos');
            
            // 🔥 CALCULAR ALTURA DINÁMICA PRECISA
            $lineHeight = 4;      // Altura de línea estándar
            $lineSmall = 3;       // Altura de línea pequeña (para NIT)
            $margin = 4;          // Márgenes
            
            // Iniciar en Y = 4 (margen superior)
            $y = 4;
            
            // Cabecera (6 líneas)
            $y += 5; // Empresa
            $y += 4; // Sucursal
            $y += 4; // Dirección
            $y += 4; // Teléfono
            $y += 4; // SANTA CRUZ
            $y += 6; // Espacio antes de RECIBO
            $y += 5; // RECIBO
            $y += 4; // N°
            $y += 2; // Espacio
            
            // Datos del cliente (4-5 líneas)
            $y += 4; // Fecha
            $y += 4; // NIT/CI
            $y += 4; // CLIENTE
            if ($lugarVenta) {
                $y += 4; // SERVICIO EN
            }
            $y += 2; // Espacio antes de tabla
            
            // Cabecera de tabla (2 líneas)
            $y += 4; // Títulos CANT, PRODUCTO, P.U., TOTAL
            $y += 2; // Línea separadora
            
            // Productos (cada producto puede tener múltiples líneas)
            foreach ($detalles as $detalle) {
                $nombre = $detalle->nombre ?? 'Producto';
                // Calcular cuántas líneas ocupa el nombre (30 caracteres por línea)
                $lineasNombre = ceil(strlen($nombre) / 30);
                $y += $lineasNombre * $lineHeight;
            }
            
            $y += 2; // Línea separadora después de productos
            $y += 5; // TOTAL
            
            // Pagos
            foreach ($pagos as $pago) {
                $y += 4; // Concepto y monto
                if ($pago->CI_NIT && $pago->CI_NIT != 0) {
                    $y += $lineSmall; // Línea de NIT/CI
                }
            }
            
            $y += 5; // TOTAL PAGO
            $y += 6; // Literal (SON: ...)
            
            // Literal puede tener varias líneas
            $literal = $this->convertirNumeroALetras(round($totalGeneral, 2));
            $lineasLiteral = ceil(strlen($literal) / 50);
            $y += $lineasLiteral * 3;
            
            $y += 2; // Espacio
            
            // Comisionista (si existe)
            if ($comisionista) {
                $y += 4;
            }
            
            // Operador
            $y += 4;
            
            // Ticket
            $y += 4;
            
            // Línea final
            $y += 6;
            
            // Altura final de la página (margen inferior de 10mm)
            $pageHeight = $y + 10;
            
            \Log::info('Altura calculada', ['y_final' => $y, 'page_height' => $pageHeight]);
            
            // =============================================
            // GENERAR PDF
            // =============================================
            $pdf = new \TCPDF('P', 'mm', array(72, $pageHeight));
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(4, 4, 4);
            $pdf->SetAutoPageBreak(false);  // 🔥 Desactivar auto page break
            $pdf->AddPage();
            
            // Marca de agua "ANULADO"
            if ($factura->IdEstado == 2) {
                $pdf->SetAlpha(0.3);
                $pdf->SetFont('helvetica', 'B', 38);
                $pdf->SetTextColor(255, 0, 0);
                $pdf->StartTransform();
                $pdf->Rotate(-25, 36, 45);
                $pdf->SetXY(10, 45);
                $pdf->Cell(52, 15, "ANULADO", 0, 1, 'C');
                $pdf->StopTransform();
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetAlpha(1);
            }
            
            // Reiniciar Y para escribir
            $y = 4;
            
            // Cabecera
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 5, $empresa->Nombre ?? '', 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "SUCURSAL " . ($sucursal->NumeroSucursal ?? ''), 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, $sucursal->Direccion ?? '', 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "Tel.: " . ($sucursal->Telefono ?? '') . " - Cel.: " . ($sucursal->Celular ?? ''), 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "SANTA CRUZ - BOLIVIA", 0, 1, 'C');
            $y += 6;
            
            // Título
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 5, "RECIBO", 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "N° " . ($factura->NumeroFactura ?? '0'), 0, 1, 'C');
            $y += 2;
            
            // Datos del cliente
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "FECHA: " . date('d/m/Y H:i:s', strtotime($factura->FechaVenta)), 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "NIT/CI: " . $nitCliente, 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "CLIENTE: " . $nombreCliente, 0, 1, 'L');
            $y += 4;
            
            if ($lugarVenta) {
                $pdf->SetXY(4, $y);
                $pdf->Cell(64, 4, "SERVICIO EN: " . $lugarVenta, 0, 1, 'L');
                $y += 4;
            }
            $y += 2;
            
            // Tabla de productos - Cabecera
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY(4, $y);
            $pdf->Cell(8, 4, "CANT", 0, 0, 'L');
            $pdf->Cell(35, 4, "PRODUCTO", 0, 0, 'L');
            $pdf->Cell(10, 4, "P.U.", 0, 0, 'R');
            $pdf->Cell(11, 4, "TOTAL", 0, 1, 'R');
            
            $pdf->SetXY(4, $y + 1);
            $pdf->Cell(64, 1, "", 'T', 1);
            $y += 4;
            
            // Tabla de productos - Detalles
            $pdf->SetFont('helvetica', '', 7);
            foreach ($detalles as $detalle) {
                $nombreProducto = $detalle->nombre ?? 'Producto';
                $cantidad = $detalle->unidades ?? 0;
                $precio = $detalle->preciounidades ?? 0;
                $subtotal = $detalle->totalbolivianos ?? 0;
                
                $startY = $y;
                
                // Cantidad
                $pdf->SetXY(4, $startY);
                $pdf->Cell(8, 4, number_format($cantidad, 0), 0, 0, 'L');
                
                // Precio unitario
                $pdf->SetXY(4 + 43, $startY);
                $pdf->Cell(10, 4, number_format($precio, 2, '.', ','), 0, 0, 'R');
                
                // Total
                $pdf->SetXY(4 + 53, $startY);
                $pdf->Cell(11, 4, number_format($subtotal, 2, '.', ','), 0, 1, 'R');
                
                // Producto (MultiCell)
                $pdf->SetXY(4 + 8, $startY);
                $pdf->MultiCell(35, 4, $nombreProducto, 0, 'L');
                
                $y = $pdf->GetY();
            }
            
            // Línea separadora después de productos
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 1, "", 'T', 1);
            $y += 3;
            
            // Total
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY(4, $y);
            $pdf->Cell(53, 5, "TOTAL:", 0, 0, 'R');
            $pdf->Cell(11, 5, number_format($totalGeneral, 2, '.', ','), 0, 1, 'R');
            $y += 6;
            
            // Pagos
            foreach ($pagos as $pago) {
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetXY(4, $y);
                $pdf->Cell(53, 4, $pago->Concepto, 0, 0, 'R');
                $pdf->Cell(11, 4, number_format($pago->Bolivianos, 2, '.', ','), 0, 1, 'R');
                $y += 4;
                
                if ($pago->CI_NIT && $pago->CI_NIT != 0) {
                    $pdf->SetFont('helvetica', '', 6);
                    $pdf->SetXY(4 + 5, $y);
                    $pdf->Cell(59, 3, "NIT/CI: " . $pago->CI_NIT . " - " . ($pago->identificador_nombre ?? 'SIN NOMBRE'), 0, 1, 'L');
                    $y += 3;
                }
            }
            
            // Total pagado
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY(4, $y);
            $pdf->Cell(53, 5, "TOTAL PAGO:", 0, 0, 'R');
            $pdf->Cell(11, 5, number_format($totalPagos, 2, '.', ','), 0, 1, 'R');
            $y += 6;
            
            // Literal
            $pdf->SetFont('helvetica', '', 6);
            $literal = $this->convertirNumeroALetras(round($totalGeneral, 2));
            $pdf->SetXY(4, $y);
            $pdf->MultiCell(64, 3, "SON: " . $literal, 0, 'L');
            $y = $pdf->GetY() + 2;
            
            // Comisionista
            if ($comisionista) {
                $pdf->SetXY(4, $y);
                $pdf->Cell(64, 4, "COMISIONISTA: " . ($comisionista->Nombre ?? ''), 0, 1, 'L');
                $y += 4;
            }
            
            // Vendedor
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "VENDEDOR: " . ($operador->Nombre ?? ''), 0, 1, 'L');
            $y += 4;
            
            // Ticket
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 4, "TICKET: " . ($factura->TicketDia ?? '0'), 0, 1, 'L');
            $y += 6;
            
            // Línea final
            $pdf->SetXY(4, $y);
            $pdf->Cell(64, 1, "________________________________________", 0, 1, 'C');
            
            \Log::info('PDF generado exitosamente');
            
            $pdf->Output("factura_anulada_{$factura->NumeroFactura}.pdf", 'I');
            exit;
            
        } catch (\Exception $e) {
            \Log::error('Error generando PDF de factura anulada: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            abort(500, 'Error al generar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Convertir número a letras (copia del método que ya tienes)
     */
    private function convertirNumeroALetras($numero)
    {
        $partes = explode('.', number_format($numero, 2, '.', ''));
        $entero = (int)$partes[0];
        $decimal = isset($partes[1]) ? (int)$partes[1] : 0;
        
        $literal = number_format($numero, 2) . " (" . $entero . " BOLIVIANOS CON " . str_pad($decimal, 2, '0', STR_PAD_LEFT) . " CENTAVOS)";
        
        return $literal;
    }


}