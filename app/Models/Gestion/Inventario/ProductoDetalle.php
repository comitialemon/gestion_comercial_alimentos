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
        'IdEstadoProducto',
        'IdUnidadMedida',
        'OrdenInformes',
        'Codigo',
        'Descripcion',
        'Precio',        // 🔥 SIEMPRE 0
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

    protected $casts = [
        'ActivoInactivo' => 'integer',
        'Precio' => 'decimal:2',
        'OrdenInformes' => 'integer',
        'CkeckListRuta' => 'boolean',
        'CierrePermanente' => 'boolean'
    ];

    /**
     * Scope para filtrar por contexto (solo cliente)
     */
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    /**
     * Scope para productos activos (0 = Activo)
     */
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    // ==================== RELACIONES ====================

    public function grupoAnalisis()
    {
        return $this->belongsTo(ProductoGrupoAnalisis::class, 'IdGrupoAnalisis', 'IdGrupoAnalisis');
    }

    public function linea()
    {
        return $this->belongsTo(ProductoLinea::class, 'IdLineaProducto', 'IdLinea');
    }

    public function estado()
    {
        return $this->belongsTo(ProductoEstado::class, 'IdEstadoProducto', 'IdEstado');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'IdUnidadMedida', 'IdUnidadMedida');
    }

    // ==================== ATRIBUTOS ====================

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 0 ? 'Activo' : 'Inactivo';
    }

    public function getEstadoClaseAttribute()
    {
        return $this->ActivoInactivo == 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    }
}