<?php

namespace App\Models\Gestion\Banco;

use Illuminate\Database\Eloquent\Model;

class BancoPagoQR extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'impuestos_banco_pagos_qr';
    protected $primaryKey = 'IdPagosQr';
    public $timestamps = false;

    protected $fillable = [
        'IdVentas', 'IdLiquidacion', 'IdCliente', 'IdClienteSucursal',
        'IdCredencial', 'IdOperadorIngresa', 'IdOperadorActualiza',
        'CodigoBanco', 'QrId', 'TransactionId', 'BranchCode',
        'Moneda', 'Monto', 'SingleUse', 'Descripcion', 'FechaVencimiento',
        'Estado', 'StatusQRCodeBanco', 'MontoPagado', 'MonedaPagada', 'FechaPago',
        'FechaCreacion', 'FechaAnulacion', 'FechaExpiracion', 'FechaUltimaActualizacion',
        'DatosGeneracion', 'DatosPago',
        'Conciliado', 'IdConciliacion', 'FechaConciliacion', 'ObservacionConciliacion',
        'IntentosConsulta', 'UltimoError',
    ];

    protected $casts = [
        'Monto' => 'decimal:2',
        'MontoPagado' => 'decimal:2',
        'SingleUse' => 'boolean',
        'Conciliado' => 'boolean',
        'FechaVencimiento' => 'date',
        'FechaCreacion' => 'datetime',
        'FechaPago' => 'datetime',
        'FechaAnulacion' => 'datetime',
        'FechaExpiracion' => 'datetime',
        'FechaUltimaActualizacion' => 'datetime',
        'FechaConciliacion' => 'datetime',
        'DatosGeneracion' => 'array',
        'DatosPago' => 'array',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'IdVentas', 'IdVentas');
    }

    public function credencial()
    {
        return $this->belongsTo(BancoCredencial::class, 'IdCredencial', 'IdCredencial');
    }

    public function scopeActivos($query) { return $query->where('Estado', 'ACTIVO'); }
    public function scopePagados($query) { return $query->where('Estado', 'PAGADO'); }
    public function scopeNoConciliados($query) { return $query->where('Conciliado', 0)->where('Estado', 'PAGADO'); }
}