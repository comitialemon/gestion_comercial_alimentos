<?php
// app/Models/Gestion/Inventario/InventarioFisicoDiarioCabecera.php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class InventarioFisicoDiarioCabecera extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_fisicorealizado_diario_cabecera';
    protected $primaryKey = 'IdFisicoDiario';
    public $timestamps = false;

    protected $fillable = [
        // 'IdLiquidacionVendedor', // ❌ ELIMINADO - Ya no se usa
        'IdFecha',
        'IdCliente',
        'IdSucursal',
        'IdOperador',
        'CantidadTotalProductos',
        'CantidadContados',
        'FechaRegistro',
        'ActivoInactivo',
        'NumeroCorrelativo',  // ✅ AGREGADO
        'IdTipoOperacion',    // ✅ AGREGADO
    ];

    protected $casts = [
        'CantidadTotalProductos' => 'integer',
        'CantidadContados' => 'integer',
        'ActivoInactivo' => 'integer',
        'NumeroCorrelativo' => 'integer',
    ];

    public function detalles()
    {
        return $this->hasMany(InventarioFisicoDiarioDetalle::class, 'IdFisicoDiario', 'IdFisicoDiario');
    }

    // ❌ ELIMINAR ESTA RELACIÓN (ya no existe IdLiquidacionVendedor)
    // public function liquidacion()
    // {
    //     return $this->belongsTo(\App\Models\Gestion\Impuestos\LiquidacionVendedor::class, 'IdLiquidacionVendedor', 'iDLiquidacionVendedor');
    // }

    public function fecha()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Fecha::class, 'IdFecha', 'IdFecha');
    }

    public function operador()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Operador::class, 'IdOperador', 'IdOperador');
    }

    public function sucursal()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function tipoOperacion()
    {
        return $this->belongsTo(\App\Models\Gestion\Inventario\TipoOperacion::class, 'IdTipoOperacion', 'IdTipoOperacion');
    }
}