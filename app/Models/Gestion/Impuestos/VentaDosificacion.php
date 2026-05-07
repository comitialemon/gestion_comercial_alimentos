<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;

class VentaDosificacion extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_ventas_dosificacion';
    protected $primaryKey = 'IdDosificacion';
    public $timestamps = false;

    protected $fillable = [
        'IdTipoDeDosificacion',
        'Autorizacion',
        'LlaveDosificacion',
        'Actividad',
        'PrimeraLeyenda',
        'SegundaLeyenda',
        'FechaActivacion',
        'FechaLimiteEmision',
        'ActivoInactivo',
        'IdCliente',
        'IdSucursal',
    ];

    public function scopeActiva($query, $clienteId, $sucursalId, $tipoDosificacion)
    {
        return $query->where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdTipoDeDosificacion', $tipoDosificacion)
            ->where('ActivoInactivo', 0)
            ->where('FechaActivacion', '<=', now())
            ->where('FechaLimiteEmision', '>=', now());
    }
}