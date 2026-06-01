<?php

namespace App\Models\Gestion\Impuestos;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Contabilidad\ContaCuenta;

class VentaLiquidacionConcepto extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_ventas_liquidacion_concepto';
    protected $primaryKey = 'IdConceptoLiquidacion';
    public $timestamps = false;

    protected $fillable = [
        'Concepto',
        'IdCuenta',
        'IdCliente',
        'activo',
        'requiere_identificador',
        'usa_identificador_factura'
    ];

    protected $casts = [
        'activo' => 'boolean',
        'requiere_identificador' => 'boolean',
        'usa_identificador_factura' => 'boolean',
    ];

    /**
     * Scope para filtrar por cliente actual
     */
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    /**
     * Scope para solo activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }

    /**
     * Relación con la cuenta contable
     */
    public function cuentaContable()
    {
        return $this->belongsTo(ContaCuenta::class, 'IdCuenta', 'IdCuenta');
    }

    /**
     * Relación con detalles de liquidación
     */
    public function detallesLiquidacion()
    {
        return $this->hasMany(LiquidacionVendedorDetalle::class, 'IdConceptoLiquidacion', 'IdConceptoLiquidacion');
    }
}