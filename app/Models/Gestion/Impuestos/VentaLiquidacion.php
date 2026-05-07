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
        'Bolivianos',
        'EfectivoRecibido',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'IdVentas', 'IdVentas');
    }

    public function concepto()
    {
        return $this->belongsTo(VentaLiquidacionConcepto::class, 'IdCuenta', 'IdConceptoLiquidacion');
    }
}