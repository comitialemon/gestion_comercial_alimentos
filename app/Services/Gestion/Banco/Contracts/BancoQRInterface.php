<?php

namespace App\Services\Gestion\Banco\Contracts;

use App\Services\Gestion\Banco\DTO\QRGeneradoDTO;
use App\Services\Gestion\Banco\DTO\QREstadoDTO;

interface BancoQRInterface
{
    /**
     * Obtener token del banco (con caché)
     */
    public function obtenerToken(bool $forzarRenovacion = false): string;

    /**
     * Forzar renovación del token
     */
    public function renovarToken(): string;

    /**
     * Generar un QR para cobrar
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
    ): QRGeneradoDTO;

    /**
     * Anular un QR
     */
    public function anularQR(string $qrId): array;

    /**
     * Consultar el estado de un QR
     */
    public function consultarEstadoQR(string $qrId): QREstadoDTO;

    /**
     * Listar QRs pagados en una fecha
     */
    public function listarQRPagados(string $fecha): array;

    /**
     * Obtener el ID de la credencial usada
     */
    public function getIdCredencial(): int;
}