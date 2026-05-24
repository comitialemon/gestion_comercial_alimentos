<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;

class LiquidacionVendedorDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_ventas_liquidacion_vendedor_detalle';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'iDLiquidacionVendedor',
        'IdConceptoLiquidacion',
        'monto_sistema',
        'monto_confirmacion',
    ];

    protected $casts = [
        'monto_sistema' => 'decimal:2',
        'monto_confirmacion' => 'decimal:2',
    ];

    public function liquidacion()
    {
        return $this->belongsTo(LiquidacionVendedor::class, 'iDLiquidacionVendedor', 'iDLiquidacionVendedor');
    }

    public function concepto()
    {
        return $this->belongsTo(VentaLiquidacionConcepto::class, 'IdConceptoLiquidacion', 'IdConceptoLiquidacion');
    }
}