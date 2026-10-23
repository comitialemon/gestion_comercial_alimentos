<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class Envio extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_envios';
    protected $primaryKey = 'IdEnvio';
    public $timestamps = false;

    protected $fillable = [
        'IdPedidoCliente',
        'IdTransportadora',
        'NumeroGuia',
        'FechaEnvio',
        'FechaEntregaReal',
        'IdOperadorDespacha',
        'Observaciones',
    ];

    protected $casts = [
        'FechaEnvio' => 'datetime',
        'FechaEntregaReal' => 'datetime',
    ];

    // Relación: Un envío pertenece a un pedido
    public function pedido()
    {
        return $this->belongsTo(PedidoCliente::class, 'IdPedidoCliente', 'IdPedidoCliente');
    }

    // Relación: Un envío pertenece a una transportadora
    public function transportadora()
    {
        return $this->belongsTo(Transportadora::class, 'IdTransportadora', 'IdTransportadora');
    }
}