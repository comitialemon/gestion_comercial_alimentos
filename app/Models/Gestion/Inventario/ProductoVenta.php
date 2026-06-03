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
        'ImagenProducto',  // 🔥 Cambiado: ahora es VARCHAR
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

    // 🔥 Accessor para obtener la URL completa de la imagen
    public function getImagenUrlAttribute()
    {
        if ($this->ImagenProducto && !empty($this->ImagenProducto)) {
            // Si la ruta ya tiene /storage/ al inicio, usarla directamente
            if (str_starts_with($this->ImagenProducto, '/storage/')) {
                return asset($this->ImagenProducto);
            }
            // Si no, asumir que está en storage
            return asset('/storage/' . ltrim($this->ImagenProducto, '/'));
        }
        return null;
    }

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function scopeActivosComercialmente($query)
    {
        return $query->where('ActivoInactivo', self::COMERCIAL_ACTIVO);
    }

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
    /**
     * Obtiene las opciones de cambio disponibles para este combo
     */
    public function opcionesCambio()
    {
        return $this->hasMany(ComboOpcion::class, 'id_producto_combo', 'IdDetalleProducto');
    }

    /**
     * Verifica si este producto es un combo con opciones de cambio
     */
    public function esComboConOpciones()
    {
        return $this->opcionesCambio()->activo()->exists();
    }

    /**
     * Obtiene las opciones de cambio agrupadas por producto original
     */
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