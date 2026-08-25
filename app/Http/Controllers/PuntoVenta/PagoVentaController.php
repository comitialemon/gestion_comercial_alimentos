<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Contabilidad\MetodoPagoMapeo;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use App\Services\Impuestos\VentaService;
use App\Services\TimezoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\Gestion\Impuestos\VentaLiquidacionConcepto;

class PagoVentaController extends Controller
{
    protected $ventaService;
    protected $timezoneService;

    public function __construct(VentaService $ventaService, TimezoneService $timezoneService)
    {
        $this->ventaService = $ventaService;
        $this->timezoneService = $timezoneService;
    }

    /**
     * 🔥 OBTENER FECHA Y HORA CORRECTA DEL CLIENTE
     */
    private function getFechaHoraCliente()
    {
        return $this->timezoneService->getFechaHoraActual();
    }

    /**
     * 🔥 GENERAR TICKET DEL DÍA (se ejecuta al finalizar el pago)
     * Reinicia en 1 cada día por sucursal
     */
    private function generarTicketDia($clienteId, $sucursalId, $fechaVenta)
    {
        // 🔥 Usar la fecha correcta del cliente
        $fechaStr = $this->getFechaCliente();
        
        $maxTicket = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->whereDate('FechaVenta', $fechaStr)
            ->where('ActivoInactivo', 1)
            ->max('TicketDia');
        
        $nuevoTicket = ($maxTicket ?? 0) + 1;
        
        Log::info('🎫 TicketDia generado al finalizar pago', [
            'cliente' => $clienteId,
            'sucursal' => $sucursalId,
            'fecha' => $fechaStr,
            'maximo_anterior' => $maxTicket ?? 0,
            'nuevo_ticket' => $nuevoTicket
        ]);
        
        return $nuevoTicket;
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
            'clienteNit' => $nitEmpresa,
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
        
        $nitEmpresa = session('cliente_nit');
        
        return $this->renderPagoView($tieneFacturacion, [
            'venta' => (object) $venta,
            'deuda' => (float) $deuda,
            'productos' => $productos,
            'ventaId' => $ventaId,
            'tipoVenta' => 'tactil',
            'volverRuta' => '/venta-tactil/carrito',
            'clienteNit' => $nitEmpresa,
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
                Log::error('Error obteniendo métodos de pago: ' . $e->getMessage());
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
            Log::error('Error verificando NIT: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Procesar pago SIN facturación (completo con inventario)
     * 🔥 SE GENERA EL TICKETDIA AQUÍ AL FINALIZAR EL PAGO
     * 🔥 CORREGIDO: Usa zona horaria del cliente
     */
    public function procesarPagoSinFacturacion(Request $request)
    {
        try {
            $request->validate([
                'venta_id' => 'required|exists:impuestos_ventas,IdVentas',
                'montos' => 'required|array',
                'tipo_venta' => 'required|string|in:normal,tactil',
                'id_identificador_cliente' => 'required|exists:todos_identificador,IdIdentificador',
                'identificadores_por_concepto' => 'nullable|array',
                'observacion' => 'nullable|string|max:200',
            ]);

            DB::beginTransaction();

            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $ventaId = $request->venta_id;
            $idIdentificadorClienteComprador = $request->id_identificador_cliente;
            $identificadoresPorConcepto = $request->identificadores_por_concepto ?? [];
            
            // 🔥 OBTENER FECHA Y HORA CORRECTA DEL CLIENTE
            $fechaHoraCliente = $this->getFechaHoraCliente();
            $fechaCliente = $this->getFechaCliente();
            
            // Obtener la venta
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->first();
            
            if (!$venta) {
                throw new \Exception('Venta no encontrada');
            }
            
            // 🔥 CORREGIR FECHA DE VENTA SI ES NECESARIO
            $fechaVentaActual = $venta->FechaVenta;
            $fechaVentaDate = date('Y-m-d', strtotime($fechaVentaActual));
            $fechaHoy = $fechaCliente;
            
            $fechaVentaFinal = $fechaVentaActual;
            if ($fechaVentaDate !== $fechaHoy) {
                $horaOriginal = date('H:i:s', strtotime($fechaVentaActual));
                $fechaVentaFinal = $fechaHoy . ' ' . $horaOriginal;
                
                Log::info('📅 Fecha de venta corregida', [
                    'venta_id' => $ventaId,
                    'fecha_original' => $fechaVentaActual,
                    'fecha_corregida' => $fechaVentaFinal
                ]);
            }
            
            // Obtener conceptos
            $conceptos = VentaLiquidacionConcepto::porContexto()
                ->activos()
                ->get()
                ->keyBy('IdConceptoLiquidacion');
            
            Log::info('=== PROCESANDO PAGO ===');
            Log::info('Venta ID: ' . $ventaId);
            Log::info('Fecha cliente: ' . $fechaHoraCliente);
            
            // =============================================
            // 1. INSERTAR LIQUIDACIONES
            // =============================================
            foreach ($request->montos as $conceptoId => $monto) {
                $monto = floatval($monto);
                if ($monto <= 0) continue;
                
                $concepto = $conceptos->get($conceptoId);
                if (!$concepto) {
                    Log::warning('Concepto no encontrado: ' . $conceptoId);
                    continue;
                }
                
                $idIdentificadorUsar = null;
                
                if ($concepto->requiere_identificador) {
                    $idIdentificadorUsar = $identificadoresPorConcepto[$conceptoId] ?? null;
                    if (!$idIdentificadorUsar) {
                        throw new \Exception("El concepto '{$concepto->Concepto}' requiere seleccionar un cliente");
                    }
                } 
                elseif ($concepto->usa_identificador_factura) {
                    $idIdentificadorUsar = $idIdentificadorClienteComprador;
                }
                else {
                    $idIdentificadorUsar = $this->getIdIdentificadorOperador();
                }
                
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion')
                    ->insert([
                        'IdVentas' => $ventaId,
                        'IdDiario' => 0,
                        'IdIdentificador' => $idIdentificadorUsar,
                        'IdCuenta' => $concepto->IdCuenta,
                        'Bolivianos' => $monto,
                    ]);
            }

            // =============================================
            // 2. GENERAR TICKET DIA
            // =============================================
            $ticketDia = $this->generarTicketDia($clienteId, $sucursalId, $fechaCliente);

            // =============================================
            // 3. ACTUALIZAR VENTA
            // =============================================
            $ultimoNumeroFactura = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $sucursalId)
                ->max('NumeroFactura');

            $nuevoNumeroFactura = ($ultimoNumeroFactura ?? 0) + 1;
            $operadorId = session('operador_id');

            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update([
                    'ActivoInactivo' => 1,
                    'FechaUltimaActualizcion' => $fechaHoraCliente,  // ✅ FECHA CORRECTA
                    'FechaVenta' => $fechaVentaFinal,                // ✅ FECHA CORREGIDA
                    'NumeroFactura' => $nuevoNumeroFactura,
                    'IdEstado' => 1,
                    'IdOperadorActualiza' => $operadorId,
                    'IdNIT' => $idIdentificadorClienteComprador,
                    'TicketDia' => $ticketDia,
                    'Observacion' => $request->observacion ?? '',
                ]);

            // Registrar salida de inventario con fecha correcta
            $this->registrarSalidaInventario($ventaId, $fechaCliente);

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
                'ticket_dia' => $ticketDia,
                'pdf_url' => route('ventas.factura-pdf', $ventaId),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error procesando pago: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener el ID del identificador del operador actual
     */
    private function getIdIdentificadorOperador()
    {
        $operadorId = session('operador_id');
        
        $identificador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->where('IdOperador', $operadorId)
            ->value('IdIdentificador');
        
        return $identificador ?? 1;
    }

    /**
     * Registrar salida de inventario
     * 🔥 CORREGIDO: Maneja TODOS los casos sin usar procesarComboSimple
     */
    /**
     * Registrar salida de inventario
     * 🔥 CORREGIDO: Misma lógica que el reprocesamiento
     */
    private function registrarSalidaInventario($ventaId, $fechaCorregida = null)
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
        
        // OBTENER NOMBRE DEL OPERADOR
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $venta->IdOperadorIngresa)
            ->first();
        $nombreOperador = $operador ? $operador->Nombre : 'Desconocido';
        
        // USAR LA FECHA CORREGIDA O LA FECHA ACTUAL DEL CLIENTE
        if ($fechaCorregida) {
            $fechaVenta = $fechaCorregida;
        } else {
            $fechaVenta = $this->getFechaCliente();
        }
        
        Log::info('📅 Fecha para inventario de venta ' . $ventaId . ': ' . $fechaVenta);
        
        $idFecha = $this->obtenerIdFecha($fechaVenta);
        
        // Obtener almacén
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
        
        // OBTENER EL ID DE "Ventas"
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
            $idTipoOperacion = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_tipooperacion')
                ->where('IdCliente', $clienteId)
                ->where('Detalle', 'LIKE', '%Ventas%')
                ->where('ActivoInactivo', 0)
                ->value('IdTipoOperacion');
        }

        if (!$idTipoOperacion) {
            Log::error('❌ No se encontró tipo de operación VENTAS para el cliente ' . $clienteId);
            throw new \Exception('No se encontró el tipo de operación "Ventas" para este cliente');
        }
        
        // Obtener detalles de la venta
        $detalles = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas_detalle')
            ->where('idventas', $ventaId)
            ->get();
        
        // CANASTA ACUMULADORA
        $productosADescontar = [];
        
        foreach ($detalles as $detalle) {
            $unidadesDetalle = (float) $detalle->unidades;
            
            Log::info('📦 Procesando detalle:', [
                'id_producto' => $detalle->idrelacionventainventario,
                'unidades' => $unidadesDetalle,
                'personalizacion' => $detalle->personalizacion
            ]);
            
            // 🔥 OBTENER LA COMPOSICIÓN ORIGINAL
            $composicionOriginal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_relacion_ventainventario_detalle')
                ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                ->get()
                ->keyBy('IdProducto');
            
            // 🔥 CASO 1: PRODUCTO NORMAL (sin composición)
            if ($composicionOriginal->isEmpty()) {
                Log::info('📦 Producto normal (sin composición), descontando directamente');
                $idProducto = $detalle->idrelacionventainventario;
                $cantidad = $unidadesDetalle;
                
                if (!isset($productosADescontar[$idProducto])) {
                    $productosADescontar[$idProducto] = 0;
                }
                $productosADescontar[$idProducto] += $cantidad;
                continue;
            }
            
            // 🔥 DECODIFICAR PERSONALIZACIÓN
            $personalizacion = null;
            if ($detalle->personalizacion && $detalle->personalizacion != 'null' && $detalle->personalizacion != '[]') {
                $personalizacion = json_decode($detalle->personalizacion, true);
            }
            
            // 🔥 CASO 2: CON PERSONALIZACIÓN
            if ($personalizacion && is_array($personalizacion) && count($personalizacion) > 0) {
                Log::info('✅ Detalle con personalización');
                
                foreach ($personalizacion as $comboData) {
                    $sustitutos = $comboData['sustitutos'] ?? [];
                    
                    // 🔥 PASO 2a: PROCESAR ORIGINALES NO REEMPLAZADOS
                    foreach ($composicionOriginal as $idProductoOriginal => $comp) {
                        $cantidadPorPack = (float) $comp->Porcion;
                        
                        // Calcular cuántos se reemplazan
                        $totalReemplazado = 0;
                        foreach ($sustitutos as $sust) {
                            if ($sust['id_producto_original'] == $idProductoOriginal) {
                                $totalReemplazado += (float) ($sust['cantidad'] ?? 0);
                            }
                        }
                        
                        // Calcular cuántos quedan originales (NO reemplazados)
                        $quedanOriginales = $cantidadPorPack - $totalReemplazado;
                        
                        // 🔥 NO multiplicar por unidadesDetalle (ya hay un elemento por cada pack)
                        if ($quedanOriginales > 0) {
                            if (!isset($productosADescontar[$idProductoOriginal])) {
                                $productosADescontar[$idProductoOriginal] = 0;
                            }
                            $productosADescontar[$idProductoOriginal] += $quedanOriginales;
                            Log::info("  → Original {$idProductoOriginal}: +{$quedanOriginales}");
                        }
                    }
                    
                    // 🔥 PASO 2b: PROCESAR SUSTITUTOS (los nuevos productos)
                    foreach ($sustitutos as $sust) {
                        $idSustituto = $sust['id_producto_sustituto'];
                        // 🔥 NO multiplicar por unidadesDetalle (ya hay un elemento por cada pack)
                        $cantidadSustituto = (float) ($sust['cantidad'] ?? 0);
                        
                        if ($cantidadSustituto > 0) {
                            if (!isset($productosADescontar[$idSustituto])) {
                                $productosADescontar[$idSustituto] = 0;
                            }
                            $productosADescontar[$idSustituto] += $cantidadSustituto;
                            Log::info("  → Sustituto {$idSustituto}: +{$cantidadSustituto}");
                        }
                    }
                }
            } else {
                // 🔥 CASO 3: SIN PERSONALIZACIÓN - usar composición completa
                Log::info('📦 Producto sin personalización, usando composición original');
                
                foreach ($composicionOriginal as $comp) {
                    $idProducto = $comp->IdProducto;
                    // 🔥 MULTIPLICAR POR UNIDADES DE VENTA
                    $cantidad = (float) $comp->Porcion * $unidadesDetalle;
                    
                    if (!isset($productosADescontar[$idProducto])) {
                        $productosADescontar[$idProducto] = 0;
                    }
                    $productosADescontar[$idProducto] += $cantidad;
                    Log::info("  → Producto compuesto {$idProducto}: {$comp->Porcion} x {$unidadesDetalle} = {$cantidad}");
                }
            }
        }
        
        // REGISTRAR TODOS LOS PRODUCTOS ACUMULADOS
        Log::info('📦 Total de productos a descontar:', $productosADescontar);
        
        foreach ($productosADescontar as $idProducto => $cantidadTotal) {
            if ($cantidadTotal <= 0) continue;
            
            $nombreProducto = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->where('IdProducto', $idProducto)
                ->value('Descripcion');
            
            $precioCosto = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle_precio_costo')
                ->where('IdProducto', $idProducto)
                ->orderBy('IdPrecioCosto', 'DESC')
                ->value('PrecioCosto');
            
            $costoTotal = $cantidadTotal * ($precioCosto ?? 0);
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->insert([
                    'IdTipoDeOperacion' => $idTipoOperacion,
                    'IdDocumento' => $ventaId,
                    'IdFecha' => $idFecha,
                    'IdAlmacen' => $idAlmacen,
                    'IdProducto' => $idProducto,
                    'Glosa' => "Venta Factura No {$venta->NumeroFactura}; Op.{$nombreOperador}",
                    'D_H' => 'H',
                    'Unidades' => $cantidadTotal,
                    'Bolivianos' => $costoTotal,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                ]);
            
            Log::info('✅ Descontado producto del inventario', [
                'id_producto' => $idProducto,
                'nombre' => $nombreProducto ?? 'Desconocido',
                'unidades' => $cantidadTotal,
                'costo_total' => $costoTotal,
            ]);
        }
        
        Log::info('✅ Inventario actualizado para venta ' . $ventaId, [
            'total_productos_descontados' => count($productosADescontar),
            'fecha' => $fechaVenta,
            'operador' => $nombreOperador
        ]);
    }
/*
    /**
     * Procesar combo sin personalización (descuenta todo)
     * 🔥 MÉTODO AUXILIAR
     * 🔥 CORREGIDO: Agrega operador en la glosa
     
    private function procesarComboSimple($detalle, $ventaId, $idFecha, $idAlmacen, $idTipoOperacion, $venta, $clienteId, $sucursalId, $nombreOperador)
    {
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
            
            // 🔥 GLOSA CON OPERADOR
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_propiamente')
                ->insert([
                    'IdTipoDeOperacion' => $idTipoOperacion,
                    'IdDocumento' => $ventaId,
                    'IdFecha' => $idFecha,
                    'IdAlmacen' => $idAlmacen,
                    'IdProducto' => $porcion->IdProducto,
                    'Glosa' => "Venta Factura No {$venta->NumeroFactura}; Op.{$nombreOperador}",
                    'D_H' => 'H',
                    'Unidades' => $cantidad,
                    'Bolivianos' => $costoTotal,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                ]);
        }
    }
*/
    /**
     * Obtener o crear IdFecha
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
     * 🔥 OBTENER FECHA ACTUAL DEL CLIENTE
     */
    private function getFechaCliente()
    {
        return $this->timezoneService->getFechaActual();
    }
    /**
     * Generar PDF de factura con altura DINÁMICA y DETALLE DE PERSONALIZACIÓN
     */
    public function facturaPdf($id)
    {
        Log::info('=== facturaPdf (altura dinámica) ===');
        Log::info('ID: ' . $id);
        
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
            
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', session('cliente_id'))
                ->first();
            
            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', session('cliente_sucursal_id'))
                ->first();
            
            $cliente = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_identificador')
                ->where('IdIdentificador', $venta->IdNIT)
                ->first();
            
            $nombreCliente = $cliente ? $cliente->Nombre : 'CONSUMIDOR FINAL';
            $nitCliente = $cliente ? $cliente->CI_NIT : '0';
            
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
            
            // 🔥 PROCESAR DETALLES CON PERSONALIZACIÓN
            $detallesProcesados = [];
            $totalGeneral = 0;
            
            foreach ($detalles as $detalle) {
                $productoNombre = $detalle->producto_nombre ?? 'Producto';
                $unidades = (float) $detalle->unidades;
                $precio = (float) $detalle->preciounidades;
                $subtotal = (float) $detalle->totalbolivianos;
                $totalGeneral += $subtotal;
                
                $productoData = [
                    'nombre' => $productoNombre,
                    'unidades' => $unidades,
                    'precio' => $precio,
                    'subtotal' => $subtotal,
                    'tiene_personalizacion' => false,
                    'productos_detalle' => []
                ];
                
                // 🔥 DECODIFICAR PERSONALIZACIÓN
                if ($detalle->personalizacion && $detalle->personalizacion != 'null' && $detalle->personalizacion != '[]') {
                    $personalizacion = json_decode($detalle->personalizacion, true);
                    
                    if (!empty($personalizacion) && is_array($personalizacion)) {
                        $productoData['tiene_personalizacion'] = true;
                        
                        // 🔥 OBTENER COMPOSICIÓN ORIGINAL
                        $composicionOriginal = DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('inventario_relacion_ventainventario_detalle')
                            ->where('IdDetalleProducto', $detalle->idrelacionventainventario)
                            ->get();
                        
                        // 🔥 MAPA DE PRODUCTOS ORIGINALES
                        $originales = [];
                        foreach ($composicionOriginal as $comp) {
                            $producto = DB::connection('mysql_gestion_comercial_alimentos')
                                ->table('inventario_productodetalle')
                                ->where('IdProducto', $comp->IdProducto)
                                ->first();
                            
                            $originales[$comp->IdProducto] = [
                                'nombre' => $producto->Descripcion ?? 'Producto',
                                'porcion' => (float) $comp->Porcion,
                                'cantidad_total' => (float) $comp->Porcion * $unidades
                            ];
                        }
                        
                        // 🔥 MAPA DE SUSTITUTOS (los que se cambiaron)
                        $sustitutosMap = [];
                        foreach ($personalizacion as $comboIndex => $combo) {
                            if (isset($combo['sustitutos']) && is_array($combo['sustitutos'])) {
                                foreach ($combo['sustitutos'] as $sustituto) {
                                    $idOriginal = $sustituto['id_producto_original'] ?? null;
                                    $idSustituto = $sustituto['id_producto_sustituto'] ?? null;
                                    $cantidad = (float) ($sustituto['cantidad'] ?? 0);
                                    
                                    if ($idOriginal && $idSustituto && $cantidad > 0) {
                                        // Si el sustituto es DIFERENTE al original, se reemplazó
                                        if ($idSustituto != $idOriginal) {
                                            $nombreSustituto = DB::connection('mysql_gestion_comercial_alimentos')
                                                ->table('inventario_productodetalle')
                                                ->where('IdProducto', $idSustituto)
                                                ->value('Descripcion');
                                            
                                            $key = 'sust_' . $idSustituto;
                                            if (!isset($sustitutosMap[$key])) {
                                                $sustitutosMap[$key] = [
                                                    'nombre' => $nombreSustituto ?? 'Producto #' . $idSustituto,
                                                    'cantidad' => 0,
                                                    'es_sustituto' => true,
                                                    'id_original' => $idOriginal,
                                                    'nombre_original' => $originales[$idOriginal]['nombre'] ?? 'Original'
                                                ];
                                            }
                                            $sustitutosMap[$key]['cantidad'] += $cantidad;
                                        }
                                    }
                                }
                            }
                        }
                        
                        // 🔥 CONSTRUIR LISTA DE PRODUCTOS DETALLE (FORMATO COMPACTO)
                        $productosDetalle = [];
                        
                        // RECORRER TODOS LOS PRODUCTOS ORIGINALES
                        foreach ($originales as $idOriginal => $original) {
                            $cantidadSustituto = 0;
                            $nombreSustituto = null;
                            
                            // Buscar si este original fue reemplazado
                            foreach ($sustitutosMap as $sust) {
                                if ($sust['id_original'] == $idOriginal) {
                                    $cantidadSustituto += $sust['cantidad'];
                                    $nombreSustituto = $sust['nombre'];
                                }
                            }
                            
                            // 🔥 CALCULAR CUÁNTOS QUEDARON ORIGINALES
                            $cantidadOriginalQueQueda = $original['cantidad_total'] - $cantidadSustituto;
                            
                            // 🔥 SI QUEDARON ORIGINALES, MOSTRARLOS
                            if ($cantidadOriginalQueQueda > 0) {
                                $productosDetalle[] = [
                                    'nombre' => $original['nombre'],
                                    'cantidad' => $cantidadOriginalQueQueda,
                                    'es_sustituto' => false
                                ];
                            }
                            
                            // 🔥 SI HAY SUSTITUTOS, MOSTRARLOS
                            foreach ($sustitutosMap as $sust) {
                                if ($sust['id_original'] == $idOriginal) {
                                    $productosDetalle[] = [
                                        'nombre' => $sust['nombre'],
                                        'cantidad' => $sust['cantidad'],
                                        'es_sustituto' => true
                                    ];
                                }
                            }
                        }
                        
                        // Si no se encontraron originales, mostrar solo sustitutos
                        if (empty($productosDetalle) && !empty($sustitutosMap)) {
                            foreach ($sustitutosMap as $sust) {
                                $productosDetalle[] = [
                                    'nombre' => $sust['nombre'],
                                    'cantidad' => $sust['cantidad'],
                                    'es_sustituto' => true
                                ];
                            }
                        }
                        
                        $productoData['productos_detalle'] = $productosDetalle;
                    }
                }
                
                $detallesProcesados[] = $productoData;
            }
            
            // =============================================
            // CALCULAR ALTURA DINÁMICA
            // =============================================
            
            $x = 4;
            $y = 4;
            $width = 64;
            
            $pdfCalc = new \TCPDF('P', 'mm', array(72, 300));
            $pdfCalc->setPrintHeader(false);
            $pdfCalc->setPrintFooter(false);
            $pdfCalc->SetMargins(4, 4, 4);
            $pdfCalc->SetAutoPageBreak(false);
            $pdfCalc->AddPage();
            
            $pdfCalc->SetFont('helvetica', '', 8);
            
            $alturaTotal = 0;
            
            // CABECERA
            $alturaTotal += 5 + 4 + 4 + 4 + 4 + 6 + 5 + 4 + 6 + 4 + 4 + 4 + 6;
            
            // TABLA DE PRODUCTOS CON DETALLE
            $pdfCalc->SetFont('helvetica', 'B', 7);
            $alturaTotal += 4 + 2;
            
            foreach ($detallesProcesados as $detalle) {
                $nombreProducto = $detalle['nombre'] ?? 'Producto';
                $alturaNombre = $pdfCalc->getStringHeight(35, $nombreProducto);
                $alturaTotal += max(4, $alturaNombre);
                
                if ($detalle['tiene_personalizacion'] && !empty($detalle['productos_detalle'])) {
                    foreach ($detalle['productos_detalle'] as $prodDet) {
                        $textoDetalle = "  - " . $prodDet['nombre'] . " x" . $prodDet['cantidad'];
                        $alturaDetalle = $pdfCalc->getStringHeight(50, $textoDetalle);
                        $alturaTotal += max(3, $alturaDetalle);
                    }
                }
            }
            
            $alturaTotal += 3 + 6;
            
            // PAGOS
            $pagos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas_liquidacion as l')
                ->join('impuestos_ventas_liquidacion_concepto as c', 'l.IdCuenta', '=', 'c.IdCuenta')
                ->leftJoin('todos_identificador as i', 'l.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('l.IdVentas', $id)
                ->select('c.Concepto', 'l.Bolivianos', 'i.CI_NIT', 'i.Nombre as identificador_nombre')
                ->get();
            
            foreach ($pagos as $pago) {
                $alturaTotal += 4;
                if ($pago->CI_NIT && $pago->CI_NIT != 0) {
                    $alturaTotal += 3;
                }
            }
            
            $alturaTotal += 6 + 8;
            
            $comisionista = null;
            if ($venta->IdComisionista) {
                $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_comisionitas as c')
                    ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                    ->where('c.IdComisionista', $venta->IdComisionista)
                    ->first();
                if ($comisionista) $alturaTotal += 4;
            }
            
            $alturaTotal += 4 + 4 + 6 + 4 + 10;
            
            $alturaMinima = 80;
            $alturaFinal = max($alturaMinima, min(500, $alturaTotal));
            
            Log::info('Altura calculada: ' . $alturaFinal . ' mm');
            
            // =============================================
            // GENERAR PDF REAL
            // =============================================
            
            $pdf = new \TCPDF('P', 'mm', array(72, $alturaFinal));
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(4, 4, 4);
            $pdf->SetAutoPageBreak(true, 5);
            $pdf->AddPage();
            
            // Marca de agua si está anulado
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
            $width = 64;
            
            // CABECERA
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
            
            // DATOS DEL CLIENTE
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "FECHA: " . date('d/m/Y H:i:s', strtotime($venta->FechaVenta)), 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "NIT/CI: " . $nitCliente, 0, 1, 'L');
            $y += 4;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "CLIENTE: " . $nombreCliente, 0, 1, 'L');
            $y += 4;
            
            if ($venta->LugarVenta) {
                $lugar = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_lugar_venta')
                    ->where('IdLugar', $venta->LugarVenta)
                    ->first();
                if ($lugar) {
                    $pdf->SetXY($x, $y);
                    $pdf->Cell($width, 4, "SERVICIO EN: " . $lugar->Lugar, 0, 1, 'L');
                    $y += 4;
                }
            }
            
            $y += 1;
            
            // TABLA DE PRODUCTOS CON DETALLE DE PERSONALIZACIÓN
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
            foreach ($detallesProcesados as $detalle) {
                $nombreProducto = $detalle['nombre'] ?? 'Producto';
                $cantidad = $detalle['unidades'] ?? 0;
                $precio = $detalle['precio'] ?? 0;
                $subtotal = $detalle['subtotal'] ?? 0;
                $totalGeneral += $subtotal;
                
                $startY = $y;
                
                $pdf->SetXY($x, $startY);
                $pdf->Cell(8, 4, number_format($cantidad, 0), 0, 0, 'L');
                
                $pdf->SetXY($x + 43, $startY);
                $pdf->Cell(10, 4, number_format($precio, 2, '.', ','), 0, 0, 'R');
                
                $pdf->SetXY($x + 53, $startY);
                $pdf->Cell(11, 4, number_format($subtotal, 2, '.', ','), 0, 1, 'R');
                
                $pdf->SetXY($x + 8, $startY);
                $pdf->MultiCell(35, 4, $nombreProducto, 0, 'L');
                
                $y = $pdf->GetY();
                
                // 🔥 DETALLE DE PERSONALIZACIÓN (FORMATO COMPACTO)
                if ($detalle['tiene_personalizacion'] && !empty($detalle['productos_detalle'])) {
                    $pdf->SetFont('helvetica', '', 6);
                    $pdf->SetTextColor(80, 80, 80);
                    
                    foreach ($detalle['productos_detalle'] as $prodDet) {
                        $textoDetalle = $prodDet['nombre'] . " x" . $prodDet['cantidad'];
                        $pdf->SetXY($x , $y);
                        $pdf->MultiCell(50, 3, $textoDetalle, 0, 'L');
                        $y = $pdf->GetY();
                    }
                    
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 7);
                }
            }
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 1, "", 'T', 1);
            $y += 1;
            
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($x, $y);
            $pdf->Cell(53, 5, "TOTAL:", 0, 0, 'R');
            $pdf->Cell(11, 5, number_format($totalGeneral, 2, '.', ','), 0, 1, 'R');
            $y += 6;
            
