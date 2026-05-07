<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class ClientePlaza extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_cliente_plaza';
    protected $primaryKey = 'IdPlaza';
    public $timestamps = false;

    protected $fillable = [
        'Plaza',
        'Abreviacion',
    ];

    /**
     * Obtiene las sucursales de esta plaza
     */
    public function sucursales()
    {
        return $this->hasMany(ClienteSucursal::class, 'IdPlaza', 'IdPlaza');
    }
}