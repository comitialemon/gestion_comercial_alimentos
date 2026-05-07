<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class ContaCuenta extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_cuenta';
    protected $primaryKey = 'IdCuenta';
    public $timestamps = false;

    protected $fillable = [
        'Cuenta',
        'Descripcion',
        'TipoDeCuenta',
        'IdMoneda',
        'ActivoFijo',
        'AbiertoCerrado',
        'IdCliente',
        'IdOperadorIngreso',
        'FechaIngreso',
        'IdOperadorEdita',
        'FechaActualiza',
    ];

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }
}