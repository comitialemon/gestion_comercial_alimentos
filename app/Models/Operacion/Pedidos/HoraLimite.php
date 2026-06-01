<?php

namespace App\Models\Operacion\Pedidos;

use Illuminate\Database\Eloquent\Model;

class HoraLimite extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_ventas_pedidos_horalimite';
    protected $primaryKey = 'IdHoraLimite';
    public $timestamps = false;

    protected $fillable = [
        'Hora',
        'ActivaControlDia',
        'IdCliente',
        'IdSucursal',
    ];

    protected $casts = [
        'Hora' => 'integer',
        'ActivaControlDia' => 'boolean',
        'IdSucursal' => 'integer',
    ];

    /**
     * Scope para filtrar por cliente actual
     */
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    /**
     * Scope para sucursal específica o general (0 = todas)
     */
    public function scopePorSucursal($query, $sucursalId = null)
    {
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        return $query->where(function($q) use ($sucursalId) {
            $q->where('IdSucursal', $sucursalId)
              ->orWhere('IdSucursal', 0);
        });
    }

    /**
     * Scope para horas activas
     */
    public function scopeActivos($query)
    {
        return $query->where('ActivaControlDia', 0);
    }

    /**
     * Scope para ordenar por hora
     */
    public function scopeOrdenado($query)
    {
        return $query->orderBy('Hora');
    }

    /**
     * Accesor para mostrar hora formateada (ej: 14 → 14:00)
     */
    public function getHoraFormateadaAttribute()
    {
        return str_pad($this->Hora, 2, '0', STR_PAD_LEFT) . ':00';
    }

    /**
     * Accesor para estado (Activo/Inactivo)
     */
    public function getEstadoTextoAttribute()
    {
        return $this->ActivaControlDia ? 'Inactivo' : 'Activo';
    }

    public function getEstadoColorAttribute()
    {
        return $this->ActivaControlDia ? 'red' : 'green';
    }
}