<?php

namespace App\Models\Gestion\Contabilidad;

use Illuminate\Database\Eloquent\Model;

class TipoDiario extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'conta_tipodiario';
    protected $primaryKey = 'IdTipoDiario';
    public $timestamps = false;

    protected $fillable = [
        'TipoDiario',
        'Abreviacion',
    ];

    public function diarios()
    {
        return $this->hasMany(Diario::class, 'IdTipoDiario', 'IdTipoDiario');
    }
}