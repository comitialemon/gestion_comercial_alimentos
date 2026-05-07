<?php

namespace App\Services\Facturacion;

use App\Models\Gestion\Contabilidad\MetodoPagoMapeo;
use App\Models\Gestion\Contabilidad\ContaCuenta;
use Illuminate\Support\Facades\Log;

class MetodoPagoMapeoService
{
    public function getCuentasContables()
    {
        return ContaCuenta::porContexto()
            ->orderBy('Cuenta')
            ->get(['IdCuenta as id', 'Cuenta as nombre', 'Descripcion as descripcion']);
    }

    public function getMapeosExistentes()
    {
        return MetodoPagoMapeo::porContexto()->get();
    }

    public function guardarMapeo($codigoSiat, $idContaCuenta, $activo, $idCliente, $idSucursal)
    {
        Log::info('Guardando mapeo', [
            'codigo_siat' => $codigoSiat,
            'idContaCuenta' => $idContaCuenta,
            'activo' => $activo,
            'idCliente' => $idCliente,
            'idSucursal' => $idSucursal
        ]);

        if ($activo) {
            MetodoPagoMapeo::updateOrCreate(
                [
                    'idCliente' => $idCliente,
                    'idSucursal' => $idSucursal,
                    'codigo_siat' => $codigoSiat,
                    'idContaCuenta' => $idContaCuenta,
                ],
                [
                    'activo' => 1,
                    'creado_por' => session('operador_id'),
                ]
            );
        } else {
            MetodoPagoMapeo::where('idCliente', $idCliente)
                ->where('idSucursal', $idSucursal)
                ->where('codigo_siat', $codigoSiat)
                ->where('idContaCuenta', $idContaCuenta)
                ->delete();
        }
    }
}