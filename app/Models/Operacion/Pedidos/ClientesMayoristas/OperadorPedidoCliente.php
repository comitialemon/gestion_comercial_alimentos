<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Operador;
use App\Models\Gestion\Todos\OperadorSucursalDb;

class OperadorPedidoCliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_operadores_clientes';
    protected $primaryKey = 'IdOperadorPedidoCliente';
    public $timestamps = false;

    protected $fillable = [
        'IdOperador',
        'IdSucursalDB',   // ✅ NUEVO: asignación (operador+cliente+sucursal)
        'IdCliente',      // ✅ NUEVO: cliente explícito
        'Ciudad',
        'Provincia',
        'Destino',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'ActivoInactivo' => 'integer',
        'Ciudad'         => 'boolean', // 🔥 NUEVO
        'Provincia'      => 'boolean', // 🔥 NUEVO
        'FechaInserta'   => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
    }

    public function asignacion()
    {
        return $this->belongsTo(OperadorSucursalDb::class, 'IdSucursalDB', 'IdSucursalDB');
    }

    public function cliente()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Cliente::class, 'IdCliente', 'IdCliente');
    }

    // ==================== SCOPES ====================

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopePorOperador($query, $idOperador)
    {
        return $query->where('IdOperador', $idOperador);
    }

    public function scopePorCliente($query, $idCliente)
    {
        return $query->where('IdCliente', $idCliente);
    }

    // ==================== ACCESORS ====================
/*
    public function getCiudadTextoAttribute()
    {
        return $this->Ciudad == 'Sí' ? 'Sí' : 'No';
    }

    public function getProvinciaTextoAttribute()
    {
        return $this->Provincia == 'Sí' ? 'Sí' : 'No';
    }*/
}