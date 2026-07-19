<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoDisponibilidadDia extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario_disponibilidad_dias';
    protected $primaryKey = 'IdDisponibilidad';
    public $timestamps = false;

    protected $fillable = [
        'IdProducto',
        'IdSucursal',
        'DiaSemana',
        'Activo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'DiaSemana' => 'integer',
        'Activo' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(ProductoVenta::class, 'IdProducto', 'IdDetalleProducto');
    }

    public function sucursal()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }
}