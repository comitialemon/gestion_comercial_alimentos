<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\LiquidacionVendedor;
use App\Models\Gestion\Impuestos\LiquidacionVendedorDetalle;
use App\Models\Gestion\Impuestos\VentaLiquidacionConcepto;
use App\Models\Gestion\Todos\Fecha;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiquidacionVendedorController extends Controller
{
    /**
     * Mostrar selector de fechas pendientes
     */
    public function index()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $fechasPendientes = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->join('todos_fecha', DB::raw('DATE(impuestos_ventas.FechaVenta)'), '=', 'todos_fecha.Fecha')
            ->where('impuestos_ventas.IdCliente', $clienteId)
            ->where('impuestos_ventas.IdClienteSucursal', $sucursalId)
            ->where('impuestos_ventas.IdOperadorIngresa', $operadorId)
            ->where('impuestos_ventas.LiquidadoVendedor', 0)
            ->where('impuestos_ventas.IdEstado', 1)
            ->select('todos_fecha.IdFecha as id', DB::raw("DATE_FORMAT(todos_fecha.Fecha, '%d-%m-%Y') as fecha"))
            ->groupBy('todos_fecha.IdFecha', 'todos_fecha.Fecha')
            ->orderBy('todos_fecha.Fecha', 'desc')
            ->get();

        return Inertia::render('Gestion/Impuestos/LiquidacionVendedor/Control', [
            'fechasPendientes' => $fechasPendientes,
        ]);
    }
    
    /**
     * Obtener datos de liquidación para una fecha específica
     */
    public function getDatos($fechaId)
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');

        $fecha = Fecha::find($fechaId);
        $fechaStr = $fecha ? $fecha->Fecha : null;

        if (!$fechaStr) {
            return response()->json(['error' => 'Fecha no encontrada'], 404);
        }

        // 1. Total de ventas del día
        $totalVentas = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas')
            ->where('IdCliente', $clienteId)
            ->where('IdClienteSucursal', $sucursalId)
            ->where('IdOperadorIngresa', $operadorId)
            ->where('LiquidadoVendedor', 0)
            ->where('IdEstado', 1)
            ->whereDate('FechaVenta', $fechaStr)
            ->sum('ImporteVenta');

        $totalVentas = round($totalVentas, 2);

        // 2. Obtener TODOS los conceptos activos del cliente
        $conceptos = VentaLiquidacionConcepto::porContexto()
            ->activos()
            ->get();

        // 3. Calcular montos del sistema para cada concepto
        $montosSistema = [];
        foreach ($conceptos as $concepto) {
            $monto = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas as v')
                ->join('impuestos_ventas_liquidacion as l', 'v.IdVentas', '=', 'l.IdVentas')
                ->join('impuestos_ventas_liquidacion_concepto as c', 'l.IdCuenta', '=', 'c.IdCuenta')
                ->where('v.IdCliente', $clienteId)
                ->where('v.IdClienteSucursal', $sucursalId)
                ->where('v.IdOperadorIngresa', $operadorId)
                ->where('v.LiquidadoVendedor', 0)
                ->where('v.IdEstado', 1)
                ->where('v.ActivoInactivo', 1)
                ->whereDate('v.FechaVenta', $fechaStr)
                ->where('c.Concepto', $concepto->Concepto)
                ->where('c.IdCliente', $clienteId)
                ->sum('l.Bolivianos');

            $montosSistema[$concepto->IdConceptoLiquidacion] = round($monto, 2);
        }

        // 4. Verificar si ya existe una liquidación para esta fecha
        $liquidacionExistente = LiquidacionVendedor::porContexto()
            ->porVendedor()
            ->where('IdFecha', $fechaId)
            ->where('ActivoInactivo', 0)
            ->first();

        if ($liquidacionExistente) {
            // Cargar detalles existentes
            $detalles = $liquidacionExistente->detalles()->with('concepto')->get();
            $montosConfirmacion = [];
            foreach ($detalles as $detalle) {
                $montosConfirmacion[$detalle->IdConceptoLiquidacion] = $detalle->monto_confirmacion;
            }

            return response()->json([
                'success' => true,
                'liquidacion' => $liquidacionExistente,
                'montosConfirmacion' => $montosConfirmacion,
                'fechaStr' => $fechaStr,
                'fechaId' => $fechaId,
            ]);
        }

        // 5. Crear datos para nueva liquidación
        $montosConfirmacion = [];
        foreach ($conceptos as $concepto) {
            $montosConfirmacion[$concepto->IdConceptoLiquidacion] = $montosSistema[$concepto->IdConceptoLiquidacion] ?? 0;
        }

        $sumaMontos = array_sum($montosConfirmacion);
        $diferencia = round($totalVentas - $sumaMontos, 2);

        return response()->json([
            'success' => true,
            'data' => [
                'vEntas' => $totalVentas,
                'vEntasConfirma' => $totalVentas,
                'dIfVendedor' => $diferencia,
                'dIfVendedorConfirma' => $diferencia,
            ],
            'conceptos' => $conceptos->map(function($c) use ($montosSistema, $montosConfirmacion) {
                return [
                    'id' => $c->IdConceptoLiquidacion,
                    'nombre' => $c->Concepto,
                    'monto_sistema' => $montosSistema[$c->IdConceptoLiquidacion] ?? 0,
                    'monto_confirmacion' => $montosConfirmacion[$c->IdConceptoLiquidacion] ?? 0,
                ];
            }),
            'fechaStr' => $fechaStr,
            'fechaId' => $fechaId,
        ]);
    }

    /**
     * Guardar liquidación y contabilizar
     */
    public function guardar(Request $request)
    {
        $request->validate([
            'IdFecha' => 'required|exists:todos_fecha,IdFecha',
            'vEntasConfirma' => 'required|numeric',
            'conceptos' => 'required|array',
            'conceptos.*.id' => 'required|exists:impuestos_ventas_liquidacion_concepto,IdConceptoLiquidacion',
            'conceptos.*.monto_confirmacion' => 'required|numeric|min:0',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $fechaId = $request->IdFecha;

        if ($request->vEntasConfirma == 0) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene datos...!'
            ], 400);
        }

        // 🔥 OBTENER LA FECHA REAL
        $fecha = Fecha::find($fechaId);
        $fechaStr = $fecha ? $fecha->Fecha : null;

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            $sumaMontos = array_sum(array_column($request->conceptos, 'monto_confirmacion'));
            $diferencia = round($request->vEntasConfirma - $sumaMontos, 2);

            // Crear liquidación
            $liquidacion = LiquidacionVendedor::create([
                'IdFecha' => $fechaId,
                'IdDiario' => 0,
                'vEntas' => $request->vEntasConfirma,
                'vEntasConfirma' => $request->vEntasConfirma,
                'dIfVendedor' => $diferencia,
                'dIfVendedorConfirma' => $diferencia,
                'ActivoInactivo' => 1,
                'LiquidadoSupervisor' => 0,
                'iDcliente' => $clienteId,
                'iDsucursal' => $sucursalId,
                'iDtipoOperadorVentas' => 1,
                'iDoperadorVendedor' => $operadorId,
            ]);

            // Guardar detalles
            foreach ($request->conceptos as $concepto) {
                LiquidacionVendedorDetalle::create([
                    'iDLiquidacionVendedor' => $liquidacion->iDLiquidacionVendedor,
                    'IdConceptoLiquidacion' => $concepto['id'],
                    'monto_sistema' => $concepto['monto_sistema'] ?? 0,
                    'monto_confirmacion' => $concepto['monto_confirmacion'],
                ]);
            }

            // =============================================
            // CREAR DIARIO Y ASIENTOS (como Scriptcase)
            // =============================================
            
            // Parámetros de cuentas
            $parametrosCuentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_parametros_cuentas')
                ->where('IdCliente', $clienteId)
                ->first();

            if (!$parametrosCuentas) {
                throw new \Exception('No se encontraron parámetros de cuentas contables');
            }

            // UFV actual
            $ufvActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_factorcambio')
                ->where('IdFecha', $fechaId)
                ->where('IdMoneda', 3)
                ->value('FactorCambio') ?? 1;

            // Identificador del operador
            $identificadorOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $operadorId)
                ->value('IdIdentificador');

            // Número de diario
            $maxNumeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->max('NumeroDiario');
            
            $numeroDiario = ($maxNumeroDiario ?? 0) + 1;

            // Insertar diario
            $diarioId = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->insertGetId([
                    'IdFecha' => $fechaId,
                    'IdTipoDiario' => 10,
                    'NumeroDiario' => $numeroDiario,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'Contabilizado' => 1,
                    'IdOperadorIngreso' => $operadorId,
                    'FechaIngreso' => now(),
                    'IdoperadorEdita' => $operadorId,
                    'FechaEdita' => now(),
                ]);

            // =============================================
            // ASIENTOS CONTABLES
            // =============================================
            
            $totalVentaFacturada = round($request->vEntasConfirma, 2);
            $totalVentaFacturadaUFV = round($totalVentaFacturada / $ufvActual, 2);
            
            // Ventas Facturadas (HABER)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $parametrosCuentas->VentasFacturadas,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'H',
                    'MontoBolivianos' => $totalVentaFacturada,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $totalVentaFacturadaUFV,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Control Debito Fiscal (DEBE)
            $ivaDF = round($totalVentaFacturada * 0.13, 2);
            $ivaDF_UFV = round($ivaDF / $ufvActual, 2);
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $parametrosCuentas->ControlDFIVA,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'D',
                    'MontoBolivianos' => $ivaDF,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $ivaDF_UFV,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Debito Fiscal IVA (HABER)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $parametrosCuentas->DebitoFiscalIVA,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'H',
                    'MontoBolivianos' => $ivaDF,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $ivaDF,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // IT Pagados (DEBE)
            $itPagados = round($totalVentaFacturada * 0.03, 2);
            $itPagados_UFV = round($itPagados / $ufvActual, 2);
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $parametrosCuentas->ITPagados,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'D',
                    'MontoBolivianos' => $itPagados,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $itPagados_UFV,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // IT x Pagar (HABER)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $parametrosCuentas->ITxPagar,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'H',
                    'MontoBolivianos' => $itPagados,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $itPagados,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // =============================================
            // COSTO DE VENTA
            // =============================================
            
            // Obtener ventas por FECHA (como Scriptcase)
            $ventasLiquidadas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $sucursalId)
                ->where('IdOperadorIngresa', $operadorId)
                ->where('LiquidadoVendedor', 0)
                ->where('IdEstado', 1)
                ->whereDate('FechaVenta', $fechaStr)
                ->pluck('IdVentas')
                ->toArray();

            $totalCostoVentas = 0;
            
            foreach ($ventasLiquidadas as $idVenta) {
                $costoVenta = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->where('IdTipoDeOperacion', 2)
                    ->where('IdDocumento', $idVenta)
                    ->sum('Bolivianos') ?? 0;
                
                $totalCostoVentas += $costoVenta;
            }
            
            $totalCostoVentasUFV = round($totalCostoVentas / $ufvActual, 2);
            
            // Costo de Venta (DEBE)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => 2618, // Cuenta Costo de Venta (ajustar según tu plan)
                    'Glosa' => 'Liquidacion Ventas - Costo',
                    'D_H' => 'D',
                    'MontoBolivianos' => $totalCostoVentas,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $totalCostoVentasUFV,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Inventarios (HABER)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => 2694, // Cuenta Inventarios (ajustar según tu plan)
                    'Glosa' => 'Liquidacion Ventas - Costo',
                    'D_H' => 'H',
                    'MontoBolivianos' => $totalCostoVentas,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $totalCostoVentas,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // =============================================
            // MÉTODOS DE PAGO
            // =============================================
            foreach ($request->conceptos as $conceptoData) {
                $monto = $conceptoData['monto_confirmacion'];
                if ($monto <= 0) continue;
                
                $cuentaConcepto = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion_concepto')
                    ->where('IdConceptoLiquidacion', $conceptoData['id'])
                    ->first();
                
                if (!$cuentaConcepto) continue;
                
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $diarioId,
                        'IdCuenta' => $cuentaConcepto->IdCuenta,
                        'Glosa' => 'Liquidacion Ventas',
                        'D_H' => 'D',
                        'MontoBolivianos' => $monto,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $monto,
                        'IdIdentificador' => $identificadorOperador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);
            }

            // =============================================
            // DIFERENCIA
            // =============================================
            $cuentaPersonalVendedor = $parametrosCuentas->CuentaPersonalVendedor ?? 0;
            $diferenciaAbs = abs($diferencia);
            
            if ($diferencia > 0) {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $diarioId,
                        'IdCuenta' => $cuentaPersonalVendedor,
                        'Glosa' => 'Liquidacion Ventas',
                        'D_H' => 'D',
                        'MontoBolivianos' => $diferenciaAbs,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $diferenciaAbs,
                        'IdIdentificador' => $identificadorOperador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);
            } elseif ($diferencia < 0) {
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $diarioId,
                        'IdCuenta' => $cuentaPersonalVendedor,
                        'Glosa' => 'Liquidacion Ventas',
                        'D_H' => 'H',
                        'MontoBolivianos' => $diferenciaAbs,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $diferenciaAbs,
                        'IdIdentificador' => $identificadorOperador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);
            }

            // =============================================
            // ACTUALIZAR LIQUIDACIÓN Y VENTAS
            // =============================================
            $liquidacion->update(['IdDiario' => $diarioId]);

            // 🔥 ACTUALIZAR VENTAS POR FECHA (NO por IdFecha)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $sucursalId)
                ->where('IdOperadorIngresa', $operadorId)
                ->where('LiquidadoVendedor', 0)
                ->where('IdEstado', 1)
                ->whereDate('FechaVenta', $fechaStr)
                ->update(['LiquidadoVendedor' => $diarioId]);

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return response()->json([
                'success' => true,
                'liquidacion_id' => $liquidacion->iDLiquidacionVendedor,
                'pdf_url' => route('liquidacion-vendedor.pdf', $liquidacion->iDLiquidacionVendedor),
                'message' => 'Liquidación realizada correctamente. Diario N° ' . $numeroDiario
            ]);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al liquidar: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al liquidar: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar PDF de la liquidación (Versión Corregida - Inventario como Scriptcase)
     */
    public function pdf($id)
    {
        \Log::info('=== GENERANDO PDF LIQUIDACION ===');
        \Log::info('ID Liquidacion: ' . $id);
        
        try {
            $liquidacion = LiquidacionVendedor::with(['detalles.concepto', 'fecha'])
                ->findOrFail($id);
            
            \Log::info('Liquidacion encontrada', ['id' => $liquidacion->iDLiquidacionVendedor]);

            $clienteId = $liquidacion->iDcliente;
            $sucursalId = $liquidacion->iDsucursal;
            $vendedorId = $liquidacion->iDoperadorVendedor;
            $diarioId = $liquidacion->IdDiario;
            $idFechaCorte = $liquidacion->IdFecha;

            // Datos de la empresa
            $empresa = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente')
                ->where('IdCliente', $clienteId)
                ->first();

            $sucursal = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_cliente_sucursal')
                ->where('IdClienteSucursal', $sucursalId)
                ->first();

            // 🔥 NÚMERO DE DIARIO (NO el ID)
            $numeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdDiario', $diarioId)
                ->value('NumeroDiario');

            // Fecha de liquidación
            $fechaLiquidacion = $liquidacion->fecha ? $liquidacion->fecha->Fecha : now();
            $fechaFormateada = date('d/m/Y', strtotime($fechaLiquidacion));

            // Nombre del vendedor
            $vendedor = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador as o')
                ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
                ->where('o.IdOperador', $vendedorId)
                ->first();

            $nombreVendedor = $vendedor ? $vendedor->Nombre : 'Vendedor';

            // =============================================
            // LISTA DE VENTAS POR COMISIONISTA
            // =============================================
            $ventas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas as v')
                ->join('impuestos_ventas_detalle as vd', 'v.IdVentas', '=', 'vd.idventas')
                ->join('inventario_relacion_ventainventario as irv', 'vd.idrelacionventainventario', '=', 'irv.IdDetalleProducto')
                ->leftJoin('inventario_relacion_ventainventario_grupouno as g', 'irv.IdVentaGrupo', '=', 'g.IdVentaGrupo')
                ->where('v.IdCliente', $clienteId)
                ->where('v.IdClienteSucursal', $sucursalId)
                ->where('v.IdOperadorIngresa', $vendedorId)
                ->where('v.LiquidadoVendedor', $diarioId)
                ->where('v.IdEstado', 1)
                ->select(
                    'v.IdComisionista',
                    'irv.Detalle as producto',
                    'vd.unidades',
                    'vd.preciounidades',
                    DB::raw('vd.unidades * vd.preciounidades as total_linea'),
                    'g.Detalle as grupo'
                )
                ->orderBy('v.IdComisionista')
                ->orderBy('g.Detalle')
                ->orderBy('irv.Detalle')
                ->get();

            // Agrupar por comisionista
            $ventasPorComisionista = [];
            foreach ($ventas as $venta) {
                $comisionistaId = $venta->IdComisionista;
                if (!isset($ventasPorComisionista[$comisionistaId])) {
                    $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_comisionitas as c')
                        ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                        ->where('c.IdComisionista', $comisionistaId)
                        ->first();
                    
                    $ventasPorComisionista[$comisionistaId] = [
                        'nombre' => $comisionista ? $comisionista->Nombre : 'Sin comisionista',
                        'items' => [],
                        'total' => 0
                    ];
                }
                $ventasPorComisionista[$comisionistaId]['items'][] = $venta;
                $ventasPorComisionista[$comisionistaId]['total'] += $venta->total_linea;
            }

            // =============================================
            // INVENTARIO ACTUALIZADO
            // =============================================
            $inventarioConSaldo = [];
            
            $productosActivos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 0)
                ->orderBy('Descripcion')
                ->get(['IdProducto', 'Descripcion']);

            foreach ($productosActivos as $producto) {
                $saldoAnterior = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->where('IdProducto', $producto->IdProducto)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdFecha', '<', $idFechaCorte)
                    ->select(DB::raw('SUM(CASE WHEN D_H = "D" THEN Unidades ELSE 0 END) - SUM(CASE WHEN D_H = "H" THEN Unidades ELSE 0 END) as saldo'))
                    ->value('saldo') ?? 0;
                
                $ingresosDia = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->where('IdProducto', $producto->IdProducto)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdFecha', $idFechaCorte)
                    ->where('D_H', 'D')
                    ->sum('Unidades') ?? 0;
                
                $salidasDia = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->where('IdProducto', $producto->IdProducto)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->where('IdFecha', $idFechaCorte)
                    ->where('D_H', 'H')
                    ->sum('Unidades') ?? 0;
                
                if ($ingresosDia != 0 || $salidasDia != 0 || $saldoAnterior != 0) {
                    $inventarioConSaldo[] = [
                        'producto' => $producto->Descripcion,
                        'saldo_anterior' => $saldoAnterior,
                        'ingresos' => $ingresosDia,
                        'salidas' => $salidasDia,
                        'saldo_actual' => $saldoAnterior + $ingresosDia - $salidasDia
                    ];
                }
            }

            usort($inventarioConSaldo, function($a, $b) {
                return strcmp($a['producto'], $b['producto']);
            });

            // =============================================
            // GENERAR PDF (TAMAÑO DINÁMICO)
            // =============================================
            
            // Calcular altura aproximada del contenido
            $totalAltura = 0;
            
            // Cabecera
            $totalAltura += 60; // Header + tabla resumen
            
            // Ventas por comisionista
            foreach ($ventasPorComisionista as $comisionistaData) {
                $totalAltura += 15; // Título comisionista
                foreach ($comisionistaData['items'] as $item) {
                    $lineasProducto = ceil(strlen($item->producto) / 45);
                    $totalAltura += $lineasProducto * 5;
                }
                $totalAltura += 10; // Total comisionista
            }
            
            // Inventario
            $totalAltura += 20; // Título inventario + encabezado
            $totalAltura += count($inventarioConSaldo) * 6;
            
            // Altura total estimada (mínimo 150mm, máximo 500mm)
            $alturaHoja = max(150, min(500, $totalAltura + 10));
            
            $pdf = new \TCPDF('P', 'mm', array(100, $alturaHoja));
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(5, 5, 5);
            $pdf->SetAutoPageBreak(true, 8);
            $pdf->AddPage();
            $pdf->SetFont('times', '', 8);

            // =============================================
            // CABECERA
            // =============================================
            $pdf->SetFont('times', 'B', 10);
            $pdf->Cell(90, 5, "Arqueo de Venta", 0, 1, 'C');
            $pdf->Cell(90, 5, "Numero de Diario No " . ($numeroDiario ?? '-'), 0, 1, 'C');
            $pdf->Cell(90, 5, $empresa->Nombre ?? '', 0, 1, 'C');
            $pdf->Cell(90, 5, $sucursal->Nombre ?? '', 0, 1, 'C');
            $pdf->Cell(90, 5, $fechaFormateada, 0, 1, 'C');
            $pdf->Cell(90, 5, $nombreVendedor, 0, 1, 'C');

            // =============================================
            // TABLA RESUMEN CONCEPTOS
            // =============================================
            $pdf->Ln(3);
            $pdf->SetFont('times', 'B', 8);
            $pdf->Cell(40, 5, "Concepto", 0, 0, 'L');
            $pdf->Cell(25, 5, "Sistema", 0, 0, 'R');
            $pdf->Cell(25, 5, "Confirmacion", 0, 1, 'R');
            $pdf->Cell(90, 1, "", 'T', 1);

            $pdf->SetFont('times', '', 8);
            $pdf->Cell(40, 5, 'Tot.Ventas', 0, 0, 'L');
            $pdf->Cell(25, 5, number_format($liquidacion->vEntas, 2, '.', ''), 0, 0, 'R');
            $pdf->Cell(25, 5, number_format($liquidacion->vEntasConfirma, 2, '.', ''), 0, 1, 'R');

            foreach ($liquidacion->detalles as $detalle) {
                $nombreConcepto = $detalle->concepto ? $detalle->concepto->Concepto : 'Concepto';
                $pdf->Cell(40, 5, $nombreConcepto, 0, 0, 'L');
                $pdf->Cell(25, 5, number_format($detalle->monto_sistema, 2, '.', ''), 0, 0, 'R');
                $pdf->Cell(25, 5, number_format($detalle->monto_confirmacion, 2, '.', ''), 0, 1, 'R');
            }

            $pdf->Cell(90, 1, "", 'T', 1);
            $pdf->SetFont('times', 'B', 8);
            $pdf->Cell(40, 5, 'Dif.Ventas', 0, 0, 'L');
            $pdf->Cell(25, 5, number_format($liquidacion->dIfVendedor, 2, '.', ''), 0, 0, 'R');
            $pdf->Cell(25, 5, number_format($liquidacion->dIfVendedorConfirma, 2, '.', ''), 0, 1, 'R');

            // =============================================
            // LISTA DE VENTAS POR COMISIONISTA (MULTILÍNEA)
            // =============================================
            if ($pdf->GetY() > 240) {
                $pdf->AddPage();
                $pdf->SetFont('times', '', 8);
            }
            
            $pdf->Ln(5);
            $pdf->SetFont('times', 'B', 9);
            $pdf->Cell(90, 5, "Lista de Ventas", 0, 1, 'C');

            $pdf->SetFont('times', 'B', 8);
            $pdf->Cell(45, 5, "Producto", 0, 0, 'L');
            $pdf->Cell(12, 5, "Unid.", 0, 0, 'R');
            $pdf->Cell(12, 5, "P.U.", 0, 0, 'R');
            $pdf->Cell(15, 5, "Total", 0, 1, 'R');
            $pdf->Cell(90, 1, "", 'T', 1);

            $pdf->SetFont('times', '', 7);
            $totalGeneralVentas = 0;

            foreach ($ventasPorComisionista as $comisionistaId => $comisionistaData) {
                if ($pdf->GetY() > 250) {
                    $pdf->AddPage();
                    $pdf->SetFont('times', '', 7);
                    // Reimprimir encabezado
                    $pdf->SetFont('times', 'B', 8);
                    $pdf->Cell(45, 5, "Producto", 0, 0, 'L');
                    $pdf->Cell(12, 5, "Unid.", 0, 0, 'R');
                    $pdf->Cell(12, 5, "P.U.", 0, 0, 'R');
                    $pdf->Cell(15, 5, "Total", 0, 1, 'R');
                    $pdf->Cell(90, 1, "", 'T', 1);
                    $pdf->SetFont('times', '', 7);
                }
                
                $pdf->Ln(2);
                $pdf->SetFont('times', 'B', 8);
                $nombreComisionista = substr($comisionistaData['nombre'], 0, 40);
                $pdf->Cell(90, 5, "Comisionista: " . $nombreComisionista, 0, 1, 'L');
                $pdf->SetFont('times', '', 7);

                foreach ($comisionistaData['items'] as $item) {
                    // Verificar espacio en página
                    if ($pdf->GetY() > 265) {
                        $pdf->AddPage();
                        $pdf->SetFont('times', '', 7);
                        // Reimprimir encabezado
                        $pdf->SetFont('times', 'B', 8);
                        $pdf->Cell(45, 5, "Producto", 0, 0, 'L');
                        $pdf->Cell(12, 5, "Unid.", 0, 0, 'R');
                        $pdf->Cell(12, 5, "P.U.", 0, 0, 'R');
                        $pdf->Cell(15, 5, "Total", 0, 1, 'R');
                        $pdf->Cell(90, 1, "", 'T', 1);
                        $pdf->SetFont('times', '', 7);
                    }
                    
                    // 🔥 PRODUCTO MULTILÍNEA
                    $productoTexto = '';
                    if ($item->grupo) {
                        $productoTexto = $item->grupo . ' - ';
                    }
                    $productoTexto .= $item->producto;
                    
                    $unidades = number_format($item->unidades, 2, '.', '');
                    $precio = number_format($item->preciounidades, 2, '.', '');
                    $totalLinea = number_format($item->total_linea, 2, '.', '');
                    
                    // Guardar posición Y actual
                    $yAntes = $pdf->GetY();
                    
                    // 🔥 MultiCell para producto (multilínea, ancho 45mm)
                    $pdf->MultiCell(45, 4, $productoTexto, 0, 'L', false);
                    
                    // Obtener la altura que usó MultiCell
                    $yDespues = $pdf->GetY();
                    $alturaUsada = $yDespues - $yAntes;
                    
                    // Posicionar en la misma fila para los demás campos
                    $pdf->SetXY(50, $yAntes);
                    $pdf->Cell(12, $alturaUsada, $unidades, 0, 0, 'R');
                    $pdf->Cell(12, $alturaUsada, $precio, 0, 0, 'R');
                    $pdf->Cell(15, $alturaUsada, $totalLinea, 0, 1, 'R');
                    
                    $totalGeneralVentas += $item->total_linea;
                }

                // Total por comisionista
                if ($pdf->GetY() > 265) {
                    $pdf->AddPage();
                    $pdf->SetFont('times', '', 7);
                }
                
                $pdf->SetFont('times', 'B', 7);
                $nombreSubtotal = strtoupper(substr($comisionistaData['nombre'], 0, 25));
                $pdf->Cell(69, 5, "TOTAL " . $nombreSubtotal . ":", 'T', 0, 'R');
                $pdf->Cell(15, 5, number_format($comisionistaData['total'], 2, '.', ''), 'T', 1, 'R');
                $pdf->SetFont('times', '', 7);
            }

            // Total general
            if ($pdf->GetY() > 265) {
                $pdf->AddPage();
                $pdf->SetFont('times', '', 7);
            }
            
            $pdf->SetFont('times', 'B', 8);
            $pdf->Cell(69, 5, "TOTAL GENERAL:", 'T', 0, 'R');
            $pdf->Cell(15, 5, number_format($totalGeneralVentas, 2, '.', ''), 'T', 1, 'R');

            // =============================================
            // INVENTARIO ACTUALIZADO
            // =============================================
            if (count($inventarioConSaldo) > 0) {
                if ($pdf->GetY() > 230) {
                    $pdf->AddPage();
                }
                
                $pdf->Ln(5);
                $pdf->SetFont('times', 'B', 9);
                $pdf->Cell(90, 5, "Inventario actualizado", 0, 1, 'C');

                $pdf->SetFont('times', 'B', 8);
                $pdf->Cell(45, 5, "Producto", 0, 0, 'L');
                $pdf->Cell(12, 5, "Sal.Ini.", 0, 0, 'R');
                $pdf->Cell(12, 5, "Ingr.", 0, 0, 'R');
                $pdf->Cell(12, 5, "Salida", 0, 0, 'R');
                $pdf->Cell(12, 5, "Saldo", 0, 1, 'R');
                $pdf->Cell(90, 1, "", 'T', 1);

                $pdf->SetFont('times', '', 7);
                
                foreach ($inventarioConSaldo as $item) {
                    if ($pdf->GetY() > 270) {
                        $pdf->AddPage();
                        $pdf->SetFont('times', '', 7);
                        
                        // Reimprimir encabezado inventario
                        $pdf->SetFont('times', 'B', 8);
                        $pdf->Cell(45, 5, "Producto", 0, 0, 'L');
                        $pdf->Cell(12, 5, "Sal.Ini.", 0, 0, 'R');
                        $pdf->Cell(12, 5, "Ingr.", 0, 0, 'R');
                        $pdf->Cell(12, 5, "Salida", 0, 0, 'R');
                        $pdf->Cell(12, 5, "Saldo", 0, 1, 'R');
                        $pdf->Cell(90, 1, "", 'T', 1);
                        $pdf->SetFont('times', '', 7);
                    }
                    
                    // 🔥 PRODUCTO MULTILÍNEA PARA INVENTARIO
                    $yAntes = $pdf->GetY();
                    $pdf->MultiCell(45, 4, $item['producto'], 0, 'L', false);
                    $yDespues = $pdf->GetY();
                    $alturaUsada = $yDespues - $yAntes;
                    
                    $pdf->SetXY(50, $yAntes);
                    $pdf->Cell(12, $alturaUsada, number_format($item['saldo_anterior'], 2, '.', ''), 0, 0, 'R');
                    $pdf->Cell(12, $alturaUsada, number_format($item['ingresos'], 2, '.', ''), 0, 0, 'R');
                    $pdf->Cell(12, $alturaUsada, number_format($item['salidas'], 2, '.', ''), 0, 0, 'R');
                    $pdf->Cell(12, $alturaUsada, number_format($item['saldo_actual'], 2, '.', ''), 0, 1, 'R');
                }
            }

            $pdf->Output("liquidacion_{$liquidacion->iDLiquidacionVendedor}.pdf", 'I');
            exit;
            
        } catch (\Exception $e) {
            \Log::error('Error PDF liquidacion: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    //------LQUIDACIONES-----
    /**
     * Listado de liquidaciones del operador logueado
     */
    public function liquidacionesPorOperador()
    {
        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        
        $liquidaciones = LiquidacionVendedor::porContexto()
            ->where('iDoperadorVendedor', $operadorId)
            ->with(['fecha', 'detalles.concepto'])
            ->orderBy('iDLiquidacionVendedor', 'desc')
            ->paginate(20);
        
        // 🔥 AGREGAR NÚMERO DE DIARIO A CADA LIQUIDACIÓN
        foreach ($liquidaciones as $liquidacion) {
            $liquidacion->numero_diario = $liquidacion->numero_diario;
        }
        
        // Obtener el nombre del operador actual
        $operador = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador as o')
            ->join('todos_identificador as i', 'o.IdIdentificador', '=', 'i.IdIdentificador')
            ->where('o.IdOperador', $operadorId)
            ->first();
        
        $nombreOperador = $operador ? $operador->Nombre : 'Operador';
        
        return Inertia::render('Gestion/Impuestos/LiquidacionVendedor/IndexOperador', [
            'liquidaciones' => $liquidaciones,
            'titulo' => 'Mis Liquidaciones',
            'subtitulo' => 'Historial de liquidaciones realizadas por ' . $nombreOperador,
            'nombreOperador' => $nombreOperador,
        ]);
    }

    /**
     * Reimprimir PDF de una liquidación existente
     */
    public function reimprimir($id)
    {
        return $this->pdf($id);
    }
}