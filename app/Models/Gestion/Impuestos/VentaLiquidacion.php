<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;

class VentaLiquidacion extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_ventas_liquidacion';
    protected $primaryKey = 'IdVentasLiquidacion';
    public $timestamps = false;

    protected $fillable = [
        'IdVentas',
        'IdDiario',
        'IdIdentificador',
        'IdCuenta',
        'Bolivianos'
    ];

    /**
     * Relación con la venta
     */
    public function venta()
    {
        return $this->belongsTo(Venta::class, 'IdVentas', 'IdVentas');
    }

    /**
     * Relación con el concepto de liquidación (CORREGIDA)
     * IdCuenta en esta tabla = IdConceptoLiquidacion en la tabla conceptos
     */
    public function concepto()
    {
        return $this->belongsTo(VentaLiquidacionConcepto::class, 'IdCuenta', 'IdConceptoLiquidacion');
    }

    /**
     * Relación con el identificador (cliente/persona)
     */
    public function identificador()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }
}