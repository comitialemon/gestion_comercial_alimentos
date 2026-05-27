<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Contabilidad\MetodoPagoMapeo;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Services\Impuestos\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class PagoVentaController extends Controller
{
    protected $ventaService;

    public function __construct(VentaService $ventaService)
    {
        $this->ventaService = $ventaService;
    }

    /**
     * Mostrar formulario de pago para venta NORMAL
     */
    public function create()
    {
        $ventaId = session('venta_actual_id');
        $tieneFacturacion = session('tiene_facturacion', false);
        
        if (!$ventaId) {
            return redirect()->route('ventas.formulario')->with('error', 'No hay una venta activa');
        }
        
        $venta = Venta::with('detalles')->findOrFail($ventaId);
        $deuda = (float) $this->ventaService->getDeuda($ventaId);
        
        // 🔥 OBTENER EL NIT DE LA EMPRESA ACTUAL
        $nitEmpresa = session('cliente_nit');
        
        $productos = [];
        foreach ($venta->detalles as $detalle) {
            $productos[] = [
                'descripcionLibre' => 'Producto',
                'unidades' => (float) $detalle->unidades,
                'precioUnitario' => (float) $detalle->preciounidades,
                'total' => (float) $detalle->totalbolivianos,
            ];
        }
        
        return $this->renderPagoView($tieneFacturacion, [
            'venta' => $venta,
            'deuda' => $deuda,
            'productos' => $productos,
            'ventaId' => $ventaId,
            'tipoVenta' => 'normal',
            'volverRuta' => '/venta-factura/nueva',
            'clienteNit' => $nitEmpresa, // 🔥 PASAR EL NIT DE LA EMPRESA
        ]);
    }

    /**
     * Mostrar formulario de pago para venta TÁCTIL
     */
    public function createTactil()
    {
        $ventaId = session('venta_tactil_id');
        $tieneFacturacion = session('tiene_facturacion', false);
        
        if (!$ventaId) {
            return redirect()->route('venta-tactil.nueva')->with('error', 'No hay una venta activa');
        }
        
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $ventaId)
            ->first();
        
        if (!$venta || $venta->ActivoInactivo == 1) {
            return redirect()->route('venta-tactil.nueva')->with('error', 'La venta ya fue finalizada');
        }
        
        $productos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle as d')
            ->join('inventario_relacion_ventainventario as p', 'd.idrelacionventainventario', '=', 'p.IdDetalleProducto')
            ->where('d.idventas', $ventaId)
            ->select(
                DB::raw("'Producto' as descripcionLibre"),
                'd.unidades',
                'd.preciounidades as precioUnitario',
                'd.totalbolivianos as total'
            )
            ->get();
        
        $deuda = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle')
            ->where('idventas', $ventaId)
            ->sum('totalbolivianos');
        
        // 🔥 OBTENER EL NIT DE LA EMPRESA ACTUAL
        $nitEmpresa = session('cliente_nit');
        
        return $this->renderPagoView($tieneFacturacion, [
            'venta' => (object) $venta,
            'deuda' => (float) $deuda,
            'productos' => $productos,
            'ventaId' => $ventaId,
            'tipoVenta' => 'tactil',
            'volverRuta' => '/venta-tactil/carrito',
            'clienteNit' => $nitEmpresa, // 🔥 PASAR EL NIT DE LA EMPRESA
        ]);
    }

    /**
     * Renderizar la vista de pago correcta según facturación
     */
    private function renderPagoView($tieneFacturacion, $data)
    {
        if ($tieneFacturacion) {
            return Inertia::render('PuntoVenta/PagoVentaFacturacion', $data);
        } else {
            return Inertia::render('PuntoVenta/PagoVentaSinFacturacion', $data);
        }
    }

    /**
     * API: Obtener métodos de pago (SOLO los que están en el mapeo)
     */
    public function getMetodosPago()
    {
        $mapeos = MetodoPagoMapeo::where('idCliente', session('cliente_id'))
            ->where('idSucursal', session('cliente_sucursal_id'))
            ->where('activo', 1)
            ->get();
        
        $codigosUnicos = $mapeos->pluck('codigo_siat')->unique()->values();
        
        $metodosPago = [];
        
        if ($codigosUnicos->isNotEmpty()) {
            try {
                $response = Http::timeout(10)->get('http://siat-app:80/api/v1/metodos-pago');
                if ($response->successful()) {
                    $data = $response->json();
                    $todosMetodos = isset($data['data']) ? $data['data'] : $data;
                    
                    foreach ($todosMetodos as $metodo) {
                        if (in_array($metodo['codigo'], $codigosUnicos->toArray())) {
                            $cuentasRelacionadas = $mapeos->where('codigo_siat', $metodo['codigo'])->map(function($item) {
                                $cuenta = ContaCuenta::find($item->idContaCuenta);
                                return [
                                    'id' => $item->idContaCuenta,
                                    'nombre' => $cuenta->Cuenta ?? 'Cuenta ' . $item->idContaCuenta,
                                    'descripcion' => $cuenta->Descripcion ?? '',
                                ];
                            })->values();
                            
                            $metodosPago[] = [
                                'id' => $metodo['codigo'],
                                'codigo' => $metodo['codigo'],
                                'descripcion' => $metodo['descripcion'],
                                'cuentas' => $cuentasRelacionadas,
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Error obteniendo métodos de pago: ' . $e->getMessage());
            }
        }
        
        return response()->json($metodosPago);
    }

    public function buscarCliente(Request $request)
    {
        $request->validate(['nit' => 'required|string']);
        
        try {
            $nitEmisor = session('cliente_nit');
            $codigoSistema = env('SIAT_CODIGO_SISTEMA', '');
            
            $url = env('FACTURACION_API_URL', 'http://siat-app:80') . '/api/v1/verificar-nit';
            
            $response = Http::timeout(15)->post($url, [
                'nit' => $request->nit,
                'nit_emisor' => $nitEmisor,
                'codigo_sistema' => $codigoSistema,
                'ambiente' => 2,
                'modalidad' => 1,
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'existe' => $data['existe'] ?? false,
                    'nombre' => $data['nombre'] ?? null,
                    'mensaje' => $data['mensaje'] ?? ($data['existe'] ? 'NIT VÁLIDO' : 'NIT NO ENCONTRADO')
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => 'Error en la respuesta de Facturación'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error verificando NIT: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Procesar pago SIN facturación (completo con inventario)
     */
    public function procesarPagoSinFacturacion(Request $request)
    {
        try {
            $request->validate([
                'venta_id' => 'required|exists:impuestos_ventas,IdVentas',
                'montos' => 'required|array',
                'tipo_venta' => 'required|string|in:normal,tactil',
                'id_identificador_cliente' => 'nullable|exists:todos_identificador,IdIdentificador'
            ]);

            DB::beginTransaction();

            $ventaId = $request->venta_id;
            
            // 🔥 OBTENER EL ID DEL CLIENTE SELECCIONADO
            $idNIT = $request->id_identificador_cliente;
            
            // Si NO se seleccionó cliente, usar CONSUMIDOR FINAL (ID 1)
            if (!$idNIT || $idNIT == 0) {
                $idNIT = 1;
            }

            // 🔥 LOG PARA VER QUÉ LLEGA
            \Log::info('=== PROCESANDO PAGO ===');
            \Log::info('Venta ID: ' . $ventaId);
            \Log::info('id_identificador_cliente recibido: ' . ($request->id_identificador_cliente ?? 'NULL'));
            \Log::info('ID NIT que se guardará: ' . $idNIT);

            $ventaActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->first();
            
            if (!$ventaActual) {
                throw new \Exception('Venta no encontrada');
            }
            
            // Insertar liquidación (métodos de pago)
            foreach ($request->montos as $conceptoId => $monto) {
                if ($monto > 0) {
                    $concepto = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion_concepto')
                        ->where('IdConceptoLiquidacion', $conceptoId)
                        ->first();
                    
                    $idCuentaReal = $concepto ? $concepto->IdCuenta : $conceptoId;
                    
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_liquidacion')
                        ->insert([
                            'IdVentas' => $ventaId,
                            'IdDiario' => 0,
                            'IdIdentificador' => $idNIT, // 🔥 ID del cliente
                            'IdCuenta' => $idCuentaReal,
                            'Bolivianos' => $monto,
                        ]);
                }
            }

            // Obtener último número de factura
            $ultimoNumeroFactura = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', session('cliente_id'))
                ->where('IdClienteSucursal', session('cliente_sucursal_id'))
                ->max('NumeroFactura');

            $nuevoNumeroFactura = ($ultimoNumeroFactura ?? 0) + 1;

            // 🔥 ACTUALIZAR VENTA con el ID del cliente seleccionado
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update([
                    'ActivoInactivo' => 1,
                    'FechaUltimaActualizcion' => now(),
                    'IdNIT' => $idNIT, // 🔥 Guarda el ID del cliente
                    'NumeroFactura' => $nuevoNumeroFactura,
                    'IdEstado' => 1,
                    'IdOperadorActualiza' => session('operador_id'),
                ]);

            // Registrar salida de inventario
            $this->registrarSalidaInventario($ventaId);

            DB::commit();

            // Limpiar sesión
            if ($request->tipo_venta === 'tactil') {
                session()->forget('venta_tactil_id');
                session()->forget('venta_tactil_lugar_id');
                session()->forget('venta_tactil_comisionista_id');
                session()->forget('venta_tactil_comisionista_identificador');
            } else {
                session()->forget('venta_actual_id');
            }

            return response()->json([
                'success' => true, 
                'message' => 'Venta completada',
                'pdf_url' => route('ventas.factura-pdf', $ventaId),
                'id_nit_guardado' => $idNIT
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error procesando pago: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Registrar salida de inventario (descontar productos)
     */
    private function registrarSalidaInventario($ventaId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        
        $venta = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdVentas', $ventaId)
            ->first();
        
        if (!$venta) {
            throw new \Exception('Venta no encontrada');
        }
        
        $fechaVenta = date('Y-m-d', strtotime($venta->FechaVenta));
        $idFecha = $this->obtenerIdFecha($fechaVenta);
        
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
        
        $idTipoOperacion = 2;
        
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle')
            ->where('idventas', $ventaId)
            ->get();
        
        foreach ($detalles as $detalle) {
            $productosPorcion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->get();
            
            foreach ($productosPorcion as $porcion) {
                $cantidad = $porcion->Porcion * $detalle->unidades;
                
                $precioCosto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_productodetalle_precio_costo')
                    ->where('IdProducto', $porcion->IdProducto)
                    ->orderBy('IdPrecioCosto', 'DESC')
                    ->value('PrecioCosto');
                
                $costoTotal = $cantidad * ($precioCosto ?? 0);
                
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->insert([
                        'IdTipoDeOperacion' => $idTipoOperacion,
                        'IdDocumento' => $ventaId,
                        'IdFecha' => $idFecha,
                        'IdAlmacen' => $idAlmacen,
                        'IdProducto' => $porcion->IdProducto,
                        'Glosa' => "Venta Factura No {$venta->NumeroAutorizacion} - {$venta->NumeroFactura}",
                        'D_H' => 'H',
                        'Unidades' => $cantidad,
                        'Bolivianos' => $costoTotal,
                        'IdCliente' => $clienteId,
                        'IdSucursal' => $sucursalId,
                    ]);
            }
        }
    }

    /**
     * Obtener o crear IdFecha en todos_fecha
     */
    private function obtenerIdFecha($fecha)
    {
        $fechaObj = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->where('Fecha', $fecha)
            ->first();
        
        if ($fechaObj) {
            return $fechaObj->IdFecha;
        }
        
        return DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_fecha')
            ->insertGetId([
                'Fecha' => $fecha,
                'ActivoInactivo' => 1,
                'CierreSucursal' => 0,
                'CierrePermanente' => 0,
            ]);
    }

    /**
     * Limpiar sesión según tipo de venta
     */
    private function limpiarSesionVenta($tipoVenta)
    {
        if ($tipoVenta === 'tactil') {
            session()->forget('venta_tactil_id');
            session()->forget('venta_tactil_lugar_id');
            session()->forget('venta_tactil_comisionista_id');
            session()->forget('venta_tactil_comisionista_identificador');
        } else {
            session()->forget('venta_actual_id');
        }
    }

    /**
     * Generar PDF de factura (sin facturación electrónica)
     */

    public function facturaPdf($id)
    {
        \Log::info('=== facturaPdf completo ===');
        \Log::info('ID: ' . $id);
        
        try {
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $id)
                ->where('IdCliente', session('cliente_id'))
                ->where('IdClienteSucursal', session('cliente_sucursal_id'))
                ->first();
            
            if (!$venta) {
                abort(404, 'Venta no encontrada');
            }
            
            // Datos de la empresa y sucursal
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', session('cliente_id'))
                ->first();
            
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', session('cliente_sucursal_id'))
                ->first();
            
            // 🔥 CLIENTE (quien compró) - CORREGIDO
            $cliente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('IdIdentificador', $venta->IdNIT)
                ->first();
            
            // Si no hay cliente (IdNIT = 0 o nulo), mostrar CONSUMIDOR FINAL
            $nombreCliente = $cliente ? $cliente->Nombre : 'CONSUMIDOR FINAL';
            $nitCliente = $cliente ? $cliente->CI_NIT : '0';
            
            $detalles = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_detalle as d')
                ->join('inventario_relacion_ventainventario as p', 'd.idrelacionventainventario', '=', 'p.IdDetalleProducto')
                ->where('d.idventas', $id)
                ->select('p.Detalle as nombre', 'p.NombreCortoFactura as nombre_corto', 'd.unidades', 'd.preciounidades', 'd.totalbolivianos')
                ->get();
            
            $pagos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion as l')
                ->join('impuestos_ventas_liquidacion_concepto as c', 'l.IdCuenta', '=', 'c.IdCuenta')
                ->where('l.IdVentas', $id)
                ->select('c.Concepto', 'l.Bolivianos')
                ->get();
            
            $comisionista = null;
            if ($venta->IdComisionista) {
                $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_comisionitas as c')
                    ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                    ->where('c.IdComisionista', $venta->IdComisionista)
                    ->first();
            }
            
            // 🔥 OPERADOR (vendedor) - se muestra aparte
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $venta->IdOperadorIngresa)
                ->first();
            
            $lugarVenta = null;
            if ($venta->LugarVenta) {
                $lugar = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_lugar_venta')
                    ->where('IdLugar', $venta->LugarVenta)
                    ->first();
                $lugarVenta = $lugar ? $lugar->Lugar : null;
            }
            
            // =============================================
            // CALCULAR ALTURA TOTAL DEL CONTENIDO
            // =============================================
            $lineHeight = 4;
            $yPosition = 0;
            
            // Cabecera (aprox 60mm)
            $yPosition += 35;
            
            // Encabezados de tabla
            $yPosition += 5;
            
            // Detalle de productos
            $productLines = $detalles->count() * $lineHeight;
            $yPosition += $productLines;
            
            // Línea separadora y total
            $yPosition += 8;
            
            // Métodos de pago
            $pagosLines = $pagos->count() * $lineHeight;
            $yPosition += $pagosLines;
            
            // Total pagos
            $yPosition += 8;
            
            // Literal (texto largo)
            $literal = $this->convertirNumeroALetras(round($venta->ImporteVenta, 2));
            $literalLines = ceil(strlen($literal) / 45);
            $yPosition += $literalLines * $lineHeight;
            
            // Comisionista
            if ($comisionista) $yPosition += 8;
            
            // Operador y ticket
            $yPosition += 10;
            
            // Margen extra
            $yPosition += 10;
            
            // Altura final (mínimo 80mm, máximo 500mm)
            $pageHeight = max(80, min(500, $yPosition + 20));
            
            // =============================================
            // GENERAR PDF
            // =============================================
            $pdf = new \TCPDF('P', 'mm', array(72, $pageHeight));
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(4, 4, 4);
            $pdf->SetAutoPageBreak(true, 5);
            $pdf->AddPage();
            
            // 🔥 SELLO DE "ANULADO" si la factura está anulada (IdEstado = 2)
            if ($venta->IdEstado == 2) {
                $pdf->SetAlpha(0.3);
                $pdf->SetFont('helvetica', 'B', 38);
                $pdf->SetTextColor(255, 0, 0);
                
                $anchoPagina = 72;
                $anchoTexto = 55;
                $xCentro = ($anchoPagina - $anchoTexto) / 2;
                $yCentro = 45;
                
                $pdf->StartTransform();
                $pdf->Rotate(-25, $anchoPagina / 2, $yCentro);
                $pdf->SetXY($xCentro, $yCentro);
                $pdf->Cell($anchoTexto, 15, "ANULADO", 0, 1, 'C');
                $pdf->StopTransform();
                
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetAlpha(1);
            }
            
            $pdf->SetFont('helvetica', '', 8);
            
            $x = 4;
            $y = 4;
            $width = 64; // 72 - 4 - 4 = 64mm de ancho útil
            
            // =============================================
            // CABECERA
            // =============================================
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 5, $empresa->Nombre ?? '', 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "SUCURSAL " . ($sucursal->NumeroSucursal ?? ''), 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, $sucursal->Direccion ?? '', 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "Tel.: " . ($sucursal->Telefono ?? '') . " - Cel.: " . ($sucursal->Celular ?? ''), 0, 1, 'C');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "SANTA CRUZ - BOLIVIA", 0, 1, 'C');
            $y += 6;
            
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 5, "RECIBO", 0, 1, 'C');
            $y += 5;
            
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "N° " . ($venta->NumeroFactura ?? '0'), 0, 1, 'C');
            $y += 6;
            
            // =============================================
            // DATOS DEL CLIENTE (quien compró) - CORREGIDO
            // =============================================
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "FECHA: " . date('d/m/Y H:i:s', strtotime($venta->FechaVenta)), 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "NIT/CI: " . $nitCliente, 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "CLIENTE: " . $nombreCliente, 0, 1, 'L');
            $y += 4;
            
            if ($lugarVenta) {
                $pdf->SetXY($x, $y);
                $pdf->Cell($width, 4, "SERVICIO EN: " . $lugarVenta, 0, 1, 'L');
                $y += 4;
            }
            
            $y += 2;
            
            // =============================================
            // TABLA DE PRODUCTOS
            // =============================================
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($x, $y);
            $pdf->Cell(8, 4, "CANT", 0, 0, 'L');
            $pdf->Cell(35, 4, "PRODUCTO", 0, 0, 'L');
            $pdf->Cell(10, 4, "P.U.", 0, 0, 'R');
            $pdf->Cell(11, 4, "TOTAL", 0, 1, 'R');
            
            $pdf->SetFont('helvetica', '', 7);
            $pdf->SetXY($x, $y + 1);
            $pdf->Cell($width, 1, "", 'T', 1);
            $y += 4;
            
            $totalGeneral = 0;
            foreach ($detalles as $detalle) {
                $nombreCorto = $detalle->nombre_corto ?? $detalle->nombre ?? 'Producto';
                $nombreCorto = substr($nombreCorto, 0, 25);
                
                $cantidad = $detalle->unidades;
                $precio = $detalle->preciounidades;
                $subtotal = $detalle->totalbolivianos;
                $totalGeneral += $subtotal;
                
                $pdf->SetXY($x, $y);
                $pdf->Cell(8, 4, number_format($cantidad, 0), 0, 0, 'L');
                $pdf->Cell(35, 4, $nombreCorto, 0, 0, 'L');
                $pdf->Cell(10, 4, number_format($precio, 2, '.', ','), 0, 0, 'R');
                $pdf->Cell(11, 4, number_format($subtotal, 2, '.', ','), 0, 1, 'R');
                $y += 4;
            }
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 1, "", 'T', 1);
            $y += 3;
            
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($x, $y);
            $pdf->Cell(53, 5, "TOTAL:", 0, 0, 'R');
            $pdf->Cell(11, 5, number_format($totalGeneral, 2, '.', ','), 0, 1, 'R');
            $y += 6;
            
            // =============================================
            // MÉTODOS DE PAGO
            // =============================================
            $totalPagos = 0;
            foreach ($pagos as $pago) {
                $pdf->SetXY($x, $y);
                $pdf->Cell(53, 4, $pago->Concepto, 0, 0, 'R');
                $pdf->Cell(11, 4, number_format($pago->Bolivianos, 2, '.', ','), 0, 1, 'R');
                $totalPagos += $pago->Bolivianos;
                $y += 4;
            }
            
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($x, $y);
            $pdf->Cell(53, 5, "TOTAL PAGO:", 0, 0, 'R');
            $pdf->Cell(11, 5, number_format($totalPagos, 2, '.', ','), 0, 1, 'R');
            $y += 6;
            
            // =============================================
            // LITERAL
            // =============================================
            $pdf->SetFont('helvetica', '', 6);
            $literal = $this->convertirNumeroALetras(round($totalGeneral, 2));
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($width, 3, "SON: " . $literal, 0, 'L');
            $y = $pdf->GetY() + 2;
            
            // =============================================
            // COMISIONISTA Y OPERADOR (VENDEDOR)
            // =============================================
            if ($comisionista) {
                $pdf->SetXY($x, $y);
                $pdf->Cell($width, 4, "COMISIONISTA: " . ($comisionista->Nombre ?? ''), 0, 1, 'L');
                $y += 4;
            }
            
            // 🔥 OPERADOR (vendedor) - se muestra aquí, no como cliente
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "VENDEDOR: " . ($operador->Nombre ?? ''), 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "TICKET: " . ($venta->TicketDia ?? '0'), 0, 1, 'L');
            $y += 4;
            
            // =============================================
            // LÍNEA FINAL Y OUTPUT
            // =============================================
            $pdf->SetXY($x, $y + 2);
            $pdf->Cell($width, 1, "________________________________________", 0, 1, 'C');
            
            $pdf->Output("factura_{$venta->NumeroFactura}.pdf", 'I');
            exit;
            
        } catch (\Exception $e) {
            \Log::error('Error generando PDF factura: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            abort(500, 'Error: ' . $e->getMessage());
        }
    }
    /**
     * Procesar el pago para venta con facturación (normal y táctil)
     */
    public function store(Request $request)
    {
        $request->validate([
            'venta_id' => 'required|exists:impuestos_ventas,IdVentas',
            'nit' => 'required|string',
            'nombre' => 'required|string',
            'codigo_metodo_pago' => 'required|integer',
            'montos' => 'required|array',
            'monto_total' => 'required|numeric',
        ]);
        
        try {
            // Aquí va la lógica de pago con facturación electrónica
            // (se mantiene igual)
            
            session()->forget('venta_actual_id');
            return redirect()->route('oficial.index')->with('success', '✅ Venta procesada exitosamente');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Convertir número a letras
     */
    private function convertirNumeroALetras($numero)
    {
        $partes = explode('.', number_format($numero, 2, '.', ''));
        $entero = (int)$partes[0];
        $decimal = isset($partes[1]) ? (int)$partes[1] : 0;
        
        $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $decenasEspeciales = ['ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
        
        $literal = number_format($numero, 2) . " (" . $entero . " BOLIVIANOS CON " . str_pad($decimal, 2, '0', STR_PAD_LEFT) . " CENTAVOS)";
        
        return $literal;
    }
}