<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class HistorialPedido extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_historial';
    protected $primaryKey = 'IdHistorial';
    public $timestamps = false;

    protected $fillable = [
        'IdPedidoCliente',
        'EstadoAnterior',
        'EstadoNuevo',
        'IdOperador',
        'Observacion',
        'FechaCambio',
    ];

    protected $casts = [
        'FechaCambio' => 'datetime',
    ];

    // Relación: Un historial pertenece a un pedido
    public function pedido()
    {
        return $this->belongsTo(PedidoCliente::class, 'IdPedidoCliente', 'IdPedidoCliente');
    }
}