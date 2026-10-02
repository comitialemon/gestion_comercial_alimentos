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
        // 🔥 Token expira en 30 min → usamos 25 min (1500s) para renovar antes
        $this->tokenCacheTtl = (int) ($credencial->TokenCacheTtl ?: config('banco_economico.token_cache_ttl_default', 1500));
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
     */
    public function obtenerToken(bool $forzarRenovacion = false): string
    {
        // 🔥 MODO FAKE
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
            Log::info('🌐 URL: ' . "{$this->baseUrl}/api/authentication/authenticate");
            Log::info('👤 Usuario: ' . $this->username);
            Log::info('🔑 Password (longitud): ' . strlen($this->password));
            Log::info('───────────────────────────────────────────');

            $esBase64 = preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $this->password);
            Log::info('✔️ ¿Es Base64 válido?: ' . ($esBase64 ? 'SÍ' : 'NO ❌'));
            Log::info('═══════════════════════════════════════════');

            $response = $this->httpClient()
                ->post("{$this->baseUrl}/api/authentication/authenticate", [
                    'userName' => $this->username,
                    'password' => $this->password,
                ]);

            Log::info('📊 HTTP Status: ' . $response->status());
            Log::info('📄 Body: ' . $response->body());

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

    public function encriptar(string $texto): string
    {
        if ($this->esModoFake()) {
            return base64_encode($texto);
        }

        $response = $this->httpClient()
            ->get("{$this->baseUrl}/api/authentication/encrypt", [
                'text' => $texto,
                'aesKey' => $this->aesKey,
            ]);

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

    public function desencriptar(string $textoCifrado): string
    {
        if ($this->esModoFake()) {
            return base64_decode($textoCifrado);
        }

        $response = $this->httpClient()
            ->get("{$this->baseUrl}/api/authentication/decrypt", [
                'text' => $textoCifrado,
                'aesKey' => $this->aesKey,
            ]);

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

        Log::info('📤 Generando QR', [
            'IdCredencial' => $this->credencial->IdCredencial,
            'transactionId' => $transactionId,
            'monto' => $monto,
        ]);

        // 🔥 ENDPOINT CORRECTO: /api/qrsimple/ (CON R)
        $response = $this->httpClient()
            ->withToken($token)
            ->post("{$this->baseUrl}/api/qrsimple/generateQR", $payload);

        if ($response->status() === 401) {
            Log::warning('Token expirado, renovando');
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->post("{$this->baseUrl}/api/qrsimple/generateQR", $payload);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al generar QR', $response);
            throw new BancoQRException(
                'Error de conexión al generar QR',
                'CONEXION',
                null,
                $response->status()
            );
        }

        $data = $response->json();

        if (($data['responseCode'] ?? -1) !== 0) {
            throw new BancoQRException(
                'Error generando QR: ' . ($data['message'] ?? 'Desconocido'),
                'NEGOCIO',
                $data
            );
        }

        if (empty($data['qrId']) || empty($data['qrImage'])) {
            throw new BancoQRException('QR inválido del banco', 'NEGOCIO', $data);
        }

        Log::info('✅ QR generado', ['qrId' => $data['qrId']]);

        return new QRGeneradoDTO(
            qrId: $data['qrId'],
            qrImage: $data['qrImage'],
            transactionId: $transactionId,
            respuestaCompleta: $data,
        );
    }

    // =========================================================================
    // CONSULTA DE ESTADO
    // =========================================================================

    public function consultarEstadoQR(string $qrId): QREstadoDTO
    {
        if ($this->esModoFake()) {
            return $this->respuestaFakeEstado($qrId);
        }

        $token = $this->obtenerToken();

        // 🔥 ENDPOINT CORRECTO: /api/qrsimple/ (CON R)
        $response = $this->httpClient()
            ->withToken($token)
            ->get("{$this->baseUrl}/api/qrsimple/v2/statusQR/{$qrId}");

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->get("{$this->baseUrl}/api/qrsimple/v2/statusQR/{$qrId}");
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al consultar estado', $response);
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

    public function anularQR(string $qrId): array
    {
        if ($this->esModoFake()) {
            return ['responseCode' => 0, 'message' => ''];
        }

        $token = $this->obtenerToken();

        // 🔥 ENDPOINT CORRECTO: /api/qrsimple/ (CON R)
        $response = $this->httpClient()
            ->withToken($token)
            ->asJson()
            ->delete("{$this->baseUrl}/api/qrsimple/cancelQR", [
                'qrId' => $qrId,
            ]);

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->asJson()
                ->delete("{$this->baseUrl}/api/qrsimple/cancelQR", [
                    'qrId' => $qrId,
                ]);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al anular QR', $response);
            throw new BancoQRException('Error al anular QR', 'CONEXION', null, $response->status());
        }

        $data = $response->json();

        if (($data['responseCode'] ?? -1) !== 0) {
            throw new BancoQRException(
                'Error anulando QR: ' . ($data['message'] ?? 'Desconocido'),
                'NEGOCIO',
                $data
            );
        }

        return $data;
    }

    // =========================================================================
    // QR PAGADOS
    // =========================================================================

    public function listarQRPagados(string $fecha): array
    {
        if ($this->esModoFake()) {
            return [];
        }

        $token = $this->obtenerToken();
        $fechaFormato = date('Ymd', strtotime($fecha));

        // 🔥 ENDPOINT CORRECTO: /api/qrsimple/ (CON R)
        $response = $this->httpClient(30)
            ->withToken($token)
            ->get("{$this->baseUrl}/api/qrsimple/v2/paidQR/{$fechaFormato}");

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient(30)
                ->withToken($token)
                ->get("{$this->baseUrl}/api/qrsimple/v2/paidQR/{$fechaFormato}");
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al listar QR pagados', $response);
            throw new BancoQRException('Error al listar QRs', 'CONEXION', null, $response->status());
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
        $qrIdFalso = 'FAKE-' . strtoupper(uniqid());
        Log::info('🧪 FAKE: QR generado', ['qrId' => $qrIdFalso, 'monto' => $monto]);

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
                    'senderName' => 'CLIENTE PRUEBA',
                    'senderDocumentId' => '0',
                    'senderAccount' => '******1234',
                    'description' => 'Pago de prueba',
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