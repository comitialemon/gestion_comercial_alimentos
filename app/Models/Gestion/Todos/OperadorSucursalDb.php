<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class OperadorSucursalDb extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_operador_sucursaldb';
    protected $primaryKey = 'IdSucursalDB';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdSucursal',
        'IdOperador',
    ];

    /**
     * Obtiene el operador
     */
    public function operador()
    {
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
    }

    /**
     * Obtiene la empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    /**
     * Obtiene la sucursal
     */
    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }
}