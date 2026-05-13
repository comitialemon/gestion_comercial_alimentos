<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\TipoOperacion;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Todos\Fecha;

class InventarioPropiamente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_propiamente';
    protected $primaryKey = 'IdInventarioPropiamente';
    public $timestamps = false;

    protected $fillable = [
        'IdTipoDeOperacion',
        'IdDocumento',
        'IdFecha',
        'IdAlmacen',
        'IdProducto',
        'Glosa',
        'D_H',
        'Unidades',
        'Bolivianos',
        'IdCliente',
        'IdSucursal'
    ];

    /**
     * Scope para filtrar por contexto (cliente y sucursal actual)
     */
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    /**
     * Scope para movimientos de entrada (Debe)
     */
    public function scopeEntradas($query)
    {
        return $query->where('D_H', 'D');
    }

    /**
     * Scope para movimientos de salida (Haber)
     */
    public function scopeSalidas($query)
    {
        return $query->where('D_H', 'H');
    }

    /**
     * Relación con el producto
     */
    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }

    /**
     * Relación con el tipo de operación
     */
    public function tipoOperacion()
    {
        return $this->belongsTo(TipoOperacion::class, 'IdTipoDeOperacion', 'IdTipoOperacion');
    }

    /**
     * Relación con el almacén
     */
    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'IdAlmacen', 'IdAlmacen');
    }

    /**
     * Relación con la fecha
     */
    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }
}