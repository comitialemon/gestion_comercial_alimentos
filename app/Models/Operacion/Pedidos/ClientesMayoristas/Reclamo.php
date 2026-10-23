<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class Reclamo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_reclamos';
    protected $primaryKey = 'IdReclamo';
    public $timestamps = false;

    protected $fillable = [
        'IdPedidoCliente', 'IdCliente', 'IdOperador', 'Estado', 'Descripcion', 
        'RespuestaCalidad', // 🔥 NUEVO
        'FotoUrl', 'FechaCreacion', 'FechaActualizacion'
    ];

    protected $casts = [
        'FechaCreacion' => 'datetime',
        'FechaActualizacion' => 'datetime',
    ];

    public function pedido()
    {
        return $this->belongsTo(PedidoCliente::class, 'IdPedidoCliente', 'IdPedidoCliente');
    }

    public function detalles()
    {
        return $this->hasMany(ReclamoDetalle::class, 'IdReclamo', 'IdReclamo');
    }
}