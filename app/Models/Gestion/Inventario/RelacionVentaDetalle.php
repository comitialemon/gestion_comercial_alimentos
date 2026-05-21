<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class RelacionVentaDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario_detalle';
    protected $primaryKey = 'IdDetalleProductoPorcion';
    public $timestamps = false;

    protected $fillable = [
        'IdDetalleProducto',
        'IdProducto',
        'Porcion',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    /**
     * Relación con el producto de inventario
     */
    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }

    /**
     * Relación con el producto de venta (padre)
     */
    public function productoServicio()
    {
        return $this->belongsTo(ProductoVenta::class, 'IdDetalleProducto', 'IdDetalleProducto');
    }
}