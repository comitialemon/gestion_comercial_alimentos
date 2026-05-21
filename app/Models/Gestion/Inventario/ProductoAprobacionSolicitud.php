<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Operador;

class ProductoAprobacionSolicitud extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'producto_aprobacion_solicitud';
    protected $primaryKey = 'IdProductoAprobacionSolicitud';
    public $timestamps = false;

    protected $fillable = [
        'IdDetalleProducto',
        'IdOperadorSolicita',
        'Estado',
        'FechaSolicitud',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    public function producto()
    {
        return $this->belongsTo(ProductoVenta::class, 'IdDetalleProducto', 'IdDetalleProducto');
    }

    public function solicitante()
    {
        return $this->belongsTo(Operador::class, 'IdOperadorSolicita', 'IdOperador');
    }

    public function votos()
    {
        return $this->hasMany(ProductoAprobacionVoto::class, 'IdProductoAprobacionSolicitud', 'IdProductoAprobacionSolicitud');
    }
}