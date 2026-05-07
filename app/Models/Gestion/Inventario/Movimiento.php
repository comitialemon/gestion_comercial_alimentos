<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_propiamente';
    protected $primaryKey = 'IdInventarioPropiamente';
    public $timestamps = false;

    protected $fillable = [
        'IdTipoDeOperacion',
        'IdDocumento',
        'IdFecha',
        'IdAlmacen',
        'IdProducto',
        'Glosa',
        'D_H',
        'Unidades',
        'Bolivianos',
        'IdCliente',
        'IdSucursal',
    ];
}