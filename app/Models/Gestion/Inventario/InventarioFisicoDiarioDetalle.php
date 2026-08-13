<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class InventarioFisicoDiarioDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_fisicorealizado_diario_detalle';
    protected $primaryKey = 'IdFisicoDiarioDetalle';
    public $timestamps = false;

    protected $fillable = [
        'IdFisicoDiario',
        'IdProducto',
        'CantidadContada',
        'CantidadSistema',
        'Diferencia',
        'FechaRegistro',
    ];

    protected $casts = [
        'CantidadContada' => 'decimal:2',
        'CantidadSistema' => 'decimal:2',
        'Diferencia' => 'decimal:2',
    ];

    // Relaciones
    public function cabecera()
    {
        return $this->belongsTo(InventarioFisicoDiarioCabecera::class, 'IdFisicoDiario', 'IdFisicoDiario');
    }

    public function producto()
    {
        return $this->belongsTo(\App\Models\Gestion\Inventario\ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }
}