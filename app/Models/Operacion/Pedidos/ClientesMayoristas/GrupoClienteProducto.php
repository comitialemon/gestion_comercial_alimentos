<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;

class GrupoClienteProducto extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_grupo_cliente_producto';
    protected $primaryKey = 'IdGrupoClienteProducto';
    public $timestamps = false;

    protected $fillable = [
        'IdGrupoCliente',
        'IdProducto',
        'PrecioSinFactura',
        'PrecioConFactura',
        'PedidoMinimo',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'IdGrupoClienteProducto' => 'integer',
        'IdGrupoCliente' => 'integer',
        'IdProducto' => 'integer',
        'PrecioSinFactura' => 'decimal:2',
        'PrecioConFactura' => 'decimal:2',
        'PedidoMinimo' => 'integer',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    /**
     * Grupo al que pertenece
     */
    public function grupoCliente()
    {
        return $this->belongsTo(GrupoCliente::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    /**
     * Producto
     */
    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }

    // ==================== SCOPES ====================

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeInactivos($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    public function scopePorGrupo($query, $idGrupoCliente)
    {
        return $query->where('IdGrupoCliente', $idGrupoCliente);
    }

    public function scopePorProducto($query, $idProducto)
    {
        return $query->where('IdProducto', $idProducto);
    }

    public function scopeConPrecio($query)
    {
        return $query->where(function ($q) {
            $q->where('PrecioSinFactura', '>', 0)
              ->orWhere('PrecioConFactura', '>', 0);
        });
    }

    // ==================== ACCESORS ====================

    public function getPrecioSinFacturaFormateadoAttribute()
    {
        return number_format($this->PrecioSinFactura ?? 0, 2, ',', '.');
    }

    public function getPrecioConFacturaFormateadoAttribute()
    {
        return number_format($this->PrecioConFactura ?? 0, 2, ',', '.');
    }

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Activo' : 'Inactivo';
    }

    /**
     * Obtener el precio según el tipo
     */
    public function obtenerPrecio($tipoPrecio = 'sin_factura')
    {
        return $tipoPrecio === 'con_factura' 
            ? $this->PrecioConFactura 
            : $this->PrecioSinFactura;
    }

    /**
     * Verifica si tiene precio para el tipo indicado
     */
    public function tienePrecio($tipoPrecio = 'sin_factura')
    {
        $precio = $this->obtenerPrecio($tipoPrecio);
        return $precio !== null && $precio > 0;
    }
}