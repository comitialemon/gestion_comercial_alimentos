<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_productodetalle';
    protected $primaryKey = 'IdProducto';
    public $timestamps = false;

    protected $fillable = [
        'IdGrupoAnalisis',
        'IdLineaProducto',
        'IdGrupoProducto',
        'IdEstadoProducto',
        'IdUnidadMedida',
        'OrdenInformes',
        'Codigo',
        'Descripcion',
        'Precio',
        'ActivoInactivo',
        'CkeckListRuta',
        'IdCliente',
        'IdSucursal',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
        'CierrePermanente'
    ];

    /**
     * Scope para filtrar por contexto
     */
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    /**
     * Scope para productos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    /**
     * Relación con la unidad de medida
     */
    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'IdUnidadMedida', 'IdUnidadMedida');
    }

    /**
     * Relación con la línea de producto
     */
    public function linea()
    {
        return $this->belongsTo(ProductoLinea::class, 'IdLineaProducto', 'IdLinea');
    }

    /**
     * Relación con el grupo de producto
     */
    public function grupo()
    {
        return $this->belongsTo(ProductoGrupo::class, 'IdGrupoProducto', 'IdProductoGrupo');
    }

    /**
     * Relación con el estado del producto
     */
    public function estado()
    {
        return $this->belongsTo(ProductoEstado::class, 'IdEstadoProducto', 'IdEstado');
    }
}