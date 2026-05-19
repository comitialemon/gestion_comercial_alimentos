<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\ClienteSucursal;

class ProductoVentaPrecioSucursal extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario_preciosucursal';
    protected $primaryKey = 'IdPrecio';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdSucursal',
        'PrecioDiferenciadoA',
        'IdProducto',
        'Precio',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'Precio' => 'decimal:2',
    ];

    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoVenta::class, 'IdProducto', 'IdDetalleProducto');
    }
}