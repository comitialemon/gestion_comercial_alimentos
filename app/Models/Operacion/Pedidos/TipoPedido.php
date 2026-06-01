<?php

namespace App\Models\Operacion\Pedidos;

use Illuminate\Database\Eloquent\Model;

class TipoPedido extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_ventas_pedidos_tipopedido';
    protected $primaryKey = 'IdTipoPedido';
    public $timestamps = false;

    protected $fillable = [
        'Detalle',
        'IdCliente',
    ];

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }
}