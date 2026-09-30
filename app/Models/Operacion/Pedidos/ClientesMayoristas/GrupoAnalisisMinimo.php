<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;

class GrupoAnalisisMinimo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_grupoanalisis_minimo';
    protected $primaryKey = 'IdGrupoAnalisisMinimo';
    public $timestamps = false;

    protected $fillable = [
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
        'IdCliente' => 'integer',
        'IdSucursal' => 'integer',
        'IdGrupoAnalisis' => 'integer',
        'CantidadMinimaGrupo' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function grupoAnalisis()
    {
        return $this->belongsTo(ProductoGrupoAnalisis::class, 'IdGrupoAnalisis', 'IdGrupoAnalisis');
    }

    // ==================== SCOPES ====================

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
     * Mapa de mínimos por grupo de análisis: [IdGrupoAnalisis => CantidadMinima]
     */
    public static function obtenerMapa($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        $key = "minimos_grupoanalisis_{$clienteId}_{$sucursalId}";

        return cache()->remember($key, 1800, function () use ($clienteId, $sucursalId) {
            return self::porContexto($clienteId, $sucursalId)
                ->activos()
                ->conMinimo()
                ->pluck('CantidadMinimaGrupo', 'IdGrupoAnalisis')
                ->toArray();
        });
    }

    public static function invalidarCache($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        cache()->forget("minimos_grupoanalisis_{$clienteId}_{$sucursalId}");
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