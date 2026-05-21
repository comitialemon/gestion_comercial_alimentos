<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Operador;

class ProductoAprobacionVoto extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'producto_aprobacion_voto';
    protected $primaryKey = 'IdProductoAprobacionVoto';
    public $timestamps = false;

    protected $fillable = [
        'IdProductoAprobacionSolicitud',
        'IdOperadorAprobador',
        'Estado',
        'Comentario',
        'FechaVoto',
    ];

    public function solicitud()
    {
        return $this->belongsTo(ProductoAprobacionSolicitud::class, 'IdProductoAprobacionSolicitud', 'IdProductoAprobacionSolicitud');
    }

    public function aprobador()
    {
        return $this->belongsTo(Operador::class, 'IdOperadorAprobador', 'IdOperador');
    }
}