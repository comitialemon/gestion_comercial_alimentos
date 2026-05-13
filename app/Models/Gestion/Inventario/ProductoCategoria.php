<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoCategoria extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_producto_categoria';
    protected $primaryKey = 'id_relacion';
    public $timestamps = false;

    protected $fillable = [
        'id_detalle_producto',
        'id_categoria'
    ];

    public function producto()
    {
        return $this->belongsTo(ProductoVenta::class, 'id_detalle_producto', 'IdDetalleProducto');
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaProducto::class, 'id_categoria', 'id_categoria');
    }
}