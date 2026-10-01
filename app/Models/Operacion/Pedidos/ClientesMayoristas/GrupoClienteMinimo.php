<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;

class GrupoClienteMinimo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_grupo_cliente_minimo';
    protected $primaryKey = 'IdGrupoClienteMinimo';
    public $timestamps = false;

    protected $fillable = [
        'IdGrupoCliente',
        'IdCliente',
        'IdSucursal',
        'IdGrupoAnalisis',
        'CantidadMinimaGrupo',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'IdGrupoClienteMinimo' => 'integer',
        'IdGrupoCliente' => 'integer',
        'IdCliente' => 'integer',
        'IdSucursal' => 'integer',
        'IdGrupoAnalisis' => 'integer',
        'CantidadMinimaGrupo' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function grupoCliente()
    {
        return $this->belongsTo(GrupoCliente::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    public function grupoAnalisis()
    {
        return $this->belongsTo(ProductoGrupoAnalisis::class, 'IdGrupoAnalisis', 'IdGrupoAnalisis');
    }

    // ==================== SCOPES ====================

    public function scopePorGrupoCliente($query, $idGrupoCliente)
    {
        return $query->where('IdGrupoCliente', $idGrupoCliente);
    }

    public function scopePorContexto($query, $clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        return $query->where('IdCliente', $clienteId)->where('IdSucursal', $sucursalId);
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeConMinimo($query)
    {
        return $query->where('CantidadMinimaGrupo', '>', 0);
    }

    // ==================== HELPERS ====================

    /**
     * ✅ Mapa de mínimos por grupo de análisis para un grupo de clientes.
     * Retorna: [IdGrupoAnalisis => CantidadMinimaGrupo]
     */
    public static function obtenerMapaPorGrupoCliente($idGrupoCliente, $clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        $key = "minimos_grupo_cliente_{$idGrupoCliente}_{$clienteId}_{$sucursalId}";

        return cache()->remember($key, 1800, function () use ($idGrupoCliente, $clienteId, $sucursalId) {
            return self::porGrupoCliente($idGrupoCliente)
                ->porContexto($clienteId, $sucursalId)
                ->activos()
                ->conMinimo()
                ->pluck('CantidadMinimaGrupo', 'IdGrupoAnalisis')
                ->toArray();
        });
    }

    /**
     * ✅ Lista de IDs de grupos de análisis que aplican (tienen mínimo > 0) para un grupo de clientes.
     */
    public static function obtenerIdsAplicables($idGrupoCliente, $clienteId = null, $sucursalId = null)
    {
        $mapa = self::obtenerMapaPorGrupoCliente($idGrupoCliente, $clienteId, $sucursalId);
        return array_keys($mapa);
    }

    public static function invalidarCache($idGrupoCliente = null, $clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');

        if ($idGrupoCliente) {
            cache()->forget("minimos_grupo_cliente_{$idGrupoCliente}_{$clienteId}_{$sucursalId}");
        }
    }

    // ==================== ACCESORS ====================

    public function getCantidadFormateadaAttribute()
    {
        return number_format($this->CantidadMinimaGrupo, 2, ',', '.');
    }

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Activo' : 'Inactivo';
    }
}