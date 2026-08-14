<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;

class ContenedorDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_contenedor_detalle';
    protected $primaryKey = 'IdContenedorDetalle';
    public $timestamps = false;

    protected $fillable = [
        'IdContenedor',
        'IdProducto',
        'Cantidad',
    ];

    protected $casts = [
        'Cantidad' => 'decimal:2',
    ];

    // ==================== RELACIONES ====================
    
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
        return number_format($this->Cantidad, 2, ',', '.');
    }
}