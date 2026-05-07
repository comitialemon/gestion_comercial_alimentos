<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class VentaGrupo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario_grupouno';
    protected $primaryKey = 'IdVentaGrupo';
    public $timestamps = false;

    protected $fillable = ['Detalle', 'Orden', 'IdCliente', 'IdSucursal'];
}