<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoVenta extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario';
    protected $primaryKey = 'IdDetalleProducto';
    public $timestamps = false;

    protected $fillable = [
        'IdVentaGrupo', 'Codigo', 'Detalle', 'NombreCortoFactura',
        'PrecioVenta', 'ActivoInactivo', 'IdCliente', 'IdSucursal'
    ];

    protected $casts = [
        'PrecioVenta' => 'decimal:2',
    ];

    // Scope para filtrar por empresa actual
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    // Scope para productos activos
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    // Relación con categorías (menú táctil)
    public function categorias()
    {
        return $this->belongsToMany(
            CategoriaProducto::class,
            'inventario_producto_categoria',
            'id_detalle_producto',
            'id_categoria'
        );
    }
}