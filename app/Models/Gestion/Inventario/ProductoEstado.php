<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoEstado extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_producto_estado';
    protected $primaryKey = 'IdEstado';
    public $timestamps = false;

    protected $fillable = [
        'Estado',
        'IdCliente',
        'IOperador'
    ];

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function productos()
    {
        return $this->hasMany(ProductoDetalle::class, 'IdEstadoProducto', 'IdEstado');
    }
}