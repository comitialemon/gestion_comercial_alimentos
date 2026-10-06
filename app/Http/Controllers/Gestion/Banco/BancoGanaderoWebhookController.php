<?php

namespace App\Http\Controllers\Gestion\Banco;

use App\Http\Controllers\Controller;
use App\Models\Gestion\Banco\BancoCredencial;
use App\Models\Gestion\Banco\BancoPagoQR;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente;
use App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoClientePago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BancoGanaderoWebhookController extends Controller
{
    // ============================================================
    // LOGIN - Autenticación desde el banco
    // ============================================================
    public function login(Request $request)
    {
        Log::info('🔐 Webhook Login - BANCO GANADERO', [
            'ip' => $request->ip(),
            'userName' => $request->input('userName'),
            'has_password' => !empty($request->input('password')),
        ]);

        $request->validate([
            'userName' => 'required|string',
            'password' => 'required|string',
        ]);

        $credencial = BancoCredencial::where('WebhookUser', $request->userName)
            ->where('CodigoBanco', 'BGAN')
            ->where('ActivoInactivo', 1)
            ->first();

        if (!$credencial) {
            Log::warning('❌ Webhook Login - Usuario no encontrado', [
                'userName' => $request->userName,
            ]);
            return response()->json([
                'result' => 'COD001',
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        $expectedPassword = $credencial->webhook_password_descifrado;

        if (empty($expectedPassword) || !hash_equals($expectedPassword, $request->password)) {
            Log::warning('❌ Webhook Login - Password incorrecto', [
                'userName' => $request->userName,
                'IdCredencial' => $credencial->IdCredencial,
            ]);
            return response()->json([
                'result' => 'COD001',
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        $token = $credencial->webhook_token_descifrado;

        if (empty($token)) {
            Log::error('❌ Webhook Login - Token no configurado', [
                'IdCredencial' => $credencial->IdCredencial,
            ]);
            return response()->json([
                'result' => 'COD003',
                'message' => 'Error de configuración',
            ], 500);
        }

        Log::info('✅ Webhook Login - Exitoso', [
            'IdCredencial' => $credencial->IdCredencial,
            'userName' => $request->userName,
        ]);

        return response()->json([
            'result' => 'COD000',
            'message' => 'Autenticación exitosa',
            'token' => $token,
        ]);
    }

    // ============================================================
    // PAYMENTS - Notificación de pago desde el banco
    // ============================================================
    public function payments(Request $request)
    {
        Log::info('💰 Webhook Payments - BANCO GANADERO', [
            'ip' => $request->ip(),
            'body' => $request->all(),
        ]);

        // 1. VALIDAR TOKEN
        $authHeader = $request->header('Authorization');
        $tokenRecibido = str_replace('Bearer ', '', $authHeader ?? '');

        if (empty($tokenRecibido)) {
            Log::warning('❌ Webhook Payments - Token requerido');
            return response()->json([
                'result' => 'COD001',
                'message' => 'Token requerido',
            ], 401);
        }

        $credencial = BancoCredencial::where('CodigoBanco', 'BGAN')
            ->where('ActivoInactivo', 1)
            ->whereNotNull('WebhookTokenCifrado')
            ->get()
            ->first(function ($cred) use ($tokenRecibido) {
                $storedToken = $cred->webhook_token_descifrado;
                return !empty($storedToken) && hash_equals($storedToken, $tokenRecibido);
            });

        if (!$credencial) {
            Log::warning('❌ Webhook Payments - Token inválido', [
                'token_preview' => substr($tokenRecibido, 0, 20) . '...',
            ]);
            return response()->json([
                'result' => 'COD001',
                'message' => 'Token inválido',
            ], 401);
        }

        // 2. VALIDAR DATOS
        $request->validate([
            'qrId' => 'required|string',
            'transactionId' => 'required',
            'payDate' => 'required|string',
        ]);

        $qrId = $request->input('qrId');
        $transactionId = $request->input('transactionId');

        // 3. BUSCAR PAGO
        $pago = PedidoClientePago::where('QrId', $qrId)->first()
            ?? BancoPagoQR::where('QrId', $qrId)->first();

        if (!$pago) {
            Log::warning('⚠️ Webhook Payments - QR no encontrado', ['qrId' => $qrId]);
            return response()->json([
                'result' => 'COD000',
                'message' => 'QR no encontrado',
            ]);
        }

        // 4. PROCESAR PAGO (IDEMPOTENTE)
        DB::beginTransaction();

        try {
            if ($pago->Estado === 'PAGADO') {
                DB::rollBack();
                Log::info('ℹ️ Webhook Payments - Pago ya registrado', ['qrId' => $qrId]);
                return response()->json([
                    'result' => 'COD000',
                    'message' => 'Pago ya registrado',
                ]);
            }

            $pago->update([
                'Estado' => 'PAGADO',
                'StatusQrCodeBanco' => 2,
                'MontoPagado' => $pago->Monto,
                'FechaPago' => now(),
                'DatosPago' => array_merge(
                    $request->all(),
                    ['webhook_received_at' => now()->toIso8601String()]
                ),
                'FechaUltimaActualizacion' => now(),
            ]);

            // 5. SI ES PEDIDO → ASIGNAR NÚMERO
            if ($pago instanceof PedidoClientePago) {
                $pedido = PedidoCliente::find($pago->IdPedidoCliente);

                if ($pedido && $pedido->EstadoPedido === 'Esperando Pago') {
                    $maxNumero = PedidoCliente::where('IdCliente', $pedido->IdCliente)
                        ->where('IdSucursal', $pedido->IdSucursal)
                        ->where('NumeroPedido', '!=', '0')
                        ->whereNotNull('NumeroPedido')
                        ->max(DB::raw('CAST(NumeroPedido AS UNSIGNED)')) ?? 0;

                    $nuevoNumero = $maxNumero + 1;
                    $numeroFormateado = str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);

                    $pedido->update([
                        'EstadoPedido' => 'Pendiente',
                        'ActivoInactivo' => 1,
                        'NumeroPedido' => $numeroFormateado,
                        'IdOperadorActualiza' => $credencial->IdOperadorIngresa ?? 1,
                        'FechaActualiza' => now(),
                    ]);

                    Log::info('✅ Pedido actualizado desde webhook', [
                        'IdPedidoCliente' => $pedido->IdPedidoCliente,
                        'NumeroPedido' => $numeroFormateado,
                    ]);
                }
            }

            DB::commit();

            Log::info('✅ Webhook Payments - Pago procesado', [
                'qrId' => $qrId,
                'transactionId' => $transactionId,
                'IdCredencial' => $credencial->IdCredencial,
            ]);

            return response()->json([
                'result' => 'COD000',
                'message' => 'Pago registrado correctamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Error procesando webhook', [
                'qrId' => $qrId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'result' => 'COD003',
                'message' => 'Error al procesar',
            ], 500);
        }
    }
}