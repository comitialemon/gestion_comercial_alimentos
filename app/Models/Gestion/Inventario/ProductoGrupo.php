<?php
namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoGrupo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_productogrupo';
    protected $primaryKey = 'IdProductoGrupo';
    public $timestamps = false;

    protected $fillable = ['IdLinea', 'Grupo', 'IdCliente', 'IdSucursal'];

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    public function linea()
    {
        return $this->belongsTo(ProductoLinea::class, 'IdLinea', 'IdLinea');
    }
}