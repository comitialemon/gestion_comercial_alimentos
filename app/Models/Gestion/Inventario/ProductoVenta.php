<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class ProductoVenta extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_relacion_ventainventario';
    protected $primaryKey = 'IdDetalleProducto';
    public $timestamps = false;
    
    // 🔥 ESTADOS DEL PRODUCTO
    const ESTADO_ACTIVO = 0;                 // Aprobado y disponible para venta
    const ESTADO_INACTIVO = 1;              // Borrador (no enviado a aprobación)
    const ESTADO_PENDIENTE_APROBACION = 2;  // Enviado, esperando votos
    const ESTADO_RECHAZADO = 3;             // Rechazado por algún aprobador
    
    protected $fillable = [
        'IdVentaGrupo',
        'Codigo',
        'Detalle',
        'NombreCortoFactura',
        'PrecioVenta',
        'ActivoInactivo',
        'ImagenProducto',
        'IdCliente',
        'IdSucursal',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
        'CierrePermanente',
    ];

    protected $casts = [
        'PrecioVenta' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'CierrePermanente' => 'integer',
    ];

    protected $attributes = [
        'CierrePermanente' => 0,
    ];

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
                     //->where('IdSucursal', session('cliente_sucursal_id'));
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', self::ESTADO_ACTIVO);
    }

    public function grupo()
    {
        return $this->belongsTo(VentaGrupo::class, 'IdVentaGrupo', 'IdVentaGrupo');
    }

    // ✅ CORRECTO - Solo define la conexión entre tablas
    public function categorias()
    {
        return $this->belongsToMany(
            CategoriaProducto::class,
            'inventario_producto_categoria',
            'id_detalle_producto',   // FK local
            'id_categoria'           // FK remoto
        );
    }

    public function solicitudAprobacion()
    {
        return $this->hasOne(ProductoAprobacionSolicitud::class, 'IdDetalleProducto', 'IdDetalleProducto');
    }
    
    public function getEstadoTextoAttribute()
    {
        return match($this->ActivoInactivo) {
            self::ESTADO_ACTIVO => 'Activo',
            self::ESTADO_INACTIVO => 'Borrador',
            self::ESTADO_PENDIENTE_APROBACION => 'Pendiente de Aprobación',
            self::ESTADO_RECHAZADO => 'Rechazado',
            default => 'Desconocido'
        };
    }
    
    public function getEstadoClaseAttribute()
    {
        return match($this->ActivoInactivo) {
            self::ESTADO_ACTIVO => 'bg-green-100 text-green-800',
            self::ESTADO_INACTIVO => 'bg-gray-100 text-gray-600',
            self::ESTADO_PENDIENTE_APROBACION => 'bg-yellow-100 text-yellow-800',
            self::ESTADO_RECHAZADO => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-500'
        };
    }
}