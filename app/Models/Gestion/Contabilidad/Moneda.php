<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class Moneda extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_moneda';
    protected $primaryKey = 'IdMoneda';
    public $timestamps = false;

    protected $fillable = [
        'Moneda',
        'Abreviacion',
    ];

    public function factoresCambio()
    {
        return $this->hasMany(FactorCambio::class, 'IdMoneda', 'IdMoneda');
    }

    // 🔥 NUEVO: Helper para obtener factor de cambio vigente en una fecha
    public function factorCambioVigente($fecha)
    {
        return $this->factoresCambio()
            ->whereHas('fecha', function($q) use ($fecha) {
                $q->where('Fecha', '<=', $fecha);
            })
            ->orderBy('IdFactor', 'desc')
            ->first();
    }

    // 🔥 NUEVO: Helper para obtener factor de cambio más reciente
    public function factorCambioReciente()
    {
        return $this->factoresCambio()
            ->orderBy('IdFactor', 'desc')
            ->first();
    }
}