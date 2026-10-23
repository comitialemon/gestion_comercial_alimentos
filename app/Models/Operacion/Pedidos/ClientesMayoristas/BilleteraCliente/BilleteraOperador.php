<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas\BilleteraCliente;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BilleteraOperador extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_operadores_billeteras';
    protected $primaryKey = 'IdBilletera';
    public $timestamps = false;

    protected $fillable = [
        'IdOperadorPedidoCliente',
        'IdOperador',
        'IdCliente',
        'IdSucursal',
        'SaldoActual',
        'SaldoInicial',
        'TotalCargado',
        'TotalConsumido',
        'ActivoInactivo',
        'MotivoInactivacion',
        'IdCuentaContable',
        'PermiteAutoRecarga',
        'SaldoMinimoAlerta',
        'IdOperadorInserta',
        'FechaCreacion',
        'IdOperadorActualiza',
        'FechaUltimaActualizacion',
    ];

    protected $casts = [
        'SaldoActual' => 'decimal:3',
        'SaldoInicial' => 'decimal:3',
        'TotalCargado' => 'decimal:3',
        'TotalConsumido' => 'decimal:3',
        'SaldoMinimoAlerta' => 'decimal:3',
        'ActivoInactivo' => 'integer',
        'PermiteAutoRecarga' => 'integer',
        'FechaCreacion' => 'datetime',
        'FechaUltimaActualizacion' => 'datetime',
    ];

    // ============================================================
    // RELACIONES
    // ============================================================

    public function operadorPedidoCliente(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Operacion\Pedidos\ClientesMayoristas\OperadorPedidoCliente::class,
            'IdOperadorPedidoCliente',
            'IdOperadorPedidoCliente'
        );
    }

    public function operador(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Gestion\Todos\Operador::class,
            'IdOperador',
            'IdOperador'
        );
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Gestion\Todos\Cliente::class,
            'IdCliente',
            'IdCliente'
        );
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(
            \App\Models\Gestion\Todos\ClienteSucursal::class,
            'IdSucursal',
            'IdClienteSucursal'
        );
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(BilleteraMovimiento::class, 'IdBilletera', 'IdBilletera')
            ->orderBy('FechaMovimiento', 'desc')
            ->orderBy('IdMovimiento', 'desc');
    }

    // ============================================================
    // SCOPES
    // ============================================================

    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'))
            ->where('IdSucursal', session('cliente_sucursal_id'));
    }

    public function scopeActivas($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    public function scopeInactivas($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeDelOperador($query, int $idOperador)
    {
        return $query->where('IdOperador', $idOperador);
    }

    public function scopeDelOperadorPedidoCliente($query, int $idOperadorPedidoCliente)
    {
        return $query->where('IdOperadorPedidoCliente', $idOperadorPedidoCliente);
    }

    // ============================================================
    // HELPERS DE ESTADO
    // ============================================================

    public function estaActiva(): bool
    {
        return (int) $this->ActivoInactivo === 0;
    }

    public function estaInactiva(): bool
    {
        return (int) $this->ActivoInactivo === 1;
    }

    public function tieneSaldo(): bool
    {
        return (float) $this->SaldoActual > 0;
    }

    public function tieneSaldoSuficiente(float $monto): bool
    {
        return (float) $this->SaldoActual >= $monto;
    }

    /**
     * ¿El saldo está por debajo del mínimo configurado?
     * 
     * Reglas:
     * - Si SaldoMinimoAlerta = 0 → no hay alerta configurada → false
     * - Si SaldoMinimoAlerta > 0 → hay alerta → comparar
     */
    public function saldoBajoMinimo(): bool
    {
        if ((float) $this->SaldoMinimoAlerta <= 0) {
            return false;
        }
        return (float) $this->SaldoActual <= (float) $this->SaldoMinimoAlerta;
    }

    // ============================================================
    // HELPERS ESTÁTICOS
    // ============================================================

    public static function porOperador(int $idOperador): ?self
    {
        return self::where('IdOperador', $idOperador)->first();
    }

    public static function operadorTieneBilleteraActiva(int $idOperador): bool
    {
        return self::where('IdOperador', $idOperador)
            ->where('ActivoInactivo', 0)
            ->exists();
    }

    public static function obtenerActivaDelOperador(int $idOperador): ?self
    {
        return self::where('IdOperador', $idOperador)
            ->where('ActivoInactivo', 0)
            ->first();
    }

    // ============================================================
    // MÉTODOS DE NEGOCIO
    // ============================================================

    /**
     * CARGAR SALDO (ABONO)
     */
    public function cargarSaldo(
        float $monto,
        string $glosa,
        string $origenPago,
        ?string $referenciaPago,
        ?string $observaciones,
        int $idOperadorInserta
    ): BilleteraMovimiento {
        if ($monto <= 0) {
            throw new \Exception('El monto a cargar debe ser mayor a cero.');
        }

        if (!$this->estaActiva()) {
            throw new \Exception('La billetera está inactiva. Actívala antes de cargar saldo.');
        }

        return DB::connection('mysql_gestion_comercial_alimentos')
            ->transaction(function () use (
                $monto,
                $glosa,
                $origenPago,
                $referenciaPago,
                $observaciones,
                $idOperadorInserta
            ) {
                $billetera = self::where('IdBilletera', $this->IdBilletera)
                    ->lockForUpdate()
                    ->first();

                if (!$billetera) {
                    throw new \Exception('Billetera no encontrada.');
                }

                $saldoAntes = (float) $billetera->SaldoActual;
                $saldoDespues = $saldoAntes + $monto;

                $movimiento = BilleteraMovimiento::create([
                    'IdBilletera' => $billetera->IdBilletera,
                    'IdOperadorPedidoCliente' => $billetera->IdOperadorPedidoCliente,
                    'IdOperador' => $billetera->IdOperador,
                    'IdCliente' => $billetera->IdCliente,
                    'IdSucursal' => $billetera->IdSucursal,
                    'Tipo' => 'ABONO',
                    'Monto' => $monto,
                    'SaldoAntes' => $saldoAntes,
                    'SaldoDespues' => $saldoDespues,
                    'IdPedidoCliente' => null,
                    'IdMovimientoOrigen' => null,
                    'Glosa' => $glosa,
                    'Observaciones' => $observaciones,
                    'OrigenPago' => $origenPago,
                    'ReferenciaPago' => $referenciaPago,
                    'IdOperadorInserta' => $idOperadorInserta,
                    'FechaMovimiento' => now(),
                    'FechaCreacion' => now(),
                ]);

                $billetera->update([
                    'SaldoActual' => $saldoDespues,
                    'TotalCargado' => (float) $billetera->TotalCargado + $monto,
                    'IdOperadorActualiza' => $idOperadorInserta,
                    'FechaUltimaActualizacion' => now(),
                ]);

                $this->refresh();

                return $movimiento;
            });
    }

    /**
     * DESCONTAR SALDO (CARGO por pedido)
     */
    public function descontarSaldo(
        float $monto,
        int $idPedidoCliente,
        string $glosa,
        int $idOperadorInserta
    ): BilleteraMovimiento {
        if ($monto <= 0) {
            throw new \Exception('El monto a descontar debe ser mayor a cero.');
        }

        if (!$this->estaActiva()) {
            throw new \Exception('La billetera está inactiva.');
        }

        return DB::connection('mysql_gestion_comercial_alimentos')
            ->transaction(function () use ($monto, $idPedidoCliente, $glosa, $idOperadorInserta) {
                $billetera = self::where('IdBilletera', $this->IdBilletera)
                    ->lockForUpdate()
                    ->first();

                if (!$billetera) {
                    throw new \Exception('Billetera no encontrada.');
                }

                $saldoAntes = (float) $billetera->SaldoActual;

                if ($saldoAntes < $monto) {
                    throw new \Exception(
                        sprintf(
                            'Saldo insuficiente. Disponible: Bs. %s, Requerido: Bs. %s',
                            number_format($saldoAntes, 2),
                            number_format($monto, 2)
                        )
                    );
                }

                $saldoDespues = $saldoAntes - $monto;

                $movimiento = BilleteraMovimiento::create([
                    'IdBilletera' => $billetera->IdBilletera,
                    'IdOperadorPedidoCliente' => $billetera->IdOperadorPedidoCliente,
                    'IdOperador' => $billetera->IdOperador,
                    'IdCliente' => $billetera->IdCliente,
                    'IdSucursal' => $billetera->IdSucursal,
                    'Tipo' => 'CARGO',
                    'Monto' => -$monto,
                    'SaldoAntes' => $saldoAntes,
                    'SaldoDespues' => $saldoDespues,
                    'IdPedidoCliente' => $idPedidoCliente,
                    'IdMovimientoOrigen' => null,
                    'Glosa' => $glosa,
                    'Observaciones' => null,
                    'OrigenPago' => 'SISTEMA',
                    'ReferenciaPago' => null,
                    'IdOperadorInserta' => $idOperadorInserta,
                    'FechaMovimiento' => now(),
                    'FechaCreacion' => now(),
                ]);

                $billetera->update([
                    'SaldoActual' => $saldoDespues,
                    'TotalConsumido' => (float) $billetera->TotalConsumido + $monto,
                    'IdOperadorActualiza' => $idOperadorInserta,
                    'FechaUltimaActualizacion' => now(),
                ]);

                $this->refresh();

                return $movimiento;
            });
    }

    /**
     * ANULAR PEDIDO (devolver saldo)
     */
    public function anularPedido(
        int $idPedidoCliente,
        string $motivo,
        int $idOperadorInserta
    ): BilleteraMovimiento {
        return DB::connection('mysql_gestion_comercial_alimentos')
            ->transaction(function () use ($idPedidoCliente, $motivo, $idOperadorInserta) {
                $billetera = self::where('IdBilletera', $this->IdBilletera)
                    ->lockForUpdate()
                    ->first();

                if (!$billetera) {
                    throw new \Exception('Billetera no encontrada.');
                }

                $cargoOriginal = BilleteraMovimiento::where('IdBilletera', $billetera->IdBilletera)
                    ->where('IdPedidoCliente', $idPedidoCliente)
                    ->where('Tipo', 'CARGO')
                    ->first();

                if (!$cargoOriginal) {
                    throw new \Exception('No se encontró un cargo para este pedido en la billetera.');
                }

                $yaAnulado = BilleteraMovimiento::where('IdBilletera', $billetera->IdBilletera)
                    ->where('IdPedidoCliente', $idPedidoCliente)
                    ->where('Tipo', 'ANULACION')
                    ->exists();

                if ($yaAnulado) {
                    throw new \Exception('Este pedido ya fue anulado en la billetera.');
                }

                $montoDevolver = abs((float) $cargoOriginal->Monto);
                $saldoAntes = (float) $billetera->SaldoActual;
                $saldoDespues = $saldoAntes + $montoDevolver;

                $movimiento = BilleteraMovimiento::create([
                    'IdBilletera' => $billetera->IdBilletera,
                    'IdOperadorPedidoCliente' => $billetera->IdOperadorPedidoCliente,
                    'IdOperador' => $billetera->IdOperador,
                    'IdCliente' => $billetera->IdCliente,
                    'IdSucursal' => $billetera->IdSucursal,
                    'Tipo' => 'ANULACION',
                    'Monto' => $montoDevolver,
                    'SaldoAntes' => $saldoAntes,
                    'SaldoDespues' => $saldoDespues,
                    'IdPedidoCliente' => $idPedidoCliente,
                    'IdMovimientoOrigen' => $cargoOriginal->IdMovimiento,
                    'Glosa' => "Anulación pedido #{$idPedidoCliente}",
                    'Observaciones' => $motivo,
                    'OrigenPago' => 'SISTEMA',
                    'ReferenciaPago' => null,
                    'IdOperadorInserta' => $idOperadorInserta,
                    'FechaMovimiento' => now(),
                    'FechaCreacion' => now(),
                ]);

                $billetera->update([
                    'SaldoActual' => $saldoDespues,
                    'TotalConsumido' => max(0, (float) $billetera->TotalConsumido - $montoDevolver),
                    'IdOperadorActualiza' => $idOperadorInserta,
                    'FechaUltimaActualizacion' => now(),
                ]);

                $this->refresh();

                return $movimiento;
            });
    }

    /**
     * AJUSTAR (manual)
     */
    public function ajustar(
        float $monto,
        string $motivo,
        int $idOperadorInserta
    ): BilleteraMovimiento {
        if ($monto == 0) {
            throw new \Exception('El monto del ajuste no puede ser cero.');
        }

        return DB::connection('mysql_gestion_comercial_alimentos')
            ->transaction(function () use ($monto, $motivo, $idOperadorInserta) {
                $billetera = self::where('IdBilletera', $this->IdBilletera)
                    ->lockForUpdate()
                    ->first();

                if (!$billetera) {
                    throw new \Exception('Billetera no encontrada.');
                }

                $saldoAntes = (float) $billetera->SaldoActual;
                $saldoDespues = $saldoAntes + $monto;

                if ($saldoDespues < 0) {
                    throw new \Exception(
                        sprintf(
                            'El ajuste dejaría el saldo en negativo (Bs. %s). No permitido.',
                            number_format($saldoDespues, 2)
                        )
                    );
                }

                $tipo = $monto > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO';

                $movimiento = BilleteraMovimiento::create([
                    'IdBilletera' => $billetera->IdBilletera,
                    'IdOperadorPedidoCliente' => $billetera->IdOperadorPedidoCliente,
                    'IdOperador' => $billetera->IdOperador,
                    'IdCliente' => $billetera->IdCliente,
                    'IdSucursal' => $billetera->IdSucursal,
                    'Tipo' => $tipo,
                    'Monto' => $monto,
                    'SaldoAntes' => $saldoAntes,
                    'SaldoDespues' => $saldoDespues,
                    'IdPedidoCliente' => null,
                    'IdMovimientoOrigen' => null,
                    'Glosa' => 'Ajuste manual',
                    'Observaciones' => $motivo,
                    'OrigenPago' => 'AJUSTE',
                    'ReferenciaPago' => null,
                    'IdOperadorInserta' => $idOperadorInserta,
                    'FechaMovimiento' => now(),
                    'FechaCreacion' => now(),
                ]);

                $update = [
                    'SaldoActual' => $saldoDespues,
                    'IdOperadorActualiza' => $idOperadorInserta,
                    'FechaUltimaActualizacion' => now(),
                ];

                if ($monto > 0) {
                    $update['TotalCargado'] = (float) $billetera->TotalCargado + $monto;
                } else {
                    $update['TotalConsumido'] = (float) $billetera->TotalConsumido + abs($monto);
                }

                $billetera->update($update);

                $this->refresh();

                return $movimiento;
            });
    }

    /**
     * ACTIVAR billetera
     */
    public function activar(int $idOperadorEncargado): void
    {
        $this->update([
            'ActivoInactivo' => 0,
            'MotivoInactivacion' => null,
            'IdOperadorActualiza' => $idOperadorEncargado,
            'FechaUltimaActualizacion' => now(),
        ]);
    }

    /**
     * INACTIVAR billetera
     */
    public function inactivar(int $idOperadorEncargado, ?string $motivo = null): void
    {
        $this->update([
            'ActivoInactivo' => 1,
            'MotivoInactivacion' => $motivo,
            'IdOperadorActualiza' => $idOperadorEncargado,
            'FechaUltimaActualizacion' => now(),
        ]);
    }
        /**
     * DEVOLUCIÓN POR RECLAMO (Ticket de Calidad)
     * Registra un movimiento de tipo 'DEVOLUCION' e incrementa el saldo.
     */
    public function devolverPorReclamo(
        float $monto,
        int $idPedidoCliente,
        string $glosa,
        int $idOperadorInserta
    ): BilleteraMovimiento {
        if ($monto <= 0) {
            throw new \Exception('El monto a devolver debe ser mayor a cero.');
        }

        return DB::connection('mysql_gestion_comercial_alimentos')
            ->transaction(function () use ($monto, $idPedidoCliente, $glosa, $idOperadorInserta) {
                $billetera = self::where('IdBilletera', $this->IdBilletera)
                    ->lockForUpdate()
                    ->first();

                if (!$billetera) {
                    throw new \Exception('Billetera no encontrada.');
                }

                $saldoAntes = (float) $billetera->SaldoActual;
                $saldoDespues = $saldoAntes + $monto;

                $movimiento = BilleteraMovimiento::create([
                    'IdBilletera' => $billetera->IdBilletera,
                    'IdOperadorPedidoCliente' => $billetera->IdOperadorPedidoCliente,
                    'IdOperador' => $billetera->IdOperador,
                    'IdCliente' => $billetera->IdCliente,
                    'IdSucursal' => $billetera->IdSucursal,
                    'Tipo' => 'DEVOLUCION', // 🔥 TIPO ESPECÍFICO
                    'Monto' => $monto,
                    'SaldoAntes' => $saldoAntes,
                    'SaldoDespues' => $saldoDespues,
                    'IdPedidoCliente' => $idPedidoCliente,
                    'IdMovimientoOrigen' => null,
                    'Glosa' => $glosa,
                    'Observaciones' => 'Devolución por reclamo de calidad',
                    'OrigenPago' => 'SISTEMA',
                    'ReferenciaPago' => null,
                    'IdOperadorInserta' => $idOperadorInserta,
                    'FechaMovimiento' => now(),
                    'FechaCreacion' => now(),
                ]);

                $billetera->update([
                    'SaldoActual' => $saldoDespues,
                    'TotalCargado' => (float) $billetera->TotalCargado + $monto,
                    'IdOperadorActualiza' => $idOperadorInserta,
                    'FechaUltimaActualizacion' => now(),
                ]);

                $this->refresh();

                return $movimiento;
            });
    }
}