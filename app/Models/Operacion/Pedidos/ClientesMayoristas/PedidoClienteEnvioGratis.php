<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class PedidoClienteEnvioGratis extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_envios_gratis';
    protected $primaryKey = 'IdEnvioGratis';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdSucursal',
        'IdIdentificador',
        'DiaSemana',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'DiaSemana'       => 'integer',
        'ActivoInactivo'  => 'integer',
    ];

    // ==================== HELPERS ====================

    /**
     * Devuelve el nombre del día a partir del número ISO-8601.
     * 1=Lunes ... 7=Domingo.
     */
    public static function dias(): array
    {
        return [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
    }

    /**
     * Devuelve los días gratis activos de un cliente.
     * Retorna array de enteros: [1, 3, 5]
     */
    public static function diasGratisDe(int $clienteId, int $sucursalId, int $identificadorId): array
    {
        return self::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdIdentificador', $identificadorId)
            ->where('ActivoInactivo', 0)
            ->orderBy('DiaSemana')
            ->pluck('DiaSemana')
            ->map(fn ($d) => (int) $d)
            ->toArray();
    }

    /**
     * ¿Un cliente tiene envío gratis un día de la semana específico?
     */
    public static function esDiaGratis(int $clienteId, int $sucursalId, int $identificadorId, int $diaSemana): bool
    {
        return self::where('IdCliente', $clienteId)
            ->where('IdSucursal', $sucursalId)
            ->where('IdIdentificador', $identificadorId)
            ->where('DiaSemana', $diaSemana)
            ->where('ActivoInactivo', 0)
            ->exists();
    }

    /**
     * ¿Una fecha (Carbon o string) cae en día gratis para el cliente?
     * Se usa al momento de despachar para decidir si se cobra o no.
     */
    public static function esFechaGratis(int $clienteId, int $sucursalId, int $identificadorId, $fecha): bool
    {
        if (!$fecha) return false;
        try {
            $carbon = \Carbon\Carbon::parse($fecha);
            $diaSemana = (int) $carbon->format('N'); // 1=Lunes ... 7=Domingo
            return self::esDiaGratis($clienteId, $sucursalId, $identificadorId, $diaSemana);
        } catch (\Exception $e) {
            return false;
        }
    }
}