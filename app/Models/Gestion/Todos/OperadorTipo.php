<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class OperadorTipo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_operador_tipo';
    protected $primaryKey = 'IdOperadorTipo';
    public $timestamps = false;

    protected $fillable = [
        'Detalle',
    ];

    /**
     * Obtiene los operadores de este tipo
     */
    public function operadores()
    {
        return $this->hasMany(Operador::class, 'IdOperadorTipo', 'IdOperadorTipo');
    }
}