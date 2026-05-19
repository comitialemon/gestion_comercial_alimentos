<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Contabilidad\FactorCambio;

class Fecha extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_fecha';
    protected $primaryKey = 'IdFecha';
    public $timestamps = false;

    protected $fillable = [
        'Fecha',
        'ActivoInactivo',
        'CierreSucursal',
        'CierrePermanente',
    ];

    // Relación con factores de cambio
    public function factoresCambio()
    {
        return $this->hasMany(FactorCambio::class, 'IdFecha', 'IdFecha');
    }

    // Scope para fechas activas
    public function scopeActivas($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    // Scope para fechas no cerradas
    public function scopeAbiertas($query)
    {
        return $query->where('CierreSucursal', 0)->where('CierrePermanente', 0);
    }

    // Accessor para fecha formateada
    public function getFechaFormateadaAttribute()
    {
        return date('d/m/Y', strtotime($this->Fecha));
    }
}