<?php

namespace App\Models\Gestion\Todos;  // ✅ Correcto

use Illuminate\Database\Eloquent\Model;

class Identificador extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_identificador';
    protected $primaryKey = 'IdIdentificador';
    public $timestamps = false;

    protected $fillable = [
        'CI_NIT',
        'Nombre',
        'IdOperadorIngreso',
        'FechaIngreso',
        'IdOperadorEdita',
        'FechaEdita',
    ];

    // Scope para buscar por NIT
    public function scopePorNit($query, $nit)
    {
        return $query->where('CI_NIT', $nit);
    }
    public function operador()
    {
        return $this->hasOne(Operador::class, 'IdIdentificador', 'IdIdentificador');
    }
}
