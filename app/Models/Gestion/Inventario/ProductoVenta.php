<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoVenta extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario';
    protected $primaryKey = 'IdDetalleProducto';
    public $timestamps = false;
    
    // CONSTANTES
    const COMERCIAL_ACTIVO = 0;
    const COMERCIAL_INACTIVO = 1;
    const APROBACION_BORRADOR = 0;
    const APROBACION_PENDIENTE = 1;
    const APROBACION_APROBADO = 2;
    const APROBACION_RECHAZADO = 3;
    
    protected $fillable = [
        'Codigo',
        'Detalle',
        'PrecioVenta',
        'ActivoInactivo',
        'estado_aprobacion',
        // 'ImagenProducto', // ❌ ELIMINAR - Ya no se usa
        'IdCliente',
        'IdSucursal',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
        'CierrePermanente',
        'id_categoria',
    ];

    protected $casts = [
        'PrecioVenta' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'estado_aprobacion' => 'integer',
        'CierrePermanente' => 'integer',
    ];

    // ==================== RELACIONES CON IMÁGENES ====================

    /**
     * Relación con todas las imágenes del producto
     */
    public function imagenes()
    {
        return $this->hasMany(ProductoImagen::class, 'IdProducto', 'IdDetalleProducto')
            ->where('ActivoInactivo', 1)
            ->orderBy('Orden', 'asc');
    }

    /**
     * Relación con la imagen principal
     */
    public function imagenPrincipal()
    {
        return $this->hasOne(ProductoImagen::class, 'IdProducto', 'IdDetalleProducto')
            ->where('ActivoInactivo', 1)
            ->where('EsPrincipal', 1);
    }

    /**
     * 🔥 Obtener URL de la imagen principal (thumbnail)
     */
    public function getImagenUrlAttribute()
    {
        $principal = $this->imagenPrincipal;
        if ($principal) {
            return $principal->url_thumbnail;
        }
        return null;
    }

    /**
     * 🔥 Obtener todas las URLs de las imágenes
     */
    public function getImagenesUrlsAttribute()
    {
        return $this->imagenes->map(function($imagen) {
            return [
                'id' => $imagen->IdImagen,
                'thumbnail' => $imagen->url_thumbnail,
                'medium' => $imagen->url_medium,
                'original' => $imagen->url_original,
                'es_principal' => $imagen->EsPrincipal,
                'orden' => $imagen->Orden,
            ];
        })->toArray();
    }

    // ==================== SCOPES ====================

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function scopeActivosComercialmente($query)
    {
        return $query->where('ActivoInactivo', self::COMERCIAL_ACTIVO);
    }

    // ==================== RELACIONES EXISTENTES ====================

    public function categoria()
    {
        return $this->belongsTo(CategoriaProducto::class, 'id_categoria', 'id_categoria');
    }

    public function categorias()
    {
        return $this->belongsToMany(
            CategoriaProducto::class,
            'inventario_producto_categoria',
            'id_detalle_producto',
            'id_categoria'
        )->wherePivot('id_sucursal', session('cliente_sucursal_id'));
    }

    public function solicitudAprobacion()
    {
        return $this->hasOne(ProductoAprobacionSolicitud::class, 'IdDetalleProducto', 'IdDetalleProducto');
    }

    public function opcionesCambio()
    {
        return $this->hasMany(ComboOpcion::class, 'id_producto_combo', 'IdDetalleProducto');
    }

    // ==================== ATRIBUTOS ====================
    
    public function getEstadoTextoAttribute()
    {
        return match($this->ActivoInactivo) {
            self::COMERCIAL_ACTIVO => 'Activo',
            self::COMERCIAL_INACTIVO => 'Inactivo',
            default => 'Desconocido'
        };
    }
    
    public function getEstadoClaseAttribute()
    {
        return match($this->ActivoInactivo) {
            self::COMERCIAL_ACTIVO => 'bg-green-100 text-green-800',
            self::COMERCIAL_INACTIVO => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-500'
        };
    }
    
    public function getAprobacionTextoAttribute()
    {
        return match($this->estado_aprobacion) {
            self::APROBACION_BORRADOR => 'Borrador',
            self::APROBACION_PENDIENTE => 'Pendiente de Aprobación',
            self::APROBACION_APROBADO => 'Aprobado',
            self::APROBACION_RECHAZADO => 'Rechazado',
            default => 'Desconocido'
        };
    }
    
    public function getAprobacionClaseAttribute()
    {
        return match($this->estado_aprobacion) {
            self::APROBACION_BORRADOR => 'bg-gray-100 text-gray-600',
            self::APROBACION_PENDIENTE => 'bg-yellow-100 text-yellow-800',
            self::APROBACION_APROBADO => 'bg-green-100 text-green-800',
            self::APROBACION_RECHAZADO => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-500'
        };
    }

    public function esComboConOpciones()
    {
        return $this->opcionesCambio()->activo()->exists();
    }

    public function getOpcionesAgrupadasAttribute()
    {
        $opciones = $this->opcionesCambio()
            ->activo()
            ->with('productoSustituto')
            ->orderBy('orden')
            ->get();
        
        $agrupadas = [];
        foreach ($opciones as $opcion) {
            $originalId = $opcion->id_producto_original;
            if (!isset($agrupadas[$originalId])) {
                $agrupadas[$originalId] = [
                    'id_producto_original' => $originalId,
                    'nombre_original' => $opcion->productoOriginal?->Descripcion ?? 'Producto',
                    'opciones' => []
                ];
            }
            $agrupadas[$originalId]['opciones'][] = [
                'id_sustituto' => $opcion->id_producto_sustituto,
                'nombre' => $opcion->productoSustituto?->Descripcion ?? 'Producto',
                'es_default' => $opcion->es_default,
                'orden' => $opcion->orden
            ];
        }
        
        return array_values($agrupadas);
    }
}