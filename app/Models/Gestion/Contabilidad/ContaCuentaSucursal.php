<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\ClienteSucursal;

class ContaCuentaSucursal extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_cuenta_sucursales';
    protected $primaryKey = 'IdCuentaSucursales';
    public $timestamps = false;

    protected $fillable = [
        'IdCuenta',
        'Cuenta',
        'DinamicaCuenta',
        'IdCliente',
        'IdSucursal',
    ];

    public function cuenta()
    {
        return $this->belongsTo(ContaCuenta::class, 'IdCuenta', 'IdCuenta');
    }

    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }
}