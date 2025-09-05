<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class Operador extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_operador';
    protected $primaryKey = 'IdOperador';
    public $timestamps = false;

    protected $fillable = [
        'IdIdentificador', 'Iniciales', 'Clave', 'NombreAcceso',
        'DireccionDomicilio', 'TelefonoDomicilio', 'NumeroCelular',
        'IdOperadorTipo', 'ActivoInactivo',
    ];
}
