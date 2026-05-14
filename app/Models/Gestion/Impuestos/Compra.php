<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\Almacen;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Fecha;

class Compra extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_compras';
    protected $primaryKey = 'IdCompras';
    public $timestamps = false;

    protected $fillable = [
        'NumeroCorrelativo',
        'IdDiario',
        'IdCuenta',
        'IdAlmacen',
        'IdTipoFactura',
        'NumeroFactura',
        'IdNIT',
        'NumeroDUI',
        'NumeroAutorizacion',
        'IdFecha',
        'IdCliente',
        'IdSucursal',
        'ActivoInactivo',
        'FechaIngreso',
        'IdOperadorIngresa',
        'FechaActualiza',
        'IdOperadorActualiza',
        'ImporteFactura',
        'Observacion',
    ];

    protected $casts = [
        'ImporteFactura' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'IdDiario' => 'integer',
        'NumeroFactura' => 'integer',
        'NumeroAutorizacion' => 'integer',
    ];

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class, 'IdCompras', 'IdCompras');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'IdAlmacen', 'IdAlmacen');
    }

    public function proveedor()
    {
        return $this->belongsTo(Identificador::class, 'IdNIT', 'IdIdentificador');
    }

    public function fecha()
    {
        return $this->belongsTo(Fecha::class, 'IdFecha', 'IdFecha');
    }

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
                     ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    public function scopePendientes($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    public function scopeContabilizadas($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeBorradorPorOperador($query)
    {
        return $query->where('ActivoInactivo', 0)
                     ->where('IdOperadorIngresa', session('operador_id'));
    }
}