            // MÉTODOS DE PAGO
            $totalPagos = 0;
            foreach ($pagos as $pago) {
                $pdf->SetFont('helvetica', 'B', 7);
                $pdf->SetXY($x, $y);
                $pdf->Cell(53, 4, $pago->Concepto, 0, 0, 'R');
                $pdf->Cell(11, 4, number_format($pago->Bolivianos, 2, '.', ','), 0, 1, 'R');
                $totalPagos += $pago->Bolivianos;
                $y += 4;
                
                if ($pago->CI_NIT && $pago->CI_NIT != 0) {
                    $pdf->SetFont('helvetica', '', 6);
                    $pdf->SetXY($x + 5, $y);
                    $pdf->Cell(59, 3, "NIT/CI: " . $pago->CI_NIT . " - " . ($pago->identificador_nombre ?? 'SIN NOMBRE'), 0, 1, 'L');
                    $y += 3;
                }
            }
            
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->SetXY($x, $y);
            $pdf->Cell(53, 5, "TOTAL PAGO:", 0, 0, 'R');
            $pdf->Cell(11, 5, number_format($totalPagos, 2, '.', ','), 0, 1, 'R');
            $y += 6;
            
            // LITERAL
            $pdf->SetFont('helvetica', '', 6);
            $literal = $this->convertirNumeroALetras(round($totalGeneral, 2));
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($width, 3, "SON: " . $literal, 0, 'L');
            $y = $pdf->GetY() + 2;
            
