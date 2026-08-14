<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Contenedor extends Model
{
    use HasFactory;

    protected $table = 'operacion_pedidos_clientes_contenedor';
    protected $primaryKey = 'IdContenedor';
    public $timestamps = false;

    protected $fillable = [
        'Codigo',
        'Nombre',
        'CapacidadTotal',
        'ActivoInactivo',
        'IdCliente',
        'IdSucursal',
        'IdOperadorInserta',
        'FechaInserta',
    ];

    protected $casts = [
        'CapacidadTotal' => 'decimal:2',
        'ActivoInactivo' => 'integer',
    ];

    // ==================== SCOPES ====================

    /**
     * Scope para filtrar por cliente
     */
    public function scopePorCliente($query, $clienteId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        return $query->where('IdCliente', $clienteId);
    }

    /**
     * Scope para filtrar por sucursal
     */
    public function scopePorSucursal($query, $sucursalId = null)
    {
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        return $query->where('IdSucursal', $sucursalId);
    }

    /**
     * Scope para filtrar por operador
     */
    public function scopePorOperador($query, $operadorId = null)
    {
        $operadorId = $operadorId ?? session('operador_id');
        return $query->where('IdOperadorInserta', $operadorId);
    }

    /**
     * Scope para obtener solo borradores (inactivos)
     */
    public function scopeBorradores($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    /**
     * Scope para obtener solo activos
     */
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    /**
     * ✅ Scope para obtener el borrador del operador actual en su sucursal
     * Útil para saber si ya existe un borrador abierto
     */
    public function scopeBorradorPorOperador($query)
    {
        return $query->porCliente()
            ->porSucursal()
            ->porOperador()
            ->borradores();
    }

    /**
     * ✅ Verifica si existe un borrador para el operador actual
     */
    public static function existeBorradorActivo()
    {
        return self::borradorPorOperador()->exists();
    }

    /**
     * ✅ Obtiene el borrador del operador actual o null
     */
    public static function obtenerBorradorActivo()
    {
        return self::borradorPorOperador()->first();
    }

    /**
     * ✅ Crea un nuevo borrador o devuelve el existente
     */
    public static function obtenerOCrearBorrador($data)
    {
        $borrador = self::borradorPorOperador()->first();

        if ($borrador) {
            // Si existe, actualizar datos (por si cambió algo)
            $borrador->update($data);
            return $borrador;
        }

        // Si no existe, crear uno nuevo
        return self::create($data);
    }

    // ==================== RELACIONES ====================

    public function detalles()
    {
        return $this->hasMany(ContenedorDetalle::class, 'IdContenedor', 'IdContenedor');
    }

    public function sucursal()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function cliente()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Cliente::class, 'IdCliente', 'IdCliente');
    }

    public function operadorInserta()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Operador::class, 'IdOperadorInserta', 'IdOperador');
    }

    // ==================== ACCESORS ====================

    public function getCapacidadTotalFormateadaAttribute()
    {
        return number_format($this->CapacidadTotal, 2, ',', '.');
    }

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Activo' : 'Borrador';
    }

    public function getEstadoColorAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'success' : 'warning';
    }

    public function calcularTotalUnidades()
    {
        return $this->detalles->sum('Cantidad');
    }
}