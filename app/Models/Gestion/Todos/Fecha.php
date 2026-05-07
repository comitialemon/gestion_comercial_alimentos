<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class Fecha extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_fecha';
    protected $primaryKey = 'IdFecha';
    public $timestamps = false;

    protected $fillable = [
        'Fecha',
        'ActivoInactivo',
        'CierreSucursal',
        'CierrePermanente',
    ];
}