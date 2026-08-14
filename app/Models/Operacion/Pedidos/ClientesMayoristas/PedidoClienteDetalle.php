<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;

class PedidoClienteDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_detalle';
    protected $primaryKey = 'IdPedidoClienteDetalle';
    public $timestamps = false;

    protected $fillable = [
        'IdPedidoCliente',
        'IdContenedor',
        'IdProducto',
        'Cantidad',
        'OrdenContenedor',
    ];

    protected $casts = [
        'Cantidad' => 'decimal:2',
    ];

    // ==================== RELACIONES ====================
    
    public function pedido()
    {
        return $this->belongsTo(PedidoCliente::class, 'IdPedidoCliente', 'IdPedidoCliente');
    }

    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'IdContenedor', 'IdContenedor');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }
}