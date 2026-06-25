<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaImagen extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_categoria_imagen';
    protected $primaryKey = 'IdImagenCategoria';
    public $timestamps = false;

    protected $fillable = [
        'IdCategoria',
        'NombreArchivo',
        'RutaOriginal',
        'RutaMedium',
        'RutaThumbnail',
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
     * Relación con la categoría
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'IdCategoria', 'id_categoria');
    }

    /**
     * Scope para imágenes activas
     */
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
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