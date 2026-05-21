<?php

namespace App\Http\Controllers\Gestion\Impuestos;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\Venta;
use App\Models\Gestion\Impuestos\VentaLiquidacion;
use App\Models\Gestion\Impuestos\VentaLiquidacionConcepto;
use App\Models\Gestion\Impuestos\LiquidacionVendedor;
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

        // Obtener fechas con ventas pendientes de liquidar
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

        // Obtener la fecha
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

        // 2. Obtener pagos agrupados por concepto
        $pagos = DB::connection('mysql_gestion_comercial_alimentos')
            ->table('impuestos_ventas as v')
            ->join('impuestos_ventas_liquidacion as l', 'v.IdVentas', '=', 'l.IdVentas')
            ->join('impuestos_ventas_liquidacion_concepto as c', function($join) use ($clienteId) {
                $join->on('l.IdCuenta', '=', 'c.IdCuenta')
                     ->where('c.IdCliente', '=', $clienteId);
            })
            ->where('v.IdCliente', $clienteId)
            ->where('v.IdClienteSucursal', $sucursalId)
            ->where('v.IdOperadorIngresa', $operadorId)
            ->where('v.LiquidadoVendedor', 0)
            ->where('v.IdEstado', 1)
            ->where('v.ActivoInactivo', 1)
            ->whereDate('v.FechaVenta', $fechaStr)
            ->select('c.Concepto', DB::raw('SUM(l.Bolivianos) as total'))
            ->groupBy('c.Concepto')
            ->get();

        $efectivo = 0;
        $clientes = 0;
        $qr = 0;
        $tarjeta = 0;

        foreach ($pagos as $pago) {
            switch ($pago->Concepto) {
                case 'Efectivo':
                    $efectivo = round($pago->total, 2);
                    break;
                case 'Cliente':
                    $clientes = round($pago->total, 2);
                    break;
                case 'QR':
                    $qr = round($pago->total, 2);
                    break;
                case 'Tarjeta':
                    $tarjeta = round($pago->total, 2);
                    break;
            }
        }

        // Calcular diferencia
        $sumaPagos = $efectivo + $clientes + $qr + $tarjeta;
        $diferencia = round($totalVentas - $sumaPagos, 2);

        // Verificar si ya existe una liquidación para esta fecha
        $liquidacionExistente = LiquidacionVendedor::where('iDcliente', $clienteId)
            ->where('iDsucursal', $sucursalId)
            ->where('iDoperadorVendedor', $operadorId)
            ->where('IdFecha', $fechaId)
            ->where('ActivoInactivo', 0)
            ->first();

        if ($liquidacionExistente) {
            // Usar datos existentes
            return response()->json([
                'success' => true,
                'liquidacion' => $liquidacionExistente,
                'fechaStr' => $fechaStr,
                'fechaId' => $fechaId,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'vEntas' => $totalVentas,
                'vEntasConfirma' => $totalVentas,
                'eFectivoBolivianos' => $efectivo,
                'eFectivoBolivianosConfirma' => $efectivo,
                'cLientes' => $clientes,
                'cLientesConfirma' => $clientes,
                'pOrCobrarPersonal' => $qr,
                'pOrCobrarPersonalConfirma' => $qr,
                'tArjetaATC' => $tarjeta,
                'tArjetaATCconfirma' => $tarjeta,
                'dIfVendedor' => $diferencia,
                'dIfVendedorConfirma' => $diferencia,
            ],
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
            'eFectivoBolivianosConfirma' => 'required|numeric',
            'cLientesConfirma' => 'required|numeric',
            'pOrCobrarPersonalConfirma' => 'required|numeric',
            'tArjetaATCconfirma' => 'required|numeric',
            'dIfVendedorConfirma' => 'required|numeric',
        ]);

        $clienteId = session('cliente_id');
        $sucursalId = session('cliente_sucursal_id');
        $operadorId = session('operador_id');
        $fechaId = $request->IdFecha;

        // Validar que haya datos
        if ($request->vEntasConfirma == 0) {
            return redirect()->back()->with('error', 'No tiene datos...!');
        }

        // Obtener la fecha
        $fecha = Fecha::find($fechaId);
        $fechaStr = $fecha ? $fecha->Fecha : null;

        DB::connection('mysql_gestion_comercial_alimentos')->beginTransaction();

        try {
            // 1. Obtener o crear liquidación
            $liquidacion = LiquidacionVendedor::where('iDcliente', $clienteId)
                ->where('iDsucursal', $sucursalId)
                ->where('iDoperadorVendedor', $operadorId)
                ->where('IdFecha', $fechaId)
                ->where('ActivoInactivo', 0)
                ->first();

            if (!$liquidacion) {
                // Obtener valores del sistema para guardar
                $totalVentas = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas')
                    ->where('IdCliente', $clienteId)
                    ->where('IdClienteSucursal', $sucursalId)
                    ->where('IdOperadorIngresa', $operadorId)
                    ->where('LiquidadoVendedor', 0)
                    ->where('IdEstado', 1)
                    ->whereDate('FechaVenta', $fechaStr)
                    ->sum('ImporteVenta');

                $pagos = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas as v')
                    ->join('impuestos_ventas_liquidacion as l', 'v.IdVentas', '=', 'l.IdVentas')
                    ->join('impuestos_ventas_liquidacion_concepto as c', function($join) use ($clienteId) {
                        $join->on('l.IdCuenta', '=', 'c.IdCuenta')
                             ->where('c.IdCliente', '=', $clienteId);
                    })
                    ->where('v.IdCliente', $clienteId)
                    ->where('v.IdClienteSucursal', $sucursalId)
                    ->where('v.IdOperadorIngresa', $operadorId)
                    ->where('v.LiquidadoVendedor', 0)
                    ->where('v.IdEstado', 1)
                    ->where('v.ActivoInactivo', 1)
                    ->whereDate('v.FechaVenta', $fechaStr)
                    ->select('c.Concepto', DB::raw('SUM(l.Bolivianos) as total'))
                    ->groupBy('c.Concepto')
                    ->get();

                $efectivo = 0;
                $clientes = 0;
                $qr = 0;
                $tarjeta = 0;

                foreach ($pagos as $pago) {
                    switch ($pago->Concepto) {
                        case 'Efectivo': $efectivo = round($pago->total, 2); break;
                        case 'Cliente': $clientes = round($pago->total, 2); break;
                        case 'QR': $qr = round($pago->total, 2); break;
                        case 'Tarjeta': $tarjeta = round($pago->total, 2); break;
                    }
                }

                $sumaPagos = $efectivo + $clientes + $qr + $tarjeta;
                $diferencia = round($totalVentas - $sumaPagos, 2);

                $liquidacion = LiquidacionVendedor::create([
                    'IdFecha' => $fechaId,
                    'IdDiario' => 0,
                    'vEntas' => round($totalVentas, 2),
                    'vEntasConfirma' => $request->vEntasConfirma,
                    'eFectivoBolivianos' => $efectivo,
                    'eFectivoBolivianosConfirma' => $request->eFectivoBolivianosConfirma,
                    'cLientes' => $clientes,
                    'cLientesConfirma' => $request->cLientesConfirma,
                    'pOrCobrarPersonal' => $qr,
                    'pOrCobrarPersonalConfirma' => $request->pOrCobrarPersonalConfirma,
                    'tArjetaATC' => $tarjeta,
                    'tArjetaATCconfirma' => $request->tArjetaATCconfirma,
                    'dIfVendedor' => $diferencia,
                    'dIfVendedorConfirma' => $request->dIfVendedorConfirma,
                    'ActivoInactivo' => 1,
                    'LiquidadoSupervisor' => 0,
                    'iDcliente' => $clienteId,
                    'iDsucursal' => $sucursalId,
                    'iDtipoOperadorVentas' => 1,
                    'iDoperadorVendedor' => $operadorId,
                ]);
            } else {
                // Actualizar solo los campos de confirmación
                $liquidacion->update([
                    'vEntasConfirma' => $request->vEntasConfirma,
                    'eFectivoBolivianosConfirma' => $request->eFectivoBolivianosConfirma,
                    'cLientesConfirma' => $request->cLientesConfirma,
                    'pOrCobrarPersonalConfirma' => $request->pOrCobrarPersonalConfirma,
                    'tArjetaATCconfirma' => $request->tArjetaATCconfirma,
                    'dIfVendedorConfirma' => $request->dIfVendedorConfirma,
                    'ActivoInactivo' => 1,
                ]);
            }

            // 2. Crear diario contable (TipoDiario = 1 para ventas)
            $maxNumeroDiario = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->where('IdCliente', $clienteId)
                ->where('IdSucursal', $sucursalId)
                ->max('NumeroDiario');
            
            $numeroDiario = ($maxNumeroDiario ?? 0) + 1;

            $diarioId = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario')
                ->insertGetId([
                    'IdFecha' => $fechaId,
                    'IdTipoDiario' => 1,
                    'NumeroDiario' => $numeroDiario,
                    'IdCliente' => $clienteId,
                    'IdSucursal' => $sucursalId,
                    'Contabilizado' => 1,
                    'IdOperadorIngreso' => $operadorId,
                    'FechaIngreso' => now(),
                    'IdoperadorEdita' => $operadorId,
                    'FechaEdita' => now(),
                ]);

            // 3. Actualizar liquidación con IdDiario
            $liquidacion->update(['IdDiario' => $diarioId]);

            // 4. Actualizar ventas con LiquidadoVendedor = IdDiario
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('IdCliente', $clienteId)
                ->where('IdClienteSucursal', $sucursalId)
                ->where('IdOperadorIngresa', $operadorId)
                ->where('LiquidadoVendedor', 0)
                ->where('IdEstado', 1)
                ->whereDate('FechaVenta', $fechaStr)
                ->update(['LiquidadoVendedor' => $diarioId]);

            // 5. Obtener cuentas de parámetros
            $cuentas = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_parametros_cuentas')
                ->where('IdCliente', $clienteId)
                ->first();

            // 6. Obtener UFV actual
            $ufvActual = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_factorcambio')
                ->where('IdFecha', $fechaId)
                ->where('IdMoneda', 3)
                ->value('FactorCambio') ?? 1;

            // 7. Identificador del operador
            $identificadorOperador = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('todos_operador')
                ->where('IdOperador', $operadorId)
                ->value('IdIdentificador') ?? 1;

            $totalVentasConfirmado = $request->vEntasConfirma;
            $totalVentasUFV = round($totalVentasConfirmado / $ufvActual, 2);

            // 8. Insertar asientos contables
            
            // Ventas Facturadas (Haber)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $cuentas->VentasFacturadas ?? 1,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'H',
                    'MontoBolivianos' => $totalVentasConfirmado,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $totalVentasUFV,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // IVA Débito Fiscal (Control DF IVA) - Debe
            $ivaDF = round($totalVentasConfirmado * 0.13, 2);
            $ivaDFUFV = round($ivaDF / $ufvActual, 2);
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $cuentas->ControlDFIVA ?? 1,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'D',
                    'MontoBolivianos' => $ivaDF,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $ivaDFUFV,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Débito Fiscal IVA (Haber)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $cuentas->DebitoFiscalIVA ?? 1,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'H',
                    'MontoBolivianos' => $ivaDF,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $ivaDF,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // IT Pagados (Debe)
            $itPagados = round($totalVentasConfirmado * 0.03, 2);
            $itPagadosUFV = round($itPagados / $ufvActual, 2);
            
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $cuentas->ITPagados ?? 1,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'D',
                    'MontoBolivianos' => $itPagados,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $itPagadosUFV,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // IT por Pagar (Haber)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => $cuentas->ITxPagar ?? 1,
                    'Glosa' => 'Liquidacion Ventas',
                    'D_H' => 'H',
                    'MontoBolivianos' => $itPagados,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $itPagados,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Costo de Ventas
            $totalCostoVentas = 0;
            $ventasIds = DB::connection('mysql_gestion_comercial_alimentos')
                ->table('impuestos_ventas')
                ->where('LiquidadoVendedor', $diarioId)
                ->pluck('IdVentas');

            foreach ($ventasIds as $ventaId) {
                $costo = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('inventario_propiamente')
                    ->where('IdTipoDeOperacion', 2)
                    ->where('IdDocumento', $ventaId)
                    ->sum('Bolivianos');
                $totalCostoVentas += $costo;
            }

            $totalCostoVentasUFV = round($totalCostoVentas / $ufvActual, 2);

            // Costo de Ventas (Debe)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => 2618, // Cuenta Costo de Ventas
                    'Glosa' => 'Liquidacion Ventas - Costo',
                    'D_H' => 'D',
                    'MontoBolivianos' => $totalCostoVentas,
                    'TipoCambio' => $ufvActual,
                    'MontoOtraMoneda' => $totalCostoVentasUFV,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // Inventario (Haber)
            DB::connection('mysql_gestion_comercial_alimentos')
                ->table('conta_diario_propiamente')
                ->insert([
                    'IdDiario' => $diarioId,
                    'IdCuenta' => 2694, // Cuenta Inventario
                    'Glosa' => 'Liquidacion Ventas - Costo',
                    'D_H' => 'H',
                    'MontoBolivianos' => $totalCostoVentas,
                    'TipoCambio' => 1,
                    'MontoOtraMoneda' => $totalCostoVentas,
                    'IdIdentificador' => $identificadorOperador,
                    'IdActividad' => 1,
                    'Deducible' => 'D',
                ]);

            // 9. Insertar asientos por método de pago
            
            // Efectivo
            if ($request->eFectivoBolivianosConfirma != 0) {
                $cuentaEfectivo = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion_concepto')
                    ->where('Concepto', 'Efectivo')
                    ->where('IdCliente', $clienteId)
                    ->value('IdCuenta');
                
                if ($cuentaEfectivo) {
                    DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('conta_diario_propiamente')
                        ->insert([
                            'IdDiario' => $diarioId,
                            'IdCuenta' => $cuentaEfectivo,
                            'Glosa' => 'Liquidacion Ventas',
                            'D_H' => 'D',
                            'MontoBolivianos' => $request->eFectivoBolivianosConfirma,
                            'TipoCambio' => 1,
                            'MontoOtraMoneda' => $request->eFectivoBolivianosConfirma,
                            'IdIdentificador' => $identificadorOperador,
                            'IdActividad' => 1,
                            'Deducible' => 'D',
                        ]);
                }
            }

            // Clientes (cuentas por cobrar)
            if ($request->cLientesConfirma != 0) {
                $cuentaCliente = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion_concepto')
                    ->where('Concepto', 'Cliente')
                    ->where('IdCliente', $clienteId)
                    ->value('IdCuenta');
                
                if ($cuentaCliente) {
                    // Insertar uno por cada cliente
                    $clientesPagos = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas as v')
                        ->join('impuestos_ventas_liquidacion as l', 'v.IdVentas', '=', 'l.IdVentas')
                        ->where('v.LiquidadoVendedor', $diarioId)
                        ->where('v.IdCliente', $clienteId)
                        ->where('v.IdClienteSucursal', $sucursalId)
                        ->where('v.IdOperadorIngresa', $operadorId)
                        ->where('v.IdEstado', 1)
                        ->where('l.IdCuenta', $cuentaCliente)
                        ->select('l.IdIdentificador', 'l.Bolivianos', 'v.NumeroFactura')
                        ->get();

                    foreach ($clientesPagos as $pagoCliente) {
                        DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('conta_diario_propiamente')
                            ->insert([
                                'IdDiario' => $diarioId,
                                'IdCuenta' => $cuentaCliente,
                                'Glosa' => "Liquidacion Ventas Fact.{$pagoCliente->NumeroFactura}",
                                'D_H' => 'D',
                                'MontoBolivianos' => $pagoCliente->Bolivianos,
                                'TipoCambio' => 1,
                                'MontoOtraMoneda' => $pagoCliente->Bolivianos,
                                'IdIdentificador' => $pagoCliente->IdIdentificador,
                                'IdActividad' => 1,
                                'Deducible' => 'D',
                            ]);
                    }
                }
            }

            // QR (Por Cobrar Personal)
            if ($request->pOrCobrarPersonalConfirma != 0) {
                $cuentaQR = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion_concepto')
                    ->where('Concepto', 'QR')
                    ->where('IdCliente', $clienteId)
                    ->value('IdCuenta');
                
                if ($cuentaQR) {
                    $qrPagos = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas as v')
                        ->join('impuestos_ventas_liquidacion as l', 'v.IdVentas', '=', 'l.IdVentas')
                        ->where('v.LiquidadoVendedor', $diarioId)
                        ->where('v.IdCliente', $clienteId)
                        ->where('v.IdClienteSucursal', $sucursalId)
                        ->where('v.IdOperadorIngresa', $operadorId)
                        ->where('v.IdEstado', 1)
                        ->where('l.IdCuenta', $cuentaQR)
                        ->select('l.IdIdentificador', 'l.Bolivianos', 'v.NumeroFactura')
                        ->get();

                    foreach ($qrPagos as $pagoQR) {
                        DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('conta_diario_propiamente')
                            ->insert([
                                'IdDiario' => $diarioId,
                                'IdCuenta' => $cuentaQR,
                                'Glosa' => "Liquidacion Ventas Fact.{$pagoQR->NumeroFactura}",
                                'D_H' => 'D',
                                'MontoBolivianos' => $pagoQR->Bolivianos,
                                'TipoCambio' => 1,
                                'MontoOtraMoneda' => $pagoQR->Bolivianos,
                                'IdIdentificador' => $pagoQR->IdIdentificador,
                                'IdActividad' => 1,
                                'Deducible' => 'D',
                            ]);
                    }
                }
            }

            // Tarjeta ATC
            if ($request->tArjetaATCconfirma != 0) {
                $cuentaTarjeta = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('impuestos_ventas_liquidacion_concepto')
                    ->where('Concepto', 'Tarjeta')
                    ->where('IdCliente', $clienteId)
                    ->value('IdCuenta');
                
                if ($cuentaTarjeta) {
                    $tarjetaPagos = DB::connection('mysql_gestion_comercial_alimentos')
                        ->table('impuestos_ventas as v')
                        ->join('impuestos_ventas_liquidacion as l', 'v.IdVentas', '=', 'l.IdVentas')
                        ->where('v.LiquidadoVendedor', $diarioId)
                        ->where('v.IdCliente', $clienteId)
                        ->where('v.IdClienteSucursal', $sucursalId)
                        ->where('v.IdOperadorIngresa', $operadorId)
                        ->where('v.IdEstado', 1)
                        ->where('l.IdCuenta', $cuentaTarjeta)
                        ->select('l.IdIdentificador', 'l.Bolivianos', 'v.NumeroFactura')
                        ->get();

                    foreach ($tarjetaPagos as $pagoTarjeta) {
                        DB::connection('mysql_gestion_comercial_alimentos')
                            ->table('conta_diario_propiamente')
                            ->insert([
                                'IdDiario' => $diarioId,
                                'IdCuenta' => $cuentaTarjeta,
                                'Glosa' => "Liquidacion Ventas Fact.{$pagoTarjeta->NumeroFactura}",
                                'D_H' => 'D',
                                'MontoBolivianos' => $pagoTarjeta->Bolivianos,
                                'TipoCambio' => 1,
                                'MontoOtraMoneda' => $pagoTarjeta->Bolivianos,
                                'IdIdentificador' => $pagoTarjeta->IdIdentificador,
                                'IdActividad' => 1,
                                'Deducible' => 'D',
                            ]);
                    }
                }
            }

            // 10. Diferencia (Cuenta Personal Vendedor)
            if ($request->dIfVendedorConfirma != 0) {
                $diferenciaAbs = abs($request->dIfVendedorConfirma);
                $dH = $request->dIfVendedorConfirma > 0 ? 'D' : 'H';
                
                DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('conta_diario_propiamente')
                    ->insert([
                        'IdDiario' => $diarioId,
                        'IdCuenta' => $cuentas->CuentaPersonalVendedor ?? 1,
                        'Glosa' => 'Liquidacion Ventas',
                        'D_H' => $dH,
                        'MontoBolivianos' => $diferenciaAbs,
                        'TipoCambio' => 1,
                        'MontoOtraMoneda' => $diferenciaAbs,
                        'IdIdentificador' => $identificadorOperador,
                        'IdActividad' => 1,
                        'Deducible' => 'D',
                    ]);
            }

            DB::connection('mysql_gestion_comercial_alimentos')->commit();

            return redirect()->route('liquidacion-vendedor.index')
                ->with('success', 'Liquidación realizada correctamente. Diario N° ' . $numeroDiario);

        } catch (\Exception $e) {
            DB::connection('mysql_gestion_comercial_alimentos')->rollBack();
            Log::error('Error al liquidar: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al liquidar: ' . $e->getMessage());
        }
    }
}