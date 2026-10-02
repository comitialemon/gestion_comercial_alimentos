<?php

namespace App\Http\Controllers\PuntoVenta;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Impuestos\BancoCredencial;
use App\Models\Gestion\Impuestos\BancoPagoQR;
use App\Services\Gestion\PuntoVenta\BancoEconomicoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BancoQRPruebaController extends Controller
{
    /**
     * Vista principal de pruebas
     */
    public function index()
    {
        $idCliente = session('cliente_id');

        // Credenciales activas del cliente
        $credenciales = BancoCredencial::porCliente($idCliente)
            ->where('ActivoInactivo', 1)
            ->get()
            ->map(fn($c) => [
                'IdCredencial' => $c->IdCredencial,
                'Alias' => $c->Alias ?? $c->CodigoBanco,
                'Ambiente' => $c->Ambiente,
            ]);

        // Últimos 20 QRs generados
        $ultimosQRs = BancoPagoQR::where('IdCliente', $idCliente)
            ->orderBy('IdPagosQr', 'DESC')
            ->limit(20)
            ->get()
            ->map(fn($qr) => [
                'IdPagosQr' => $qr->IdPagosQr,
                'QrId' => $qr->QrId,
                'TransactionId' => $qr->TransactionId,
                'Monto' => (float) $qr->Monto,
                'Descripcion' => $qr->Descripcion,
                'Estado' => $qr->Estado,
                'FechaCreacion' => $qr->FechaCreacion?->format('Y-m-d H:i:s'),
                'FechaPago' => $qr->FechaPago?->format('Y-m-d H:i:s'),
                'MontoPagado' => $qr->MontoPagado ? (float) $qr->MontoPagado : null,
            ]);

        return Inertia::render('PuntoVenta/BancoQRPrueba/Index', [
            'credenciales' => $credenciales,
            'ultimosQRs' => $ultimosQRs,
        ]);
    }

    /**
     * Generar un QR de prueba
     */
    public function generar(Request $request)
    {
        $request->validate([
            'IdCredencial' => 'required|integer|exists:impuestos_banco_credenciales,IdCredencial',
            'Monto' => 'required|numeric|min:0.01',
            'Descripcion' => 'nullable|string|max:200',
        ]);

        try {
            $idCliente = session('cliente_id');
            $sucursalId = session('cliente_sucursal_id');
            $operadorId = session('operador_id');

            // Obtener credencial (validando que sea del cliente)
            $credencial = BancoCredencial::porCliente($idCliente)
                ->findOrFail($request->IdCredencial);

            // Generar TransactionId único
            $transactionId = sprintf(
                'TEST-S%04d-%s-%s',
                $sucursalId,
                date('YmdHis'),
                strtoupper(substr(uniqid(), -4))
            );

            // Instanciar Service y generar QR
            $service = new BancoEconomicoService($credencial);

            $qr = $service->generarQR(
                $transactionId,
                (float) $request->Monto,
                $request->Descripcion ?? 'QR de prueba - ' . now()->format('d/m/Y H:i'),
                'BOB',
                null,
                $credencial->BranchCode
            );

            // Guardar en BD
            $pagoQR = BancoPagoQR::create([
                'IdVentas' => 0, // No es venta real, es prueba
                'IdCliente' => $idCliente,
                'IdClienteSucursal' => $sucursalId,
                'IdCredencial' => $credencial->IdCredencial,
                'IdOperadorIngresa' => $operadorId,
                'CodigoBanco' => $credencial->CodigoBanco,
                'QrId' => $qr->qrId,
                'TransactionId' => $transactionId,
                'BranchCode' => $credencial->BranchCode,
                'Moneda' => 'BOB',
                'Monto' => $request->Monto,
                'SingleUse' => 1,
                'Descripcion' => $request->Descripcion,
                'FechaVencimiento' => date('Y-m-d'),
                'Estado' => 'ACTIVO',
                'FechaCreacion' => now(),
                'DatosGeneracion' => $qr->respuestaCompleta,
            ]);

            Log::info('✅ QR de prueba generado', [
                'IdPagosQr' => $pagoQR->IdPagosQr,
                'QrId' => $qr->qrId,
                'Monto' => $request->Monto,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'QR generado correctamente',
                'qr' => [
                    'IdPagosQr' => $pagoQR->IdPagosQr,
                    'QrId' => $qr->qrId,
                    'TransactionId' => $transactionId,
                    'QrImage' => $qr->qrImage,
                    'Monto' => (float) $request->Monto,
                    'Descripcion' => $request->Descripcion,
                    'Estado' => 'ACTIVO',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Error generando QR de prueba', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar estado de un QR
     */
    public function consultarEstado($qrId)
    {
        try {
            $idCliente = session('cliente_id');

            $pagoQR = BancoPagoQR::where('IdCliente', $idCliente)
                ->where('QrId', $qrId)
                ->firstOrFail();

            // Si ya está pagado, devolver directo
            if ($pagoQR->Estado === 'PAGADO') {
                return response()->json([
                    'success' => true,
                    'estado' => 'PAGADO',
                    'datos_pago' => $pagoQR->DatosPago,
                    'monto_pagado' => $pagoQR->MontoPagado,
                    'fecha_pago' => $pagoQR->FechaPago,
                ]);
            }

            // Si está anulado, devolver directo
            if ($pagoQR->Estado === 'ANULADO') {
                return response()->json([
                    'success' => true,
                    'estado' => 'ANULADO',
                ]);
            }

            // Consultar al banco
            $service = new BancoEconomicoService($pagoQR->credencial);
            $estadoBanco = $service->consultarEstadoQR($qrId);

            if ($estadoBanco->estaPagado()) {
                $primerPago = $estadoBanco->getPrimerPago();

                $pagoQR->update([
                    'Estado' => 'PAGADO',
                    'StatusQRCodeBanco' => 1,
                    'FechaPago' => now(),
                    'MontoPagado' => $primerPago?->amount ?? $pagoQR->Monto,
                    'DatosPago' => $estadoBanco->respuestaCompleta['payment'] ?? [],
                    'FechaUltimaActualizacion' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'estado' => 'PAGADO',
                    'datos_pago' => $estadoBanco->respuestaCompleta['payment'] ?? [],
                ]);
            }

            if ($estadoBanco->estaAnulado()) {
                $pagoQR->update([
                    'Estado' => 'ANULADO',
                    'StatusQRCodeBanco' => 9,
                    'FechaAnulacion' => now(),
                    'FechaUltimaActualizacion' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'estado' => 'ANULADO',
                ]);
            }

            return response()->json([
                'success' => true,
                'estado' => 'ACTIVO',
                'statusQRCode' => $estadoBanco->statusQRCode,
            ]);

        } catch (\Exception $e) {
            Log::error('Error consultando estado QR', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Anular un QR
     */
    public function anular($qrId)
    {
        try {
            $idCliente = session('cliente_id');

            $pagoQR = BancoPagoQR::where('IdCliente', $idCliente)
                ->where('QrId', $qrId)
                ->firstOrFail();

            if ($pagoQR->Estado === 'PAGADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede anular un QR ya pagado',
                ], 422);
            }

            $service = new BancoEconomicoService($pagoQR->credencial);
            $service->anularQR($qrId);

            $pagoQR->update([
                'Estado' => 'ANULADO',
                'FechaAnulacion' => now(),
                'FechaUltimaActualizacion' => now(),
                'IdOperadorActualiza' => session('operador_id'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'QR anulado correctamente',
            ]);

        } catch (\Exception $e) {
            Log::error('Error anulando QR', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Listar QRs pagados en una fecha
     */
    public function listarPagados(Request $request)
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'IdCredencial' => 'required|integer|exists:impuestos_banco_credenciales,IdCredencial',
        ]);

        try {
            $idCliente = session('cliente_id');

            $credencial = BancoCredencial::porCliente($idCliente)
                ->findOrFail($request->IdCredencial);

            $service = new BancoEconomicoService($credencial);
            $pagos = $service->listarQRPagados($request->fecha);

            return response()->json([
                'success' => true,
                'pagos' => $pagos,
                'total' => count($pagos),
            ]);

        } catch (\Exception $e) {
            Log::error('Error listando QRs pagados', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}