            // COMISIONISTA
            if ($comisionista) {
                $pdf->SetXY($x, $y);
                $pdf->Cell($width, 4, "COMISIONISTA: " . ($comisionista->Nombre ?? ''), 0, 1, 'L');
                $y += 4;
            }
            
            // OPERADOR
            $operador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $venta->IdOperadorIngresa)
                ->first();
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "VENDEDOR: " . ($operador->Nombre ?? ''), 0, 1, 'L');
            $y += 4;
            
            // TICKET DIA
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 4, "TICKET: " . ($venta->TicketDia ?? '0'), 0, 1, 'L');
            $y += 3;
            
            $pdf->SetXY($x, $y);
            $pdf->Cell($width, 1, "________________________________________", 0, 1, 'C');
            
            $pdf->Output("factura_{$venta->NumeroFactura}.pdf", 'I');
            exit;
            
        } catch (\Exception $e) {
            Log::error('Error generando PDF factura: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            abort(500, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Procesar el pago para venta con facturación
     * 🔥 TAMBIÉN SE GENERA EL TICKETDIA AQUÍ
     * 🔥 CORREGIDO: Usa zona horaria del cliente
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
            'observacion' => 'nullable|string|max:200',
        ]);
        
        try {
            $clienteId = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $ventaId = $request->venta_id;
            
            // 🔥 OBTENER FECHA Y HORA CORRECTA DEL CLIENTE
            $fechaHoraCliente = $this->getFechaHoraCliente();
            $fechaCliente = $this->getFechaCliente();
            
            // Obtener la venta para tener la fecha
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->first();
            
            if (!$venta) {
                throw new \Exception('Venta no encontrada');
            }
            
            // 🔥 CORREGIR FECHA DE VENTA SI ES NECESARIO
            $fechaVentaActual = $venta->FechaVenta;
            $fechaVentaDate = date('Y-m-d', strtotime($fechaVentaActual));
            $fechaHoy = $fechaCliente;
            
            $fechaVentaFinal = $fechaVentaActual;
            if ($fechaVentaDate !== $fechaHoy) {
                $horaOriginal = date('H:i:s', strtotime($fechaVentaActual));
                $fechaVentaFinal = $fechaHoy . ' ' . $horaOriginal;
                
                Log::info('📅 Fecha de venta corregida en store()', [
                    'venta_id' => $ventaId,
                    'fecha_original' => $fechaVentaActual,
                    'fecha_corregida' => $fechaVentaFinal
                ]);
            }
            
            // 🔥 Generar TicketDia con fecha correcta
            $ticketDia = $this->generarTicketDia($clienteId, $sucursalId, $fechaCliente);
            
            $operadorId = session('operador_id');
            
            // ✅ Actualizar venta con fechas correctas
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->update([
                    'ActivoInactivo' => 1,
                    'FechaUltimaActualizcion' => $fechaHoraCliente,  // ✅ FECHA CORRECTA
                    'FechaVenta' => $fechaVentaFinal,                // ✅ FECHA CORREGIDA
                    'IdEstado' => 1,
                    'IdOperadorActualiza' => $operadorId,
                    'TicketDia' => $ticketDia,
                    'Observacion' => $request->observacion ?? '',
                ]);
            
            // Registrar salida de inventario con fecha correcta
            $this->registrarSalidaInventario($ventaId, $fechaCliente);
            
            // Limpiar sesión
            session()->forget('venta_actual_id');
            
            return redirect()->route('oficial.index')->with('success', '✅ Venta procesada exitosamente. Ticket #' . $ticketDia);
            
        } catch (\Exception $e) {
            Log::error('Error en store: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
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
        
        $literal = number_format($numero, 2) . " (" . $entero . " BOLIVIANOS CON " . str_pad($decimal, 2, '0', STR_PAD_LEFT) . " CENTAVOS)";
        
        return $literal;
    }

    /**
     * Obtener NIT predefinido para una venta (comisionista o cliente)
     */
    public function getNitPredefinido($ventaId)
    {
        try {
            $venta = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdVentas', $ventaId)
                ->first();
            
            if (!$venta) {
                return response()->json(['success' => false, 'message' => 'Venta no encontrada']);
            }
            
            $clienteNit = session('cliente_nit');
            
            $comisionista = null;
            if ($venta->IdComisionista && $venta->IdComisionista > 0) {
                $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_comisionitas as c')
                    ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                    ->where('c.IdComisionista', $venta->IdComisionista)
                    ->first();
            }
            
            if ($comisionista && $clienteNit && $comisionista->CI_NIT == $clienteNit) {
                return response()->json([
                    'success' => true,
                    'nit' => 0,
                    'nombre' => 'SIN NIT',
                    'id_identificador' => null,
                    'mensaje' => 'El comisionista tiene el NIT de la empresa, se usará SIN NIT'
                ]);
            }
            
            if ($comisionista && $comisionista->CI_NIT != 0) {
                return response()->json([
                    'success' => true,
                    'nit' => $comisionista->CI_NIT,
                    'nombre' => $comisionista->Nombre,
                    'id_identificador' => $comisionista->IdIdentificador,
                    'mensaje' => 'NIT del comisionista'
                ]);
            }
            
            if ($venta->IdNIT && $venta->IdNIT > 0) {
                $clienteExistente = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('todos_identificador')
                    ->where('IdIdentificador', $venta->IdNIT)
                    ->first();
                
                if ($clienteExistente && $clienteExistente->CI_NIT != 0) {
                    return response()->json([
                        'success' => true,
                        'nit' => $clienteExistente->CI_NIT,
                        'nombre' => $clienteExistente->Nombre,
                        'id_identificador' => $clienteExistente->IdIdentificador,
                        'mensaje' => 'NIT de la venta'
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'nit' => null,
                'nombre' => null,
                'id_identificador' => null,
                'mensaje' => 'Sin NIT predefinido'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getNitPredefinido: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}