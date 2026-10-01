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
        'CantidadMinimaGrupo', // ✅ Se mantiene en la tabla pero ya no se usa
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

    // ==================== HELPERS ====================

    /**
     * ✅ NUEVO: Lista de IDs de grupos ACTIVOS.
     * Retorna: [IdGrupoAnalisis, IdGrupoAnalisis, ...]
     */
    public static function obtenerIdsActivos($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        $key = "grupos_activos_{$clienteId}_{$sucursalId}";

        return cache()->remember($key, 1800, function () use ($clienteId, $sucursalId) {
            return self::porContexto($clienteId, $sucursalId)
                ->activos()
                ->pluck('IdGrupoAnalisis')
                ->toArray();
        });
    }

    /**
     * ✅ Compatibilidad: alias de obtenerIdsActivos (por si algo lo usa)
     */
    public static function obtenerMapa($clienteId = null, $sucursalId = null)
    {
        return self::obtenerIdsActivos($clienteId, $sucursalId);
    }

    /**
     * ✅ NUEVO: ¿El grupo está activo?
     */
    public static function grupoEstaActivo($idGrupoAnalisis, $clienteId = null, $sucursalId = null)
    {
        $ids = self::obtenerIdsActivos($clienteId, $sucursalId);
        return in_array((int) $idGrupoAnalisis, array_map('intval', $ids));
    }

    public static function invalidarCache($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        cache()->forget("grupos_activos_{$clienteId}_{$sucursalId}");
        // Limpiar también la clave vieja por si acaso
        cache()->forget("minimos_grupoanalisis_{$clienteId}_{$sucursalId}");
    }

    // ==================== ACCESORS ====================

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Activo' : 'Inactivo';
    }
    /**
     * ✅ Lista de IDs de grupos de análisis activos + su nombre.
     * Útil para mostrar la pestaña de mínimos en grupos de clientes.
     * Retorna: [{IdGrupoAnalisis, Grupo}, ...]
     */
    public static function obtenerGruposActivosConNombre($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');

        $idsActivos = self::obtenerIdsActivos($clienteId, $sucursalId);
        $idsActivos = array_map('intval', $idsActivos);

        if (empty($idsActivos)) {
            return [];
        }

        return \App\Models\Gestion\Inventario\ProductoGrupoAnalisis::where('IdCliente', $clienteId)
            ->whereIn('IdGrupoAnalisis', $idsActivos)
            ->orderBy('Grupo')
            ->get(['IdGrupoAnalisis', 'Grupo'])
            ->map(function ($g) {
                return [
                    'IdGrupoAnalisis' => (int) $g->IdGrupoAnalisis,
                    'Grupo' => $g->Grupo,
                ];
            })
            ->toArray();
    }
}