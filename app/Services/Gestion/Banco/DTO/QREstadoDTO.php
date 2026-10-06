<?php

namespace App\Services\Gestion\Banco\DTO;

class QREstadoDTO
{
    public const ESTADO_ACTIVO = 0;
    public const ESTADO_PAGADO = 1;
    public const ESTADO_ANULADO = 9;

    public function __construct(
        public readonly int $statusQRCode,
        public readonly array $pagos,
        public readonly array $respuestaCompleta,
    ) {}

    public static function fromArray(array $data): self
    {
        $pagos = [];
        if (isset($data['payment']) && is_array($data['payment'])) {
            foreach ($data['payment'] as $pago) {
                $pagos[] = QRPagoDTO::fromArray($pago);
            }
        }

        // Acepta múltiples variantes
        $statusQRCode = $data['statusQrCode']
                     ?? $data['statusQRCode']
                     ?? $data['orderState']
                     ?? 0;

        // Mapeo si viene orderState (BGAN)
        if (isset($data['orderState'])) {
            $statusQRCode = match ((int) $data['orderState']) {
                1 => self::ESTADO_ACTIVO,
                2 => self::ESTADO_PAGADO,
                3, 5 => self::ESTADO_ANULADO,
                default => self::ESTADO_ACTIVO,
            };
        }

        return new self(
            statusQRCode: (int) $statusQRCode,
            pagos: $pagos,
            respuestaCompleta: $data,
        );
    }

    public function estaActivo(): bool
    {
        return $this->statusQRCode === self::ESTADO_ACTIVO;
    }

    public function estaPagado(): bool
    {
        return $this->statusQRCode === self::ESTADO_PAGADO;
    }

    public function estaAnulado(): bool
    {
        return $this->statusQRCode === self::ESTADO_ANULADO;
    }

    public function getPrimerPago(): ?QRPagoDTO
    {
        return $this->pagos[0] ?? null;
    }

    public function getNombreEstado(): string
    {
        return match ($this->statusQRCode) {
            self::ESTADO_ACTIVO => 'ACTIVO',
            self::ESTADO_PAGADO => 'PAGADO',
            self::ESTADO_ANULADO => 'ANULADO',
            default => 'DESCONOCIDO',
        };
    }
}