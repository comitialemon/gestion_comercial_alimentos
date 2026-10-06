<?php

namespace App\Services\Gestion\Banco\Services;

use App\Models\Gestion\Banco\BancoCredencial;
use App\Services\Gestion\Banco\BancoQRException;
use App\Services\Gestion\Banco\Contracts\BancoQRInterface;
use App\Services\Gestion\Banco\DTO\QRGeneradoDTO;
use App\Services\Gestion\Banco\DTO\QREstadoDTO;
use App\Services\Gestion\Banco\DTO\QRPagoDTO;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BancoGanaderoService implements BancoQRInterface
{
    // ============================================================
    // CONSTANTES
    // ============================================================
    private const MAX_ACCOUNT_REFERENCE = 20;
    private const MAX_GLOSS = 60;
    private const MAX_AMOUNT_SMN_MULTIPLIER = 5;
    private const SMN_BOLIVIA = 2362;
    private const MAX_EXPIRATION_YEARS = 1;

    protected BancoCredencial $credencial;
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected ?string $apiKey;
    protected string $accountReference;
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
        $this->apiKey = $credencial->api_key_descifrado;
        $this->accountReference = $credencial->cuenta_credito_descifrado;
        $this->timeout = (int) ($credencial->Timeout ?: 20);
        $this->retry = (int) ($credencial->Reintentos ?: 3);
        $this->retryDelay = 1000;
        $this->tokenCacheTtl = (int) ($credencial->TokenCacheTtl ?: 1500);
    }

    public function getIdCredencial(): int
    {
        return $this->credencial->IdCredencial;
    }

    protected function esModoFake(): bool
    {
        return (bool) config('banco_ganadero.fake_mode', false);
    }

    // =========================================================================
    // AUTENTICACIÓN
    // =========================================================================
    public function obtenerToken(bool $forzarRenovacion = false): string
    {
        if ($this->esModoFake()) {
            return 'FAKE_TOKEN_GANADERO_' . uniqid();
        }

        $cacheKey = "banco_ganadero_token_{$this->credencial->IdCredencial}";

        if ($forzarRenovacion) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $this->tokenCacheTtl, function () {
            Log::info('🔐 Autenticando BANCO GANADERO', [
                'Usuario' => $this->username,
                'Url' => "{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/access",
            ]);

            $response = $this->httpClient()
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/access", [
                    'userName' => $this->username,
                    'password' => $this->password,
                ]);

            if (!$response->successful()) {
                $this->logError('Error HTTP al autenticar con Banco Ganadero', $response);
                throw new BancoQRException(
                    'Error de conexión al autenticar (HTTP ' . $response->status() . '): ' . $response->body(),
                    'CONEXION',
                    null,
                    $response->status()
                );
            }

            $data = $response->json();

            if (($data['result'] ?? '') !== 'COD000') {
                throw new BancoQRException(
                    'Error de autenticación [' . ($data['result'] ?? 'N/A') . ']: ' . ($data['message'] ?? 'Desconocido'),
                    'AUTENTICACION',
                    $data
                );
            }

            if (empty($data['token'])) {
                throw new BancoQRException('Token no recibido del banco', 'AUTENTICACION', $data);
            }

            Log::info('✅ Token Ganadero obtenido');

            return $data['token'];
        });
    }

    public function renovarToken(): string
    {
        return $this->obtenerToken(true);
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

        $this->validarParametros($monto, $descripcion, $fechaVencimiento, $moneda);

        $token = $this->obtenerToken();

        $fechaVenc = $fechaVencimiento
            ? date('dmY', strtotime($fechaVencimiento))
            : date('dmY', strtotime('+1 day'));

        $glosaFinal = mb_strlen($descripcion) > self::MAX_GLOSS
            ? mb_substr($descripcion, 0, self::MAX_GLOSS - 3) . '...'
            : $descripcion;

        $payload = [
            'accountReference' => $this->accountReference,
            'amount' => round($monto, 2),
            'currency' => $moneda,
            'gloss' => $glosaFinal,
            'expirationDate' => $fechaVenc,
            'singleUse' => $singleUse ? 1 : 0,
            'userName' => $this->username,
            'apiKey' => $this->apiKey,
        ];

        Log::info('📤 Generando QR Ganadero', ['transactionId' => $transactionId, 'monto' => $monto]);

        $response = $this->httpClient()
            ->withToken($token)
            ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/collections", $payload);

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/collections", $payload);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al generar QR Ganadero', $response);
            throw new BancoQRException('Error de conexión al generar QR', 'CONEXION', null, $response->status());
        }

        $data = $response->json();

        if (($data['result'] ?? '') !== 'COD000') {
            throw new BancoQRException(
                'Error generando QR [' . ($data['result'] ?? 'N/A') . ']: ' . ($data['message'] ?? 'Desconocido'),
                'NEGOCIO',
                $data
            );
        }

        if (empty($data['qrId']) || empty($data['qrImage'])) {
            throw new BancoQRException('QR inválido: falta qrId o qrImage', 'NEGOCIO', $data);
        }

        Log::info('✅ QR Ganadero generado', ['qrId' => $data['qrId']]);

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

        $response = $this->httpClient()
            ->withToken($token)
            ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/status", [
                'qrId' => $qrId,
                'userName' => $this->username,
                'apiKey' => $this->apiKey,
            ]);

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/status", [
                    'qrId' => $qrId,
                    'userName' => $this->username,
                    'apiKey' => $this->apiKey,
                ]);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al consultar estado Ganadero', $response);
            throw new BancoQRException('Error al consultar estado', 'CONEXION', null, $response->status());
        }

        $data = $response->json();
        $orderState = (int) ($data['orderState'] ?? 0);

        $statusQRCode = match ($orderState) {
            1 => 0,   // Registrado → Activo
            2 => 1,   // Pagado
            3 => 9,   // Anulado
            5 => 9,   // Tipo incorrecto
            default => 0,
        };

        $pagos = [];
        if ($statusQRCode === 1) {
            $pagos[] = QRPagoDTO::fromArray([
                'qrId' => $qrId,
                'transactionId' => $data['transactionNumber'] ?? null,
                'paymentDate' => $data['payday'] ?? null,
                'paymentTime' => $data['payHour'] ?? null,
                'currency' => 'BOB',
                'amount' => $data['amount'] ?? null,
                'senderName' => $data['payerName'] ?? null,
                'senderAccount' => $data['payerAccount'] ?? null,
            ]);
        }

        return new QREstadoDTO(
            statusQRCode: $statusQRCode,
            pagos: $pagos,
            respuestaCompleta: $data,
        );
    }

    // =========================================================================
    // ANULACIÓN
    // =========================================================================
    public function anularQR(string $qrId): array
    {
        if ($this->esModoFake()) {
            return ['result' => 'COD000', 'message' => ''];
        }

        $token = $this->obtenerToken();

        $response = $this->httpClient()
            ->withToken($token)
            ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/cancellations", [
                'qrId' => $qrId,
                'username' => $this->username,
                'apiKey' => $this->apiKey,
            ]);

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient()
                ->withToken($token)
                ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/cancellations", [
                    'qrId' => $qrId,
                    'username' => $this->username,
                    'apiKey' => $this->apiKey,
                ]);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al anular QR Ganadero', $response);
            throw new BancoQRException('Error al anular QR', 'CONEXION', null, $response->status());
        }

        $data = $response->json();

        if (($data['result'] ?? '') !== 'COD000') {
            throw new BancoQRException(
                'Error anulando QR: ' . ($data['message'] ?? 'Desconocido'),
                'NEGOCIO',
                $data
            );
        }

        return $data;
    }

    // =========================================================================
    // LISTAR PAGADOS
    // =========================================================================
    public function listarQRPagados(string $fecha): array
    {
        if ($this->esModoFake()) {
            return [];
        }

        $token = $this->obtenerToken();
        $fechaFormato = date('dmY', strtotime($fecha));

        $response = $this->httpClient(30)
            ->withToken($token)
            ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/transactions", [
                'username' => $this->username,
                'startDate' => $fechaFormato,
                'endDate' => $fechaFormato,
                'apiKey' => $this->apiKey,
            ]);

        if ($response->status() === 401) {
            $token = $this->renovarToken();
            $response = $this->httpClient(30)
                ->withToken($token)
                ->post("{$this->baseUrl}/ws-servicio-codigo-qr-empresas/service/v1/qrcode/transactions", [
                    'username' => $this->username,
                    'startDate' => $fechaFormato,
                    'endDate' => $fechaFormato,
                    'apiKey' => $this->apiKey,
                ]);
        }

        if (!$response->successful()) {
            $this->logError('Error HTTP al listar pagos Ganadero', $response);
            throw new BancoQRException('Error al listar pagos', 'CONEXION', null, $response->status());
        }

        $data = $response->json();

        if (($data['result'] ?? '') !== 'COD000') {
            throw new BancoQRException(
                'Error listando pagos: ' . ($data['message'] ?? 'Desconocido'),
                'NEGOCIO',
                $data
            );
        }

        return $data['orders'] ?? [];
    }

    // =========================================================================
    // VALIDACIONES
    // =========================================================================
    protected function validarParametros(
        float $monto,
        string $descripcion,
        ?string $fechaVencimiento,
        string $moneda
    ): void {
        if (strlen($this->accountReference) > self::MAX_ACCOUNT_REFERENCE) {
            throw new BancoQRException(
                'La referencia de cuenta excede ' . self::MAX_ACCOUNT_REFERENCE . ' caracteres',
                'NEGOCIO'
            );
        }

        if (empty($this->accountReference)) {
            throw new BancoQRException('La referencia de cuenta está vacía', 'NEGOCIO');
        }

        $maxMonto = self::SMN_BOLIVIA * self::MAX_AMOUNT_SMN_MULTIPLIER;
        if ($monto <= 0) {
            throw new BancoQRException('El monto debe ser mayor a 0', 'NEGOCIO');
        }
        if ($monto > $maxMonto) {
            throw new BancoQRException(
                "El monto Bs. {$monto} excede el máximo permitido (Bs. {$maxMonto})",
                'NEGOCIO'
            );
        }

        if (!in_array($moneda, ['BOB', 'USD'])) {
            throw new BancoQRException('Moneda inválida. Solo BOB o USD', 'NEGOCIO');
        }

        if (empty($descripcion)) {
            throw new BancoQRException('La glosa es obligatoria', 'NEGOCIO');
        }

        if ($fechaVencimiento) {
            $fechaVencTs = strtotime($fechaVencimiento);
            $hoyTs = strtotime(date('Y-m-d'));

            if ($fechaVencTs < $hoyTs) {
                throw new BancoQRException('La fecha de vencimiento no puede ser anterior a hoy', 'NEGOCIO');
            }

            $maxFechaTs = strtotime('+' . self::MAX_EXPIRATION_YEARS . ' year');
            if ($fechaVencTs > $maxFechaTs) {
                throw new BancoQRException(
                    'La fecha de vencimiento no puede exceder ' . self::MAX_EXPIRATION_YEARS . ' año',
                    'NEGOCIO'
                );
            }
        }
    }

    // =========================================================================
    // MODO FAKE
    // =========================================================================
    protected function respuestaFakeQR(string $transactionId, float $monto, string $descripcion): QRGeneradoDTO
    {
        $qrIdFalso = 'FAKE-GAN-' . strtoupper(uniqid());

        return new QRGeneradoDTO(
            qrId: $qrIdFalso,
            qrImage: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            transactionId: $transactionId,
            respuestaCompleta: ['fake' => true, 'qrId' => $qrIdFalso, 'result' => 'COD000'],
        );
    }

    protected function respuestaFakeEstado(string $qrId): QREstadoDTO
    {
        if (rand(1, 100) <= 50) {
            return new QREstadoDTO(
                statusQRCode: 1,
                pagos: [QRPagoDTO::fromArray([
                    'qrId' => $qrId,
                    'transactionId' => 'FAKE-GAN-TX-' . uniqid(),
                    'paymentDate' => date('dmY'),
                    'paymentTime' => date('H:i'),
                    'currency' => 'BOB',
                    'amount' => 1.00,
                    'senderName' => 'CLIENTE PRUEBA GANADERO',
                    'senderAccount' => '******1234',
                ])],
                respuestaCompleta: ['fake' => true],
            );
        }

        return new QREstadoDTO(0, [], ['fake' => true]);
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