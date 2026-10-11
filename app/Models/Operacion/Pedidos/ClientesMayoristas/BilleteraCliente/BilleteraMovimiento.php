<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BilleteraMovimiento extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_operadores_billeteras_movimientos';
    protected $primaryKey = 'IdMovimiento';
    public $timestamps = false;

    protected $fillable = [
        'IdBilletera',
        'IdOperadorPedidoCliente',
        'IdOperador',
        'IdCliente',
        'IdSucursal',
        'Tipo',
        'Monto',
        'SaldoAntes',
        'SaldoDespues',
        'IdPedidoCliente',
        'IdMovimientoOrigen',
        'Glosa',
        'Observaciones',
        'OrigenPago',
        'ReferenciaPago',
        'IdAsientoContable',
        'IdOperadorInserta',
        'FechaMovimiento',
        'FechaCreacion',
    ];

    protected $casts = [
        'Monto' => 'decimal:3',
        'SaldoAntes' => 'decimal:3',
        'SaldoDespues' => 'decimal:3',
        'FechaMovimiento' => 'datetime',
        'FechaCreacion' => 'datetime',
    ];

    // ============================================================
    // RELACIONES
    // ============================================================

    public function billetera(): BelongsTo
    {
        return $this->belongsTo(BilleteraOperador::class, 'IdBilletera', 'IdBilletera');
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Gestion\Todos\Operador::class,
            'IdOperador',
            'IdOperador'
        );
    }

    public function operadorInserta(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Gestion\Todos\Operador::class,
            'IdOperadorInserta',
            'IdOperador'
        );
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Operacion\Pedidos\ClientesMayoristas\PedidoCliente::class,
            'IdPedidoCliente',
            'IdPedidoCliente'
        );
    }

    public function movimientoOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'IdMovimientoOrigen', 'IdMovimiento');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    public function scopePorBilletera($query, int $idBilletera)
    {
        return $query->where('IdBilletera', $idBilletera);
    }

    public function scopeAbonos($query)
    {
        return $query->where('Tipo', 'ABONO');
    }

    public function scopeCargos($query)
    {
        return $query->where('Tipo', 'CARGO');
    }

    public function scopeEntreFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('FechaMovimiento', [$desde, $hasta]);
    }

    // ============================================================
    // ACCESSORS
    // ============================================================

    public function getEsPositivoAttribute(): bool
    {
        return (float) $this->Monto > 0;
    }

    public function getMontoFormateadoAttribute(): string
    {
        $signo = $this->es_positivo ? '+' : '';
        return $signo . number_format((float) $this->Monto, 2, ',', '.');
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->Tipo) {
            'ABONO' => 'Abono',
            'CARGO' => 'Cargo por pedido',
            'AJUSTE_POSITIVO' => 'Ajuste (+)',
            'AJUSTE_NEGATIVO' => 'Ajuste (−)',
            'ANULACION' => 'Anulación',
            'DEVOLUCION' => 'Devolución',
            'TRANSFERENCIA_IN' => 'Transferencia recibida',
            'TRANSFERENCIA_OUT' => 'Transferencia enviada',
            'VENCIMIENTO' => 'Vencimiento',
            default => $this->Tipo,
        };
    }
}