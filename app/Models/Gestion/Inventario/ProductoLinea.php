<?php
namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoLinea extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_producto_linea';
    protected $primaryKey = 'IdLinea';
    public $timestamps = false;

    protected $fillable = ['IdEstado', 'Linea', 'IdCliente', 'IdSucursal', 'IdOperador'];

    // ✅ CORREGIDO: Solo filtrar por IdCliente (NO por sucursal)
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function estado()
    {
        return $this->belongsTo(ProductoEstado::class, 'IdEstado', 'IdEstado');
    }
}