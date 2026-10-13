<?php

namespace App\Services\Gestion\Banco\Services;

use App\Models\Gestion\Banco\BancoCredencial;
use App\Services\Gestion\Banco\BancoQRException;
use App\Services\Gestion\Banco\Contracts\BancoQRInterface;
use App\Services\Gestion\Banco\DTO\QRGeneradoDTO;
use App\Services\Gestion\Banco\DTO\QREstadoDTO;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BancoEconomicoService implements BancoQRInterface
{
    protected BancoCredencial $credencial;
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected ?string $aesKey;
    protected string $cuentaCredito;
    protected string $branchCode;
    protected int $timeout;
    protected int $retry;
    protected int $retryDelay;
    protected int $tokenCacheTtl;

    // ============================================================
    // 🔥 URLs CORRECTAS (con 'r' después de 'q' según prueba en Tinker)
    // El PDF tiene un error tipográfico: dice 'qsimple' pero la API real es 'qrsimple'
    // ============================================================
    private const URL_AUTHENTICATE = '/api/authentication/authenticate';
    private const URL_ENCRYPT      = '/api/authentication/encrypt';
    private const URL_DECRYPT      = '/api/authentication/decrypt';
    private const URL_GENERATE_QR  = '/api/qrsimple/generateQR';
    private const URL_CANCEL_QR    = '/api/qrsimple/cancelQR';
    private const URL_STATUS_QR    = '/api/qrsimple/v2/statusQR';
    private const URL_PAID_QR      = '/api/qrsimple/v2/paidQR';

    public function __construct(BancoCredencial $credencial)
    {
        $this->credencial = $credencial;
        $this->baseUrl = rtrim($credencial->UrlBase, '/');
        $this->username = $credencial->Usuario;
        $this->password = $credencial->password_descifrado;
        $this->aesKey = $credencial->aes_key_descifrado;
        $this->cuentaCredito = $credencial->cuenta_credito_descifrado;
        $this->branchCode = $credencial->BranchCode ?? config('banco_economico.branch_code_default', 'S0001');
        $this->timeout = (int) ($credencial->Timeout ?: config('banco_economico.timeout_default', 20));
        $this->retry = (int) ($credencial->Reintentos ?: config('banco_economico.retry_default', 3));
        $this->retryDelay = (int) config('banco_economico.retry_delay_default', 1000);
        $this->tokenCacheTtl = (int) ($credencial->TokenCacheTtl ?: config('banco_economico.token_cache_ttl_default', 1500));

        // ✅ VALIDACIÓN: Verificar que las credenciales se desencriptaron correctamente
        if (empty($this->password)) {
            throw new BancoQRException(
                "No se pudo desencriptar el password de la credencial #{$credencial->IdCredencial}. " .
                "Verifica que el APP_KEY no haya cambiado y que el dato en BD esté correctamente encriptado.",
                'CONFIGURACION'
            );
        }

        if (empty($this->aesKey)) {
            throw new BancoQRException(
                "No se pudo desencriptar el AES Key de la credencial #{$credencial->IdCredencial}.",
                'CONFIGURACION'
            );
        }

        if (empty($this->cuentaCredito)) {
            throw new BancoQRException(
                "No se pudo desencriptar la Cuenta de Crédito de la credencial #{$credencial->IdCredencial}.",
                'CONFIGURACION'
            );
        }
    }

    public function getIdCredencial(): int
    {
        return $this->credencial->IdCredencial;
    }

    protected function esModoFake(): bool
    {
        return (bool) config('banco_economico.fake_mode', false);
    }

    // =========================================================================
    // AUTENTICACIÓN
    // =========================================================================

    /**
     * Obtener token del banco (con caché de 25 minutos)
     * 
     * IMPORTANTE: El password debe ir ENCRIPTADO con el endpoint /encrypt del banco.
     */
    public function obtenerToken(bool $forzarRenovacion = false): string
    {
        if ($this->esModoFake()) {
            Log::info('🧪 MODO FAKE: token simulado');
            return 'FAKE_TOKEN_' . uniqid();
        }

        $cacheKey = "banco_economico_token_{$this->credencial->IdCredencial}";

        if ($forzarRenovacion) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $this->tokenCacheTtl, function () {
            Log::info('═══════════════════════════════════════════');
            Log::info('🔐 AUTENTICACIÓN BANCO ECONÓMICO');
            Log::info('═══════════════════════════════════════════');
            Log::info('🌐 URL: ' . "{$this->baseUrl}" . self::URL_AUTHENTICATE);
            Log::info('👤 Usuario: ' . $this->username);
            Log::info('🔑 Password (longitud original): ' . strlen($this->password));
            Log::info('───────────────────────────────────────────');

            // 🔥 PASO 1: Encriptar el password con el endpoint del banco
            $passwordEncriptado = $this->encriptar($this->password);

            Log::info('🔑 Password encriptado (longitud): ' . strlen($passwordEncriptado));
            Log::info('🔑 Password encriptado (preview): ' . substr($passwordEncriptado, 0, 30) . '...');
            Log::info('═══════════════════════════════════════════');

            // 🔥 PASO 2: Enviar el password encriptado
            $response = $this->httpClient()->post(
                "{$this->baseUrl}" . self::URL_AUTHENTICATE,
                [
                    'userName' => $this->username,
                    'password' => $passwordEncriptado,
                ]
            );

            Log::info('📊 HTTP Status: ' . $response->status());
            Log::info('📄 Body: ' . substr($response->body(), 0, 500));

            if (!$response->successful()) {
                $this->logError('Error HTTP al autenticar', $response);
                throw new BancoQRException(
                    'Error de conexión al autenticar con el banco (HTTP ' . $response->status() . '): ' . $response->body(),
                    'CONEXION',
                    null,
                    $response->status()
                );
            }

            $data = $response->json();

            if (!$data) {
                throw new BancoQRException(
                    'Respuesta del banco no es JSON válido: ' . $response->body(),
                    'NEGOCIO'
                );
            }

            if (($data['responseCode'] ?? -1) !== 0) {
                Log::error('❌ Error de autenticación del banco', [
                    'responseCode' => $data['responseCode'] ?? 'no definido',
                    'message' => $data['message'] ?? 'sin mensaje',
                ]);

                throw new BancoQRException(
                    'Error de autenticación: ' . ($data['message'] ?? 'Desconocido'),
                    'AUTENTICACION',
                    $data
                );
            }

            if (empty($data['token'])) {
                throw new BancoQRException(
                    'El banco no devolvió un token válido',
                    'AUTENTICACION',
                    $data
                );
            }

            Log::info('✅ TOKEN OBTENIDO');
            Log::info('🔑 Token (primeros 50): ' . substr($data['token'], 0, 50) . '...');

            return $data['token'];
        });
    }

    public function renovarToken(): string
    {
        return $this->obtenerToken(true);
    }

    // =========================================================================
    // ENCRIPTACIÓN
    // =========================================================================

    /**
     * Encriptar texto con el endpoint del banco
     */
    public function encriptar(string $texto): string
    {
        if ($this->esModoFake()) {
            return base64_encode($texto);
        }

        $response = $this->httpClient()->get(
            "{$this->baseUrl}" . self::URL_ENCRYPT,
            [
                'text' => $texto,
                'aesKey' => $this->aesKey,
            ]
        );

        if (!$response->successful()) {
            $this->logError('Error HTTP al encriptar', $response);
            throw new BancoQRException(
                'Error al encriptar',
                'CONEXION',
                null,
                $response->status()
            );
        }

        $resultado = trim($response->body());
        $resultado = trim($resultado, '"');
        $resultado = trim($resultado, "'");

        if (empty($resultado)) {
            throw new BancoQRException('Respuesta vacía al encriptar', 'NEGOCIO');
        }

        return $resultado;
    }

    /**
     * Desencriptar texto con el endpoint del banco
     */
    public function desencriptar(string $textoCifrado): string
    {
        if ($this->esModoFake()) {
            return base64_decode($textoCifrado);
        }

        $response = $this->httpClient()->get(
            "{$this->baseUrl}" . self::URL_DECRYPT,
            [
                'text' => $textoCifrado,
                'aesKey' => $this->aesKey,
            ]
        );

        if (!$response->successful()) {
            $this->logError('Error HTTP al desencriptar', $response);
            throw new BancoQRException(
                'Error al desencriptar',
                'CONEXION',
                null,
                $response->status()
            );
        }

        $resultado = trim($response->body());
        $resultado = trim($resultado, '"');
        $resultado = trim($resultado, "'");

        return $resultado;
    }

    // =========================================================================
    // GENERACIÓN DE QR
    // =========================================================================

    /**
     * Generar un QR para cobrar
     * 
     * URL correcta: /api/qrsimple/generateQR (con 'r' después de 'q')
     * El banco devuelve: qrId (con I mayúscula) y qrImage
     */
    public function generarQR(
        string $transactionId,
        float $monto,
        string $descripcion,
        string $moneda = 'BOB',
        ?string $fechaVencimiento = null,
        ?string $branchCode = null,
        bool $singleUse = true,
        bool $modifyAmount = false
    ): QRGeneradoDTO {
        if ($this->esModoFake()) {
            return $this->respuestaFakeQR($transactionId, $monto, $descripcion);
        }

        $token = $this->obtenerToken();
        $cuentaCifrada = $this->encriptar($this->cuentaCredito);

        $payload = [
            'transactionId' => $transactionId,
            'accountCredit' => $cuentaCifrada,
            'currency' => $moneda,
            'amount' => round($monto, 2),
            'description' => $descripcion,
            'dueDate' => $fechaVencimiento ?? date('Y-m-d'),
            'singleUse' => $singleUse,
            'modifyAmount' => $modifyAmount,
            'branchCode' => $branchCode ?? $this->branchCode,
        ];

        $url = "{$this->baseUrl}" . self::URL_GENERATE_QR;

        Log::info('📤 Generando QR BECO', [
            'IdCredencial' => $this->credencial->IdCredencial,
            'url' => $url,
            'transactionId' => $transactionId,
            'monto' => $monto,
            'payload' => $payload,
        ]);

        $response = $this->httpClient()
            ->withToken($token)
            ->post($url, $payload);

        if ($response->status() === 401) {
            Log::warning('Token BECO expirado, renovando');
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->post($url, $payload);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al generar QR BECO', $response);
            throw new BancoQRException(
                'Error de conexión al generar QR (HTTP ' . $response->status() . ')',
                'CONEXION',
                null,
                $response->status()
            );
        }

        $data = $response->json();

        Log::info('📥 Respuesta generar QR BECO', [
            'responseCode' => $data['responseCode'] ?? 'no definido',
            'message' => $data['message'] ?? 'sin mensaje',
            'tiene_qrId' => !empty($data['qrId']),
            'tiene_qrld' => !empty($data['qrld']),
            'tiene_qrImage' => !empty($data['qrImage']),
        ]);

        if (($data['responseCode'] ?? -1) !== 0) {
            throw new BancoQRException(
                'Error generando QR: ' . ($data['message'] ?? 'Desconocido'),
                'NEGOCIO',
                $data
            );
        }

        // 🔥 IMPORTANTE: El banco devuelve 'qrId' (con I mayúscula), NO 'qrld' (con L)
        // El PDF tiene otro error tipográfico aquí
        $qrId = $data['qrId'] ?? $data['qrld'] ?? null;
        $qrImage = $data['qrImage'] ?? null;

        if (empty($qrId) || empty($qrImage)) {
            Log::error('❌ QR inválido del banco', ['data' => $data]);
            throw new BancoQRException(
                'QR inválido del banco: falta qrId o qrImage',
                'NEGOCIO',
                $data
            );
        }

        Log::info('✅ QR BECO generado', ['qrId' => $qrId]);

        return new QRGeneradoDTO(
            qrId: $qrId,
            qrImage: $qrImage,
            transactionId: $transactionId,
            respuestaCompleta: $data,
        );
    }

    // =========================================================================
    // CONSULTA DE ESTADO
    // =========================================================================

    /**
     * Consultar el estado de un QR
     * 
     * URL correcta: /api/qrsimple/v2/statusQR/{qrId}
     */
    public function consultarEstadoQR(string $qrId): QREstadoDTO
    {
        if ($this->esModoFake()) {
            return $this->respuestaFakeEstado($qrId);
        }

        $token = $this->obtenerToken();

        $url = "{$this->baseUrl}" . self::URL_STATUS_QR . "/{$qrId}";

        Log::info('🔍 Consultando estado QR BECO', ['url' => $url, 'qrId' => $qrId]);

        $response = $this->httpClient()
            ->withToken($token)
            ->get($url);

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->get($url);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al consultar estado BECO', $response);
            throw new BancoQRException(
                'Error al consultar estado',
                'CONEXION',
                null,
                $response->status()
            );
        }

        return QREstadoDTO::fromArray($response->json());
    }

    // =========================================================================
    // ANULACIÓN
    // =========================================================================

    /**
     * Anular un QR
     * 
     * URL correcta: /api/qrsimple/cancelQR/{qrId}
     */
    public function anularQR(string $qrId): array
    {
        if ($this->esModoFake()) {
            return ['responseCode' => 0, 'message' => ''];
        }

        $token = $this->obtenerToken();

        $url = "{$this->baseUrl}" . self::URL_CANCEL_QR . "/{$qrId}";

        Log::info('🚫 Anulando QR BECO', ['url' => $url, 'qrId' => $qrId]);

        $response = $this->httpClient()
            ->withToken($token)
            ->delete($url);

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->delete($url);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al anular QR BECO', $response);
            throw new BancoQRException(
                'Error al anular QR',
                'CONEXION',
                null,
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['responseCode'] ?? -1) !== 0) {
            throw new BancoQRException(
                'Error anulando QR: ' . ($data['message'] ?? 'Desconocido'),
                'NEGOCIO',
                $data
            );
        }

        Log::info('✅ QR BECO anulado', ['qrId' => $qrId]);

        return $data;
    }

    // =========================================================================
    // QR PAGADOS
    // =========================================================================

    /**
     * Listar QRs pagados en una fecha
     * 
     * URL correcta: /api/qrsimple/v2/paidQR/{fecha} (formato yyyyMMdd)
     */
    public function listarQRPagados(string $fecha): array
    {
        if ($this->esModoFake()) {
            return [];
        }

        $token = $this->obtenerToken();
        $fechaFormato = date('Ymd', strtotime($fecha));

        $url = "{$this->baseUrl}" . self::URL_PAID_QR . "/{$fechaFormato}";

        Log::info('📋 Listando QR pagados BECO', ['url' => $url, 'fecha' => $fechaFormato]);

        $response = $this->httpClient(30)
            ->withToken($token)
            ->get($url);

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient(30)
                ->withToken($token)
                ->get($url);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al listar QR pagados BECO', $response);
            throw new BancoQRException(
                'Error al listar QRs',
                'CONEXION',
                null,
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['responseCode'] ?? -1) !== 0) {
            throw new BancoQRException(
                'Error listando QRs: ' . ($data['message'] ?? 'Desconocido'),
                'NEGOCIO',
                $data
            );
        }

        return $data['paymentList'] ?? [];
    }

    // =========================================================================
    // MODO FAKE
    // =========================================================================

    protected function respuestaFakeQR(string $transactionId, float $monto, string $descripcion): QRGeneradoDTO
    {
        $qrIdFalso = 'FAKE-BECO-' . strtoupper(uniqid());
        Log::info('🧪 FAKE: QR BECO generado', ['qrId' => $qrIdFalso, 'monto' => $monto]);

        return new QRGeneradoDTO(
            qrId: $qrIdFalso,
            qrImage: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            transactionId: $transactionId,
            respuestaCompleta: ['fake' => true, 'qrId' => $qrIdFalso, 'responseCode' => 0],
        );
    }

    protected function respuestaFakeEstado(string $qrId): QREstadoDTO
    {
        if (rand(1, 100) <= 50) {
            return QREstadoDTO::fromArray([
                'statusQRCode' => 1,
                'payment' => [[
                    'qrId' => $qrId,
                    'transactionId' => 'FAKE-TX-' . uniqid(),
                    'paymentDate' => date('Y-m-d\TH:i:s'),
                    'paymentTime' => date('H:i:s'),
                    'currency' => 'BOB',
                    'amount' => 1.00,
                    'senderBankCode' => '1016',
                    'senderName' => 'CLIENTE PRUEBA BECO',
                    'senderDocumentId' => '0',
                    'senderAccount' => '******1234',
                    'description' => 'Pago de prueba fake',
                ]],
            ]);
        }

        return QREstadoDTO::fromArray(['statusQRCode' => 0, 'payment' => []]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    protected function httpClient(int $timeout = null)
    {
        return Http::timeout($timeout ?? $this->timeout)
            ->retry($this->retry, $this->retryDelay, function ($exception, $request) {
                return $exception instanceof ConnectionException;
            })
            ->acceptJson()
            ->asJson();
    }

    protected function logError(string $mensaje, Response $response): void
    {
        Log::error($mensaje, [
            'IdCredencial' => $this->credencial->IdCredencial,
            'status' => $response->status(),
            'body' => substr($response->body(), 0, 500),
        ]);
    }
}