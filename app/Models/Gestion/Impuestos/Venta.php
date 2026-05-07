<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $connection = "mysql_gestion_comercial_alimentos";
    protected $table = "impuestos_ventas";
    protected $primaryKey = "IdVentas";
    public $timestamps = false;

    protected $fillable = [
        "FechaVenta",
        "NumeroFactura",
        "NumeroAutorizacion",
        "IdEstado",
        "IdNIT",
        "ImporteVenta",
        "ImporteExcento",
        "ImporteExportaciones",
        "ImporteTasaCero",
        "ImporteDescuentos",
        "CodigoControl",
        "ActivoInactivo",
        "IdOperadorIngresa",
        "IdCliente",
        "IdClienteSucursal",
        "FechaUltimaActualizcion",
        "IdOperadorActualiza",
        "Entrega",
        "TicketDia",
        "FechaEntrega",
        "LiquidadoVendedor",
        "LugarVenta",
        "IdComisionista",
        "Observacion",
    ];

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class, "idventas", "IdVentas");
    }
}
