<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Operador;

class OperadorPedidoCliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_operadores_clientes';
    protected $primaryKey = 'IdOperadorPedidoCliente';
    public $timestamps = false;

    protected $fillable = [
        'IdOperador',
        'Ciudad',       // 1 = marcado, 0 = no marcado (por ahora)
        'Provincia',    // 1 = marcado, 0 = no marcado (por ahora)
        'Destino',      // Texto libre: "Cochabamba", "Cercado", etc.
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'Ciudad' => 'integer',
        'Provincia' => 'integer',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
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

    // ==================== ACCESORS ====================

    /**
     * Texto para Ciudad (útil para mostrar en UI)
     */
    public function getCiudadTextoAttribute()
    {
        return $this->Ciudad == 1 ? 'Sí' : 'No';
    }

    /**
     * Texto para Provincia
     */
    public function getProvinciaTextoAttribute()
    {
        return $this->Provincia == 1 ? 'Sí' : 'No';
    }
}