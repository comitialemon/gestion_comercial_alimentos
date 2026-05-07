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

    /**
     * Obtiene el identificador (CI/NIT) del operador
     */
    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    /**
     * Obtiene el tipo de operador
     */
    public function tipo()
    {
        return $this->belongsTo(OperadorTipo::class, 'IdOperadorTipo', 'IdOperadorTipo');
    }

    /**
     * Obtiene las empresas asignadas a este operador (a través de sucursaldb)
     */
    public function empresas()
    {
        return $this->belongsToMany(
            Cliente::class,
            'todos_operador_sucursaldb',
            'IdOperador',
            'IdCliente'
        )->distinct();
    }

    /**
     * Obtiene las sucursales asignadas a este operador
     */
    public function sucursales()
    {
        return $this->belongsToMany(
            ClienteSucursal::class,
            'todos_operador_sucursaldb',
            'IdOperador',
            'IdSucursal'
        )->distinct();
    }

    /**
     * Scope para operadores activos
     */
    public function scopeActivo($query)
    {
        return $query->where('ActivoInactivo', 1);
    }
}