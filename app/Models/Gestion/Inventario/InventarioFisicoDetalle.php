<?php
// app/Models/Gestion/Inventario/InventarioFisicoDetalle.php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class InventarioFisicoDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_fisicorealizado_detalle';
    protected $primaryKey = 'IdFisicoPropiamente';
    public $timestamps = false;

    protected $fillable = [
        'IdFisico',
        'IdProducto',
        'UnidadesSaldo',
        'Unidades',
        'UnidadesAjuste',
    ];

    protected $casts = [
        'UnidadesSaldo' => 'float',
        'Unidades' => 'float',
        'UnidadesAjuste' => 'float',
    ];

    public function inventarioFisico()
    {
        return $this->belongsTo(InventarioFisico::class, 'IdFisico', 'IdFisico');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }
}