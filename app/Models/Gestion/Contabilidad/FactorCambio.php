<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Fecha;

class FactorCambio extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_factorcambio';
    protected $primaryKey = 'IdFactor';
    public $timestamps = false;

    protected $fillable = [
        'IdFecha',
        'IdMoneda',
        'FactorCambio',
    ];

    protected $casts = [
        'FactorCambio' => 'decimal:6',
    ];

    // Relación con fecha
    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }

    // Relación con moneda
    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'IdMoneda', 'IdMoneda');
    }
}