<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoImagen extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_producto_imagen';
    protected $primaryKey = 'IdImagen';
    public $timestamps = false;

    protected $fillable = [
        'IdProducto',
        'NombreArchivo',
        'RutaOriginal',
        'RutaMedium',
        'RutaThumbnail',
        'Orden',
        'EsPrincipal',
        'TamanioKB',
        'Ancho',
        'Alto',
        'ActivoInactivo',
        'IdCliente',
        'IdSucursal',
        'IdOperadorRegistro',
        'FechaRegistro'
    ];

    protected $casts = [
        'EsPrincipal' => 'boolean',
        'ActivoInactivo' => 'boolean',
        'TamanioKB' => 'integer',
        'Ancho' => 'integer',
        'Alto' => 'integer'
    ];

    /**
     * Relación con el producto
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(ProductoVenta::class, 'IdProducto', 'IdDetalleProducto');
    }

    /**
     * Scope para imágenes activas
     */
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    /**
     * Scope para imagen principal
     */
    public function scopePrincipal($query)
    {
        return $query->where('EsPrincipal', 1);
    }

    /**
     * Obtener URL de la imagen original
     */
    public function getUrlOriginalAttribute(): string
    {
        return asset($this->RutaOriginal);
    }

    /**
     * Obtener URL de la imagen medium
     */
    public function getUrlMediumAttribute(): string
    {
        return asset($this->RutaMedium);
    }

    /**
     * Obtener URL de la imagen thumbnail
     */
    public function getUrlThumbnailAttribute(): string
    {
        return asset($this->RutaThumbnail);
    }
}