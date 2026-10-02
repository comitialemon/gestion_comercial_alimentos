<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PedidoClientePago extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_pagos';
    protected $primaryKey = 'IdPagoPedido';
    public $timestamps = false;

    protected $fillable = [
        'IdPedidoCliente', 'IdCliente', 'IdSucursal', 'IdOperador', 'IdCredencial',
        'QrId', 'TransactionId', 'BranchCode', 'CodigoBanco',
        'Moneda', 'Monto', 'Descripcion', 'FechaVencimiento',
        'Estado', 'StatusQrCodeBanco',
        'MontoPagado', 'FechaPago', 'DatosPago',
        'DatosGeneracion',
        'FechaCreacion', 'FechaAnulacion', 'FechaExpiracion', 'FechaUltimaActualizacion',
        'Conciliado', 'FechaConciliacion', 'ObservacionConciliacion',
        'IntentosConsulta', 'UltimoError',
    ];

    protected $casts = [
        'Monto' => 'decimal:2',
        'MontoPagado' => 'decimal:2',
        'FechaVencimiento' => 'date',
        'FechaCreacion' => 'datetime',
        'FechaPago' => 'datetime',
        'FechaAnulacion' => 'datetime',
        'FechaExpiracion' => 'datetime',
        'FechaUltimaActualizacion' => 'datetime',
        'FechaConciliacion' => 'datetime',
        'DatosPago' => 'array',
        'DatosGeneracion' => 'array',
        'Conciliado' => 'boolean',
    ];

    // ============================================================
    // RELACIONES
    // ============================================================

    public function pedido()
    {
        return $this->belongsTo(PedidoCliente::class, 'IdPedidoCliente', 'IdPedidoCliente');
    }

    public function credencial()
    {
        return $this->belongsTo(
            \App\Models\Gestion\Impuestos\BancoCredencial::class,
            'IdCredencial',
            'IdCredencial'
        );
    }

    // ============================================================
    // SCOPES
    // ============================================================

    public function scopePendientes($query)
    {
        return $query->where('Estado', 'PENDIENTE');
    }

    public function scopePagados($query)
    {
        return $query->where('Estado', 'PAGADO');
    }

    public function scopeDelPedido($query, int $idPedido)
    {
        return $query->where('IdPedidoCliente', $idPedido);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    public function estaPendiente(): bool
    {
        return $this->Estado === 'PENDIENTE';
    }

    public function estaPagado(): bool
    {
        return $this->Estado === 'PAGADO';
    }

    public function estaExpirado(): bool
    {
        return $this->Estado === 'EXPIRADO';
    }

    public function estaAnulado(): bool
    {
        return $this->Estado === 'ANULADO';
    }

    public function puedeSerConsultado(): bool
    {
        return $this->Estado === 'PENDIENTE';
    }

    /**
     * ¿El QR expiró? (15 minutos desde creación)
     */
    public function haExpirado(): bool
    {
        if (!$this->FechaCreacion) return false;
        return $this->FechaCreacion->addMinutes(15)->isPast();
    }

    /**
     * Obtener pago pendiente de un pedido
     */
    public static function obtenerPendienteDelPedido(int $idPedido): ?self
    {
        return self::delPedido($idPedido)
            ->whereIn('Estado', ['PENDIENTE'])
            ->latest('IdPagoPedido')
            ->first();
    }

    /**
     * Obtener último pago de un pedido
     */
    public static function obtenerUltimoDelPedido(int $idPedido): ?self
    {
        return self::delPedido($idPedido)
            ->latest('IdPagoPedido')
            ->first();
    }
}