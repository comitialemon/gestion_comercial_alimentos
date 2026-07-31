<?php
namespace App\Models\Operacion;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\UnidadMedida;
use App\Models\Gestion\Inventario\ProductoLinea;

class FichaTecnica extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_fichatecnica';
    protected $primaryKey = 'IdFicha';
    public $timestamps = false;

    protected $fillable = [
        'NumeroCorrelativo',
        'IdLineaProducto',
        'IdProductoTerminado',
        'CantidadProduccion',
        'IdUnidadMedidaProducto',
        'ActivoInactivo',
        'IdCliente',
        'IdSucursal',
        'IdOperadorIngresa',
        'FechaIngreso',
        'IdOperadorActualiza',
        'FechaEdita',
    ];

    protected $casts = [
        'CantidadProduccion' => 'float',
        'ActivoInactivo' => 'integer',
        'FechaVigencia' => 'date',
        'FechaIngreso' => 'datetime',
        'FechaEdita' => 'datetime',
    ];

    // Relaciones
    public function productoTerminado()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProductoTerminado', 'IdProducto');
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'IdUnidadMedidaProducto', 'IdUnidadMedida');
    }

    public function lineaProducto()
    {
        return $this->belongsTo(ProductoLinea::class, 'IdLineaProducto', 'IdLinea');
    }

    public function detalles()
    {
        return $this->hasMany(FichaTecnicaDetalle::class, 'IdFicha', 'IdFicha');
    }

    // Scopes
    public function scopeActivo($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('IdCliente', $clienteId);
    }

    public function scopePorProducto($query, $productoId)
    {
        return $query->where('IdProductoTerminado', $productoId);
    }
}