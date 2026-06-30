<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoGrupoAnalisis extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_productogrupoanalisis';
    protected $primaryKey = 'IdGrupoAnalisis';
    public $timestamps = false;

    protected $fillable = [
        'Grupo',
        'IdCliente',
        'IdSucursal'
    ];

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function productos()
    {
        return $this->hasMany(ProductoDetalle::class, 'IdGrupoAnalisis', 'IdGrupoAnalisis');
    }
}