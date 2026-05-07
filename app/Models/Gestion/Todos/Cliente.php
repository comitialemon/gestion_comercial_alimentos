<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_cliente';
    protected $primaryKey = 'IdCliente';
    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'NIT',
        'Direccion',
        'Fono',
        'Celular',
        'CIRepresentanteLegal',
        'NombreRepresentanteLegal',
        'IdFechaInicioOperaciones',
        'facturacion_habilitada',
    ];

    /**
     * Obtiene las sucursales de esta empresa
     */
    public function sucursales()
    {
        return $this->hasMany(ClienteSucursal::class, 'IdCliente', 'IdCliente');
    }

    /**
     * Obtiene los operadores asignados a esta empresa
     */
    public function operadores()
    {
        return $this->belongsToMany(
            Operador::class,
            'todos_operador_sucursaldb',
            'IdCliente',
            'IdOperador'
        )->distinct();
    }
}