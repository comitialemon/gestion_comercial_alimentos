<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;

class VentaEstado extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_ventas_estado';
    protected $primaryKey = 'IdVentasEstado';
    public $timestamps = false;

    protected $fillable = [
        'Abreviacion',
        'Detalle'
    ];

    const VALIDA = 1;
    const ANULADA = 2;
    const EXTRAVIADA = 3;
    const NO_UTILIZADA = 4;
    const CONTINGENCIA = 5;
    const LIBRE_CONSIGNACION = 6;
}