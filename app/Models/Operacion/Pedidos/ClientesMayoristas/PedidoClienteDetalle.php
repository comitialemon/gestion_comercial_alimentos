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
        'Precio',           // ✅ NUEVO: Precio unitario al momento del pedido
        'OrdenContenedor',
    ];

    protected $casts = [
        'Cantidad' => 'decimal:2',
        'Precio' => 'decimal:2',    // ✅ NUEVO
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

    // ==================== ACCESORS ====================
    
    public function getCantidadFormateadaAttribute()
    {
        return number_format($this->Cantidad, 0, ',', '.');
    }

    public function getPrecioFormateadoAttribute()
    {
        return number_format($this->Precio, 2, ',', '.');
    }

    public function getSubtotalAttribute()
    {
        return $this->Cantidad * $this->Precio;
    }

    public function getSubtotalFormateadoAttribute()
    {
        return number_format($this->Cantidad * $this->Precio, 2, ',', '.');
    }

    // ==================== SCOPES ====================
    
    public function scopePorPedido($query, $idPedido)
    {
        return $query->where('IdPedidoCliente', $idPedido);
    }

    public function scopePorContenedor($query, $idContenedor)
    {
        return $query->where('IdContenedor', $idContenedor);
    }

    public function scopePorProducto($query, $idProducto)
    {
        return $query->where('IdProducto', $idProducto);
    }
}