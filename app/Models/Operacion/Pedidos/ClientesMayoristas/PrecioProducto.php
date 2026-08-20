<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Inventario\ProductoDetalle;
use Carbon\Carbon;

class PrecioProducto extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_precio_productos';
    protected $primaryKey = 'IdPrecioCliente';
    public $timestamps = false;

    protected $fillable = [
        'IdIdentificador',
        'IdProducto',
        'Precio',
        'IdCliente',
        'IdSucursal',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
        'ActivoInactivo',
    ];

    protected $casts = [
        'Precio' => 'decimal:2',
        'ActivoInactivo' => 'integer',
    ];

    // ==================== RELACIONES ====================
    
    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }

    // ==================== SCOPES ====================
    
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('IdCliente', $clienteId);
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('IdSucursal', $sucursalId);
    }

    public function scopePorIdentificador($query, $identificadorId)
    {
        return $query->where('IdIdentificador', $identificadorId);
    }

    public function scopePorProducto($query, $productoId)
    {
        return $query->where('IdProducto', $productoId);
    }

    // ==================== ACCESORS ====================
    
    public function getPrecioFormateadoAttribute()
    {
        return number_format($this->Precio, 2, ',', '.');
    }
}