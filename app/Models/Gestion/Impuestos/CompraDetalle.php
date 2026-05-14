<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;

class CompraDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_compras_detalle';
    protected $primaryKey = 'IdComprasDetalle';
    public $timestamps = false;

    protected $fillable = [
        'IdCompras',
        'IdProducto',
        'Unidades',
        'TotalBolivianos',
        'Precio',
    ];

    protected $casts = [
        'Unidades' => 'decimal:4',
        'TotalBolivianos' => 'decimal:2',
        'Precio' => 'decimal:2',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'IdCompras', 'IdCompras');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }
    
}