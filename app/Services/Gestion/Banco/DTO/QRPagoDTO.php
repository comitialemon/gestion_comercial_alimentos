<?php

namespace App\Services\Gestion\Banco\DTO;

class QRPagoDTO
{
    public function __construct(
        public readonly string $qrId,
        public readonly ?string $transactionId,
        public readonly ?string $paymentDate,
        public readonly ?string $paymentTime,
        public readonly ?string $currency,
        public readonly ?float $amount,
        public readonly ?string $senderBankCode,
        public readonly ?string $senderName,
        public readonly ?string $senderDocumentId,
        public readonly ?string $senderAccount,
        public readonly ?string $description,
        public readonly ?string $branchCode,
        public readonly array $respuestaCompleta,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            qrId: $data['qrId'] ?? '',
            transactionId: $data['transactionId'] ?? null,
            paymentDate: $data['paymentDate'] ?? null,
            paymentTime: $data['paymentTime'] ?? null,
            currency: $data['currency'] ?? null,
            amount: isset($data['amount']) ? (float) $data['amount'] : null,
            senderBankCode: $data['senderBankCode'] ?? null,
            senderName: $data['senderName'] ?? null,
            senderDocumentId: $data['senderDocumentId'] ?? null,
            senderAccount: $data['senderAccount'] ?? null,
            description: $data['description'] ?? null,
            branchCode: $data['branchCode'] ?? null,
            respuestaCompleta: $data,
        );
    }

    public function toArray(): array
    {
        return $this->respuestaCompleta;
    }
}