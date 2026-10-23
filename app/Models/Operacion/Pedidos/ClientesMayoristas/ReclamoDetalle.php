<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class ReclamoDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_reclamos_detalle';
    protected $primaryKey = 'IdReclamoDetalle';
    public $timestamps = false;

    protected $fillable = [
        'IdReclamo', 
        'IdProducto', 
        'CantidadReclamada', 
        'CantidadAprobada', 
        'Motivo'
    ];

    protected $casts = [
        'CantidadReclamada' => 'integer',
        'CantidadAprobada' => 'integer',
    ];

    public function reclamo()
    {
        return $this->belongsTo(Reclamo::class, 'IdReclamo', 'IdReclamo');
    }

    public function producto()
    {
        return $this->belongsTo(\App\Models\Gestion\Inventario\ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }
}