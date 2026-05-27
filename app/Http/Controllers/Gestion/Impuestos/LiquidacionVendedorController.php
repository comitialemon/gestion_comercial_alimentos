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
     * Generar PDF de la liquidación (EXACTAMENTE como Scriptcase)
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

            // NÚMERO DE DIARIO (NO el ID)
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
            // LISTA DE VENTAS POR COMISIONISTA (AGRUPADA como Scriptcase)
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
                    DB::raw('SUM(vd.unidades) as unidades'),
                    'vd.preciounidades',
                    DB::raw('SUM(vd.totalbolivianos) as total_linea'),
                    'g.Detalle as grupo'
                )
                ->groupBy(
                    'v.IdComisionista',
                    'vd.idrelacionventainventario',
                    'vd.preciounidades',
                    'irv.Detalle',
                    'irv.IdVentaGrupo',
                    'g.Detalle'
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
            // INVENTARIO ACTUALIZADO (EXACTAMENTE como Scriptcase)
            // =============================================
            $saldosIniciales = [];
            
            // Obtener fecha referencial
            $fechaReferenciaObj = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_fecha')
                ->where('IdFecha', $idFechaCorte)
                ->first();
            
            if ($fechaReferenciaObj) {
                // 1. SALDO INICIAL (hasta el día anterior)
                $invSaldo = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente as inv')
                    ->join('inventario_productodetalle as prod', 'inv.IdProducto', '=', 'prod.IdProducto')
                    ->where('inv.IdFecha', '<', $idFechaCorte)
                    ->where('inv.IdCliente', $clienteId)
                    ->where('inv.IdSucursal', $sucursalId)
                    ->select(
                        'inv.IdProducto',
                        'prod.Descripcion as detalle',
                        DB::raw('SUM(CASE WHEN inv.D_H = "D" THEN inv.Unidades ELSE 0 END) as Ingresos'),
                        DB::raw('SUM(CASE WHEN inv.D_H = "H" THEN inv.Unidades ELSE 0 END) as Salidas')
                    )
                    ->groupBy('inv.IdProducto', 'prod.Descripcion')
                    ->get();
                
                foreach ($invSaldo as $row) {
                    $saldosIniciales[$row->IdProducto] = [
                        'detalle' => $row->detalle,
                        'inicial' => $row->Ingresos - $row->Salidas,
                        'ingresos' => 0,
                        'salidas' => 0
                    ];
                }
                
                // 2. MOVIMIENTOS DEL DÍA
                $invDia = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->where('IdFecha', $idFechaCorte)
                    ->where('IdCliente', $clienteId)
                    ->where('IdSucursal', $sucursalId)
                    ->select(
                        'IdProducto',
                        DB::raw('SUM(CASE WHEN D_H = "D" THEN Unidades ELSE 0 END) as Ingresos'),
                        DB::raw('SUM(CASE WHEN D_H = "H" THEN Unidades ELSE 0 END) as Salidas')
                    )
                    ->groupBy('IdProducto')
                    ->get();
                
                foreach ($invDia as $row) {
                    if (!isset($saldosIniciales[$row->IdProducto])) {
                        // Producto nuevo que solo tuvo movimiento hoy
                        $producto = DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('inventario_productodetalle')
                            ->where('IdProducto', $row->IdProducto)
                            ->first();
                        
                        $saldosIniciales[$row->IdProducto] = [
                            'detalle' => $producto ? $producto->Descripcion : 'Producto',
                            'inicial' => 0,
                            'ingresos' => $row->Ingresos,
                            'salidas' => $row->Salidas
                        ];
                    } else {
                        $saldosIniciales[$row->IdProducto]['ingresos'] += $row->Ingresos;
                        $saldosIniciales[$row->IdProducto]['salidas'] += $row->Salidas;
                    }
                }
            }
            
            // 3. OBTENER TODOS LOS PRODUCTOS ACTIVOS DE LA SUCURSAL
            $todosProductos = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('inventario_productodetalle')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->where('ActivoInactivo', 0)
                ->orderBy('Descripcion')
                ->get(['IdProducto', 'Descripcion']);
            
            // Agregar productos que no están en $saldosIniciales (sin movimiento)
            foreach ($todosProductos as $producto) {
                if (!isset($saldosIniciales[$producto->IdProducto])) {
                    $saldosIniciales[$producto->IdProducto] = [
                        'detalle' => $producto->Descripcion,
                        'inicial' => 0,
                        'ingresos' => 0,
                        'salidas' => 0
                    ];
                }
            }
            
            // 4. SEPARAR PRODUCTOS CON Y SIN MOVIMIENTO
            $conMovimiento = [];
            $sinMovimiento = [];
            
            foreach ($saldosIniciales as $datos) {
                if ($datos['ingresos'] != 0 || $datos['salidas'] != 0) {
                    $conMovimiento[] = $datos;
                } else {
                    $sinMovimiento[] = $datos;
                }
            }
            
            // Ordenar alfabéticamente
            usort($conMovimiento, function($a, $b) {
                return strcmp($a['detalle'], $b['detalle']);
            });
            usort($sinMovimiento, function($a, $b) {
                return strcmp($a['detalle'], $b['detalle']);
            });

            // =============================================
            // GENERAR PDF
            // =============================================
            
            // Calcular altura aproximada
            $totalAltura = 60;
            foreach ($ventasPorComisionista as $comisionistaData) {
                $totalAltura += 10;
                foreach ($comisionistaData['items'] as $item) {
                    $lineasProducto = ceil(strlen($item->producto) / 45);
                    $totalAltura += $lineasProducto * 5;
                }
                $totalAltura += 8;
            }
            $totalAltura += 20 + (count($conMovimiento) * 5) + (count($sinMovimiento) * 5);
            
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
            // LISTA DE VENTAS POR COMISIONISTA
            // =============================================
            $pdf->Ln(5);
            $pdf->SetFont('times', 'B', 9);
            $pdf->Cell(90, 5, "Lista de Ventas", 0, 1, 'C');

            $pdf->SetFont('times', 'B', 8);
            $pdf->Cell(60, 5, "Producto", 0, 0, 'L');
            $pdf->Cell(10, 5, "Unid.", 0, 0, 'R');
            $pdf->Cell(10, 5, "P.U.", 0, 0, 'R');
            $pdf->Cell(10, 5, "Total", 0, 1, 'R');
            $pdf->Cell(90, 1, "", 'T', 1);

            $pdf->SetFont('times', '', 7);
            $totalGeneralVentas = 0;
            $comisionistaActual = null;
            $totalComisionista = 0;
            $nombreComisionistaActual = '';

            foreach ($ventas as $venta) {
                $comisionistaId = $venta->IdComisionista;
                
                if (!isset($ventasPorComisionista[$comisionistaId])) {
                    $comisionista = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas_comisionitas as c')
                        ->join('todos_identificador as i', 'c.IdIdentificador', '=', 'i.IdIdentificador')
                        ->where('c.IdComisionista', $comisionistaId)
                        ->first();
                    $nombreComisionista = $comisionista ? $comisionista->Nombre : 'Comisionista no identificado';
                    $ventasPorComisionista[$comisionistaId] = [
                        'nombre' => $nombreComisionista,
                        'total' => 0
                    ];
                }
                $nombreComisionista = $ventasPorComisionista[$comisionistaId]['nombre'];
                
                if ($comisionistaActual !== null && $comisionistaId !== $comisionistaActual) {
                    $pdf->SetFont('times', 'B', 7);
                    $pdf->Cell(80, 5, "TOTAL " . strtoupper($nombreComisionistaActual) . ":", 'T', 0, 'R');
                    $pdf->Cell(10, 5, number_format($totalComisionista, 2, '.', ''), 'T', 1, 'R');
                    $pdf->Ln(2);
                    $totalComisionista = 0;
                }
                
                if ($comisionistaId !== $comisionistaActual) {
                    $comisionistaActual = $comisionistaId;
                    $nombreComisionistaActual = $nombreComisionista;
                    
                    $pdf->Ln(3);
                    $pdf->SetFont('times', 'B', 8);
                    $pdf->Cell(90, 5, "Comisionista: " . $nombreComisionista, 0, 1, 'L');
                    $pdf->SetFont('times', '', 7);
                }
                
                $prefijoGrupo = '';
                if ($venta->grupo) {
                    $prefijoGrupo = substr($venta->grupo, 0, 1);
                }
                
                $productoTexto = '';
                if ($prefijoGrupo) {
                    $productoTexto = $prefijoGrupo . ' - ';
                }
                $productoTexto .= $venta->producto;
                
                if (strlen($productoTexto) > 90) {
                    $productoTexto = substr($productoTexto, 0, 87) . '...';
                }
                
                $unidades = number_format($venta->unidades, 2, '.', '');
                $precio = number_format($venta->preciounidades, 2, '.', '');
                $totalLinea = number_format($venta->total_linea, 2, '.', '');
                
                if ($pdf->GetY() > 265) {
                    $pdf->AddPage();
                    $pdf->SetFont('times', '', 7);
                    $pdf->SetFont('times', 'B', 8);
                    $pdf->Cell(60, 5, "Producto", 0, 0, 'L');
                    $pdf->Cell(10, 5, "Unid.", 0, 0, 'R');
                    $pdf->Cell(10, 5, "P.U.", 0, 0, 'R');
                    $pdf->Cell(10, 5, "Total", 0, 1, 'R');
                    $pdf->Cell(90, 1, "", 'T', 1);
                    $pdf->SetFont('times', '', 7);
                }
                
                $pdf->Cell(60, 4, $productoTexto, 0, 0, 'L');
                $pdf->Cell(10, 4, $unidades, 0, 0, 'R');
                $pdf->Cell(10, 4, $precio, 0, 0, 'R');
                $pdf->Cell(10, 4, $totalLinea, 0, 1, 'R');
                
                $totalComisionista += $venta->total_linea;
                $totalGeneralVentas += $venta->total_linea;
            }
            
            if ($comisionistaActual !== null) {
                $pdf->SetFont('times', 'B', 7);
                $pdf->Cell(80, 5, "TOTAL " . strtoupper($nombreComisionistaActual) . ":", 'T', 0, 'R');
                $pdf->Cell(10, 5, number_format($totalComisionista, 2, '.', ''), 'T', 1, 'R');
                $pdf->Ln(2);
            }
            
            $pdf->SetFont('times', 'B', 8);
            $pdf->Cell(80, 5, "TOTAL GENERAL:", 'T', 0, 'R');
            $pdf->Cell(10, 5, number_format($totalGeneralVentas, 2, '.', ''), 'T', 1, 'R');

            // =============================================
            // INVENTARIO ACTUALIZADO (como Scriptcase)
            // =============================================
            $pdf->Ln(5);
            $pdf->SetFont('times', 'B', 9);
            $pdf->Cell(90, 5, "Inventario actualizado", 0, 1, 'C');

            $pdf->SetFont('times', 'B', 8);
            $pdf->Cell(50, 5, "Producto", 0, 0, 'L');
            $pdf->Cell(10, 5, "Sal. Ini.", 0, 0, 'R');
            $pdf->Cell(10, 5, "Ingr.", 0, 0, 'R');
            $pdf->Cell(10, 5, "Salida", 0, 0, 'R');
            $pdf->Cell(10, 5, "Saldo", 0, 1, 'R');
            $pdf->Cell(90, 1, "", 'T', 1);

            $pdf->SetFont('times', '', 7);

            // Mostrar productos CON movimiento
            foreach ($conMovimiento as $datos) {
                if ($pdf->GetY() > 265) {
                    $pdf->AddPage();
                    $pdf->SetFont('times', '', 7);
                    $pdf->SetFont('times', 'B', 8);
                    $pdf->Cell(50, 5, "Producto", 0, 0, 'L');
                    $pdf->Cell(10, 5, "Sal. Ini.", 0, 0, 'R');
                    $pdf->Cell(10, 5, "Ingr.", 0, 0, 'R');
                    $pdf->Cell(10, 5, "Salida", 0, 0, 'R');
                    $pdf->Cell(10, 5, "Saldo", 0, 1, 'R');
                    $pdf->Cell(90, 1, "", 'T', 1);
                    $pdf->SetFont('times', '', 7);
                }
                
                $detalle = substr($datos['detalle'], 0, 45);
                $ini = number_format($datos['inicial'], 2, '.', '');
                $ing = number_format($datos['ingresos'], 2, '.', '');
                $sal = number_format($datos['salidas'], 2, '.', '');
                $saldo = number_format($datos['inicial'] + $datos['ingresos'] - $datos['salidas'], 2, '.', '');
                
                $pdf->Cell(50, 4, $detalle, 0, 0, 'L');
                $pdf->Cell(10, 4, $ini, 0, 0, 'R');
                $pdf->Cell(10, 4, $ing, 0, 0, 'R');
                $pdf->Cell(10, 4, $sal, 0, 0, 'R');
                $pdf->Cell(10, 4, $saldo, 0, 1, 'R');
            }

            // Mostrar productos SIN movimiento
            foreach ($sinMovimiento as $datos) {
                if ($pdf->GetY() > 265) {
                    $pdf->AddPage();
                    $pdf->SetFont('times', '', 7);
                    $pdf->SetFont('times', 'B', 8);
                    $pdf->Cell(50, 5, "Producto", 0, 0, 'L');
                    $pdf->Cell(10, 5, "Sal. Ini.", 0, 0, 'R');
                    $pdf->Cell(10, 5, "Ingr.", 0, 0, 'R');
                    $pdf->Cell(10, 5, "Salida", 0, 0, 'R');
                    $pdf->Cell(10, 5, "Saldo", 0, 1, 'R');
                    $pdf->Cell(90, 1, "", 'T', 1);
                    $pdf->SetFont('times', '', 7);
                }
                
                $detalle = substr($datos['detalle'], 0, 45);
                $ini = number_format($datos['inicial'], 2, '.', '');
                $ing = number_format($datos['ingresos'], 2, '.', '');
                $sal = number_format($datos['salidas'], 2, '.', '');
                $saldo = number_format($datos['inicial'] + $datos['ingresos'] - $datos['salidas'], 2, '.', '');
                
                $pdf->Cell(50, 4, $detalle, 0, 0, 'L');
                $pdf->Cell(10, 4, $ini, 0, 0, 'R');
                $pdf->Cell(10, 4, $ing, 0, 0, 'R');
                $pdf->Cell(10, 4, $sal, 0, 0, 'R');
                $pdf->Cell(10, 4, $saldo, 0, 1, 'R');
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