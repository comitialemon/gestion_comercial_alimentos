<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InventarioFisicoDiarioConfig extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_fisicorealizado_diario_config';
    protected $primaryKey = 'IdConfig';
    public $timestamps = false;

    protected $fillable = [
        'CantidadProductos',
        'IdCliente',
        'IdSucursal',
        'ActivoInactivo',
        'IdOperadorIngreso',
        'FechaIngreso',
        'IdOperadorEdita',
        'FechaEdita',
    ];

    protected $casts = [
        'CantidadProductos' => 'integer',
        'ActivoInactivo' => 'integer',
    ];

    // 🔥 RELACIONES CORREGIDAS
    public function sucursal()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function operadorIngreso()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Operador::class, 'IdOperadorIngreso', 'IdOperador');
    }

    public function operadorEdita()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Operador::class, 'IdOperadorEdita', 'IdOperador');
    }

    // Scopes
    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('IdCliente', $clienteId);
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('IdSucursal', $sucursalId);
    }

    public function scopeActivo($query)
    {
        return $query->where('ActivoInactivo', 1);
    }
}