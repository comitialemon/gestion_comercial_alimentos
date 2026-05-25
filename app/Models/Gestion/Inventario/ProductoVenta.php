<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoVenta extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario';
    protected $primaryKey = 'IdDetalleProducto';
    public $timestamps = false;
    
    // 🔥 ESTADOS COMERCIALES (ActivoInactivo)
    const COMERCIAL_ACTIVO = 0;
    const COMERCIAL_INACTIVO = 1;
    
    // 🔥 ESTADOS DE APROBACIÓN (estado_aprobacion)
    const APROBACION_BORRADOR = 0;
    const APROBACION_PENDIENTE = 1;
    const APROBACION_APROBADO = 2;
    const APROBACION_RECHAZADO = 3;
    
    protected $fillable = [
        'IdVentaGrupo',
        'Codigo',
        'Detalle',
        'NombreCortoFactura',
        'PrecioVenta',
        'ActivoInactivo',
        'estado_aprobacion',
        'ImagenProducto',
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

    protected $attributes = [
        'CierrePermanente' => 0,
        'estado_aprobacion' => 0,
    ];

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    public function scopeActivosComercialmente($query)
    {
        return $query->where('ActivoInactivo', self::COMERCIAL_ACTIVO);
    }

    public function grupo()
    {
        return $this->belongsTo(VentaGrupo::class, 'IdVentaGrupo', 'IdVentaGrupo');
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaProducto::class, 'id_categoria', 'id_categoria');
    }

    // 🔥 RELACIÓN PRINCIPAL CON CATEGORÍAS (FILTRA POR SUCURSAL ACTUAL)
    public function categorias()
    {
        return $this->belongsToMany(
            CategoriaProducto::class,
            'inventario_producto_categoria',
            'id_detalle_producto',
            'id_categoria'
        )->wherePivot('id_sucursal', session('cliente_sucursal_id')); // 🔥 CLAVE: filtro por sucursal
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
}