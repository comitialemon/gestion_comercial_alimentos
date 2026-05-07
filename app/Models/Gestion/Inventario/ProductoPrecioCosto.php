<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoPrecioCosto extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_productodetalle_precio_costo';
    protected $primaryKey = 'IdPrecioCosto';
    public $timestamps = false;

    protected $fillable = [
        'IdProducto',
        'IdFecha',
        'PrecioCosto',
        'IdCliente',
        'IdSucursal',
        'IdOperador',
    ];
}