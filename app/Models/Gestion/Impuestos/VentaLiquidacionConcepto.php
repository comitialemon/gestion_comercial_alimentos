<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;

class VentaLiquidacionConcepto extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_ventas_liquidacion_concepto';
    protected $primaryKey = 'IdConceptoLiquidacion';
    public $timestamps = false;

    protected $fillable = [
        'Concepto',
        'IdCuenta',
        'IdCliente',
    ];

    public function liquidaciones()
    {
        return $this->hasMany(VentaLiquidacion::class, 'IdCuenta', 'IdConceptoLiquidacion');
    }
}