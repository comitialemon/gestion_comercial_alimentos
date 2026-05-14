<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class AjusteInventarioDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_ajustespropiamente';
    protected $primaryKey = 'IdAjustesPropiamente';
    public $timestamps = false;

    protected $fillable = [
        'IdAjustesPrincipal',
        'IdProducto',
        'Unidades',
        'Bolivianos',
    ];

    protected $casts = [
        'Unidades' => 'decimal:2',
        'Bolivianos' => 'decimal:2',
    ];

    public function ajuste()
    {
        return $this->belongsTo(AjusteInventario::class, 'IdAjustesPrincipal', 'IdAjustesPrincipal');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }
}