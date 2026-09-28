<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Operador;

class PedidoClienteSubCliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'pedidos_clientes_subclientes';
    protected $primaryKey = 'IdSubClienteOperador';
    public $timestamps = false;

    protected $fillable = [
        'IdOperador',
        'IdIdentificador',
        'Alias',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function operador()
    {
        return $this->belongsTo(Operador::class, 'IdOperador', 'IdOperador');
    }

    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    public function detalles()
    {
        return $this->hasMany(PedidoClienteDetalle::class, 'IdSubClienteOperador', 'IdSubClienteOperador');
    }

    // ==================== SCOPES ====================

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopePorOperador($query, $operadorId = null)
    {
        $operadorId = $operadorId ?? session('operador_id');
        return $query->where('IdOperador', $operadorId);
    }

    // ==================== HELPERS ====================

    /**
     * ✅ OPTIMIZADO: Obtener subclientes del operador (con caché)
     */
    public static function obtenerSubClientesDelOperador($operadorId = null)
    {
        $operadorId = $operadorId ?? session('operador_id');
        $cacheKey = "subclientes_operador_{$operadorId}";

        return cache()->remember($cacheKey, 300, function () use ($operadorId) {
            return self::porOperador($operadorId)
                ->activos()
                ->with(['identificador' => function ($q) {
                    $q->select('IdIdentificador', 'Nombre', 'CI_NIT');
                }])
                ->select('IdSubClienteOperador', 'IdOperador', 'IdIdentificador', 'Alias')
                ->get()
                ->map(function ($sub) {
                    return [
                        'IdSubClienteOperador' => (int) $sub->IdSubClienteOperador,
                        'IdIdentificador' => (int) $sub->IdIdentificador,
                        'Nombre' => $sub->Alias ?: ($sub->identificador->Nombre ?? 'Sin nombre'),
                        'CI_NIT' => $sub->identificador->CI_NIT ?? '',
                    ];
                })
                ->values()
                ->toArray();
        });
    }

    /**
     * ✅ OPTIMIZADO: Obtener el subcliente por defecto (con caché + 1 sola query)
     */
    public static function obtenerSubClientePorDefecto($operadorId = null)
    {
        $operadorId = $operadorId ?? session('operador_id');
        $cacheKey = "subcliente_default_{$operadorId}";

        return cache()->remember($cacheKey, 3600, function () use ($operadorId) {
            $sub = \DB::connection('mysql_gestion_comercial_alimentos')
                ->table('pedidos_clientes_subclientes as s')
                ->join('todos_operador as o', 'o.IdIdentificador', '=', 's.IdIdentificador')
                ->where('o.IdOperador', $operadorId)
                ->where('s.IdOperador', $operadorId)
                ->where('s.ActivoInactivo', 1)
                ->first(['s.IdSubClienteOperador']);

            return $sub ? (int) $sub->IdSubClienteOperador : null;
        });
    }

    /**
     * ✅ OPTIMIZADO: Auto-registrar al operador como subcliente de sí mismo
     *    (con caché para no correr cada request)
     */
    public static function asegurarSubClientePropio($operadorId = null)
    {
        $operadorId = $operadorId ?? session('operador_id');
        $cacheKey = "subcliente_propio_ok_{$operadorId}";

        // ✅ Si ya verificamos hace poco, no hacer nada
        if (cache()->has($cacheKey)) {
            return;
        }

        $operador = \DB::connection('mysql_gestion_comercial_alimentos')
            ->table('todos_operador')
            ->where('IdOperador', $operadorId)
            ->first(['IdIdentificador']);

        if (!$operador || !$operador->IdIdentificador) {
            return;
        }

        $existe = self::where('IdOperador', $operadorId)
            ->where('IdIdentificador', $operador->IdIdentificador)
            ->exists();

        if (!$existe) {
            try {
                \DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('pedidos_clientes_subclientes')
                    ->insertOrIgnore([
                        'IdOperador' => $operadorId,
                        'IdIdentificador' => $operador->IdIdentificador,
                        'Alias' => null,
                        'ActivoInactivo' => 1,
                        'IdOperadorInserta' => $operadorId,
                        'FechaInserta' => now(),
                    ]);
            } catch (\Exception $e) {
                \Log::warning("Error al auto-registrar subcliente propio: " . $e->getMessage());
            }
        }

        // ✅ Cachear 1 hora
        cache()->put($cacheKey, true, 3600);
    }

    /**
     * ✅ NUEVO: Invalidar cachés del operador
     *    Llamar después de crear/editar/eliminar un subcliente
     */
    public static function invalidarCacheOperador($operadorId = null)
    {
        $operadorId = $operadorId ?? session('operador_id');
        cache()->forget("subclientes_operador_{$operadorId}");
        cache()->forget("subcliente_default_{$operadorId}");
        cache()->forget("subcliente_propio_ok_{$operadorId}");
    }
}