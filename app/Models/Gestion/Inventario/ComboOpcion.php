<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ComboOpcion extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario_combo_opcion';
    protected $primaryKey = 'id_combo_opcion';
    public $timestamps = false;

    protected $fillable = [
        'id_producto_combo',
        'id_producto_original',
        'id_producto_sustituto',
        'orden',
        'es_default',
        'activo',
        'id_operador_inserta',
        'fecha_inserta',
        'id_operador_actualiza',
        'fecha_actualiza',
    ];

    protected $casts = [
        'orden' => 'integer',
        'es_default' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * Relación con el combo (producto de venta)
     */
    public function combo()
    {
        return $this->belongsTo(ProductoVenta::class, 'id_producto_combo', 'IdDetalleProducto');
    }

    /**
     * Relación con el producto original (el que está fijo en el combo)
     */
    public function productoOriginal()
    {
        return $this->belongsTo(ProductoDetalle::class, 'id_producto_original', 'IdProducto');
    }

    /**
     * Relación con el producto sustituto (por el que se puede cambiar)
     */
    public function productoSustituto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'id_producto_sustituto', 'IdProducto');
    }

    /**
     * Scope para opciones activas
     */
    public function scopeActivo($query)
    {
        return $query->where('activo', 1);
    }

    /**
     * Scope para un combo específico
     */
    public function scopeParaCombo($query, $idProductoCombo)
    {
        return $query->where('id_producto_combo', $idProductoCombo);
    }

    /**
     * Scope para un producto original específico
     */
    public function scopeParaProductoOriginal($query, $idProductoOriginal)
    {
        return $query->where('id_producto_original', $idProductoOriginal);
    }
}