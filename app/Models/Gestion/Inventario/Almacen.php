<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\ClienteSucursal;

class Almacen extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_almacen';
    protected $primaryKey = 'IdAlmacen';
    public $timestamps = false;

    protected $fillable = ['Almacen', 'AlmacenPrincipal', 'IdCliente', 'IdSucursal'];

    // 🔥 Relación con sucursal (importante para que funcione)
    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }
}