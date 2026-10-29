<?php

namespace App\Models\Gestion\Menu\Notificaciones;

use Illuminate\Database\Eloquent\Model;

class NotificacionTipo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'notificaciones_tipos';
    protected $primaryKey = 'IdNotificacionTipo';
    public $timestamps = false;

    protected $fillable = [
        'Nombre', 
        'ActivoInactivo'
    ];

    public function operadores()
    {
        return $this->hasMany(OperadorNotificacion::class, 'IdNotificacionTipo', 'IdNotificacionTipo');
    }
}