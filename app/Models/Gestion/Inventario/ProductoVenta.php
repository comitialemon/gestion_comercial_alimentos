<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoVenta extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario';
    protected $primaryKey = 'IdDetalleProducto';
    public $timestamps = false;

    protected $fillable = [
        'IdVentaGrupo', 'Codigo', 'Detalle', 'NombreCortoFactura',
        'PrecioVenta', 'ActivoInactivo', 'IdCliente', 'IdSucursal'
    ];
}