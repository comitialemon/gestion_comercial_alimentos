<?php

namespace App\Models\Operacion;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\UnidadMedida;

class FichaTecnicaDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_fichatecnica_propiamente';
    protected $primaryKey = 'IdFichaDetalle';
    public $timestamps = false;

    protected $fillable = [
        'IdFicha',
        'IdProductoInsumo',
        'IdUnidadMedida',
        'Orden',
        'Unidades',
        'IdOperadorIngresa',
        'FechaIngreso',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'Unidades' => 'float',
        'Orden' => 'integer',
        'FechaIngreso' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // Relaciones
    public function fichaTecnica()
    {
        return $this->belongsTo(FichaTecnica::class, 'IdFicha', 'IdFicha');
    }

    public function insumo()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProductoInsumo', 'IdProducto');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'IdUnidadMedida', 'IdUnidadMedida');
    }
}