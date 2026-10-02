<?php

namespace App\Services\Gestion\Banco;

use App\Models\Gestion\Banco\BancoCredencial;
use App\Services\Gestion\Banco\Contracts\BancoQRInterface;
use App\Services\Gestion\Banco\Services\BancoEconomicoService;
use App\Services\Gestion\Banco\Services\BancoGanaderoService;

class BancoFactory
{
    /**
     * Crear el Service correcto según el banco de la credencial
     *
     * @throws \Exception Si el código de banco no está soportado
     */
    public static function desdeCredencial(BancoCredencial $credencial): BancoQRInterface
    {
        $codigoBanco = $credencial->CodigoBanco;

        // Validación: si el código está vacío
        if (empty($codigoBanco)) {
            throw new \Exception('El código de banco está vacío. No se puede instanciar el Service.');
        }

        return match ($codigoBanco) {
            'BECO' => new BancoEconomicoService($credencial),
            'BGAN' => new BancoGanaderoService($credencial),
            default => throw new \Exception("Banco no soportado: '{$codigoBanco}'. Solo se aceptan BECO o BGAN."),
        };
    }

    /**
     * Obtener el Service del cliente según su banco activo
     */
    public static function paraCliente(
        int $idCliente,
        string $ambiente = 'PRODUCCION',
        string $codigoBanco = 'BECO'
    ): BancoQRInterface {
        $credencial = BancoCredencial::porCliente($idCliente)
            ->porBanco($codigoBanco)
            ->where('Ambiente', $ambiente)
            ->where('ActivoInactivo', 1)
            ->firstOrFail();

        return self::desdeCredencial($credencial);
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