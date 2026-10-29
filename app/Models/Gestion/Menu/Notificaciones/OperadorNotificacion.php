<?php

namespace App\Models\Gestion\Menu\Notificaciones;

use Illuminate\Database\Eloquent\Model;

class OperadorNotificacion extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operador_notificaciones';
    protected $primaryKey = 'IdOperadorNotificacion';
    public $timestamps = false;

    protected $fillable = [
        'IdOperador',
        'IdNotificacionTipo',
        'IdCliente',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
    ];

    public function tipo()
    {
        return $this->belongsTo(NotificacionTipo::class, 'IdNotificacionTipo', 'IdNotificacionTipo');
    }
}