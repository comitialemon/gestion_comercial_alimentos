<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\Cliente;

class ProductoAprobacionConfig extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'producto_aprobacion_config';
    protected $primaryKey = 'IdProductoAprobacionConfig';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdOperador',
        'ActivoInactivo',
        'IdOperadorIngresa',
        'FechaIngreso',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 0);
    }
}