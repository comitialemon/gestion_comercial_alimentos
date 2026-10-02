<?php

namespace App\Services\Gestion\Banco\DTO;

class QRGeneradoDTO
{
    public function __construct(
        public readonly string $qrId,
        public readonly string $qrImage,
        public readonly string $transactionId,
        public readonly array $respuestaCompleta,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            qrId: $data['qrId'],
            qrImage: $data['qrImage'],
            transactionId: $data['transactionId'] ?? '',
            respuestaCompleta: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'qrId' => $this->qrId,
            'qrImage' => $this->qrImage,
            'transactionId' => $this->transactionId,
        ];
    }
}