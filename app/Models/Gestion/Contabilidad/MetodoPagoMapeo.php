<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class MetodoPagoMapeo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'metodo_pago_mapeo';
    protected $primaryKey = 'idMapeoMetodoPago';
    public $timestamps = false;

    protected $fillable = [
        'idMetodoPago',
        'codigo_siat',
        'idContaCuenta',
        'idCliente',
        'idSucursal',
        'activo',
        'creado_por',
        'creado_en',
    ];

    public function scopePorContexto($query)
    {
        return $query->where('idCliente', session('cliente_id'))
                     ->where('idSucursal', session('cliente_sucursal_id'));
    }
}