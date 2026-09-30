<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;

class ProductoMinimo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_producto_minimo';
    protected $primaryKey = 'IdProductoMinimo';
    public $timestamps = false;

    protected $fillable = [
        'IdCliente',
        'IdSucursal',
        'IdGrupoAnalisis',
        'IdProducto',
        'CantidadMinimaProducto',
        'DisponibleParaPedido',
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
        'IdProducto' => 'integer',
        'CantidadMinimaProducto' => 'decimal:2',
        'DisponibleParaPedido' => 'integer',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function producto()
    {
        return $this->belongsTo(ProductoDetalle::class, 'IdProducto', 'IdProducto');
    }

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
        return $query->where('CantidadMinimaProducto', '>', 0);
    }

    public function scopeDisponibles($query)
    {
        return $query->where('DisponibleParaPedido', 1);
    }

    public function scopePorGrupoAnalisis($query, $idGrupoAnalisis)
    {
        return $query->where('IdGrupoAnalisis', $idGrupoAnalisis);
    }

    // ==================== HELPERS ====================

    /**
     * Mapa de mínimos por producto: [IdProducto => CantidadMinima]
     */
    public static function obtenerMapa($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        $key = "minimos_producto_{$clienteId}_{$sucursalId}";

        return cache()->remember($key, 1800, function () use ($clienteId, $sucursalId) {
            return self::porContexto($clienteId, $sucursalId)
                ->activos()
                ->conMinimo()
                ->pluck('CantidadMinimaProducto', 'IdProducto')
                ->toArray();
        });
    }

    /**
     * Lista de IDs de productos configurados y disponibles para pedido.
     * [IdProducto, IdProducto, ...]
     */
    public static function obtenerIdsDisponibles($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        $key = "disponibles_producto_{$clienteId}_{$sucursalId}";

        return cache()->remember($key, 1800, function () use ($clienteId, $sucursalId) {
            return self::porContexto($clienteId, $sucursalId)
                ->activos()
                ->disponibles()
                ->where('CantidadMinimaProducto', '>', 0)
                ->pluck('IdProducto')
                ->toArray();
        });
    }

    /**
     * Lista de IDs de productos pausados (DisponibleParaPedido = 0).
     */
    public static function obtenerIdsPausados($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        $key = "pausados_producto_{$clienteId}_{$sucursalId}";

        return cache()->remember($key, 1800, function () use ($clienteId, $sucursalId) {
            return self::porContexto($clienteId, $sucursalId)
                ->activos()
                ->where('DisponibleParaPedido', 0)
                ->pluck('IdProducto')
                ->toArray();
        });
    }

    /**
     * Mapa de mínimos agrupado por IdGrupoAnalisis.
     */
    public static function obtenerMapaAgrupado($clienteId = null, $sucursalId = null)
    {
        $mapa = self::obtenerMapa($clienteId, $sucursalId);

        if (empty($mapa)) {
            return [];
        }

        $productos = ProductoDetalle::whereIn('IdProducto', array_keys($mapa))
            ->get(['IdProducto', 'IdGrupoAnalisis'])
            ->keyBy('IdProducto');

        $agrupado = [];
        foreach ($mapa as $idProducto => $cantidad) {
            $prod = $productos[$idProducto] ?? null;
            if (!$prod) continue;

            $idGrupo = $prod->IdGrupoAnalisis;
            if (!isset($agrupado[$idGrupo])) {
                $agrupado[$idGrupo] = [];
            }
            $agrupado[$idGrupo][$idProducto] = $cantidad;
        }

        return $agrupado;
    }

    public static function invalidarCache($clienteId = null, $sucursalId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        cache()->forget("minimos_producto_{$clienteId}_{$sucursalId}");
        cache()->forget("disponibles_producto_{$clienteId}_{$sucursalId}");
        cache()->forget("pausados_producto_{$clienteId}_{$sucursalId}");
    }

    // ==================== ACCESORS ====================

    public function getCantidadFormateadaAttribute()
    {
        return number_format($this->CantidadMinimaProducto, 2, ',', '.');
    }

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Activo' : 'Inactivo';
    }

    public function getDisponibleTextoAttribute()
    {
        return $this->DisponibleParaPedido == 1 ? 'Disponible' : 'Pausado';
    }

    public function getDisponibleBadgeAttribute()
    {
        return $this->DisponibleParaPedido == 1
            ? 'bg-green-100 text-green-800'
            : 'bg-yellow-100 text-yellow-800';
    }
}