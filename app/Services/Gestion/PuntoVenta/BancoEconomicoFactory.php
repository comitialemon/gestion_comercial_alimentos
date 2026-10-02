<?php

namespace App\Services\Gestion\PuntoVenta;

use App\Models\Gestion\Impuestos\BancoCredencial;

class BancoEconomicoFactory
{
    /**
     * Obtener Service para un cliente (usa la credencial activa)
     */
    public static function paraCliente(
        int $idCliente,
        string $ambiente = 'PRODUCCION',
        string $codigoBanco = 'BECO'
    ): BancoEconomicoService {
        $credencial = BancoCredencial::porCliente($idCliente)
            ->porBanco($codigoBanco)
            ->where('Ambiente', $ambiente)
            ->where('ActivoInactivo', 1)
            ->firstOrFail();

        return new BancoEconomicoService($credencial);
    }

    /**
     * Obtener Service desde una credencial específica
     */
    public static function desdeCredencial(BancoCredencial $credencial): BancoEconomicoService
    {
        return new BancoEconomicoService($credencial);
    }

    /**
     * Obtener credencial activa de un cliente
     */
    public static function obtenerCredencialActiva(
        int $idCliente,
        string $ambiente = 'PRODUCCION',
        string $codigoBanco = 'BECO'
    ): ?BancoCredencial {
        return BancoCredencial::porCliente($idCliente)
            ->porBanco($codigoBanco)
            ->where('Ambiente', $ambiente)
            ->where('ActivoInactivo', 1)
            ->first();
    }
}