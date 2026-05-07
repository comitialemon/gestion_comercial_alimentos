<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    protected $connection = "mysql_gestion_comercial_alimentos";
    protected $table = "impuestos_ventas_detalle";
    protected $primaryKey = "idventasdetalle";
    public $timestamps = false;

    protected $fillable = [
        "idventas",
        "IdVentaGrupo",
        "idrelacionventainventario",
        "unidades",
        "preciounidades",
        "totalbolivianos",
        "PorcentajeDescuento",
        "Descuento",
        "TotalBolivianosFacturado",
        "entregado",
        "fechaentrega",
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, "idventas", "IdVentas");
    }
}
