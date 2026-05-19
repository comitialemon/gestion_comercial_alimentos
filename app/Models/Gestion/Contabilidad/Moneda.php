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
}