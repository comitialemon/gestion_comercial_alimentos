<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class ClienteSucursal extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_cliente_sucursal';
    protected $primaryKey = 'IdClienteSucursal';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdPlaza',
        'Nombre',
        'Direccion',
        'Telefono',
        'Celular',
        'NumeroSucursal',
        'ActivaInactivaR',
        'Orden',
        'Categoria',
        'ActivoInactivo',
        'facturacion_habilitada',
    ];

    /**
     * Obtiene la empresa de esta sucursal
     */
    public function empresa()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    /**
     * Obtiene la plaza de esta sucursal
     */
    public function plaza()
    {
        return $this->belongsTo(ClientePlaza::class, 'IdPlaza', 'IdPlaza');
    }

    /**
     * Obtiene los operadores asignados a esta sucursal
     */
    public function operadores()
    {
        return $this->belongsToMany(
            Operador::class,
            'todos_operador_sucursaldb',
            'IdSucursal',
            'IdOperador'
        )->distinct();
    }
}