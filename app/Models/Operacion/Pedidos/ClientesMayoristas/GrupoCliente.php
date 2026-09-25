<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;

class GrupoCliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_grupo_cliente';
    protected $primaryKey = 'IdGrupoCliente';
    public $timestamps = false;

    protected $fillable = [
        'Nombre',
        'Descripcion',
        'IdCliente',
        'IdSucursal',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'IdGrupoCliente' => 'integer',
        'IdCliente' => 'integer',
        'IdSucursal' => 'integer',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    /**
     * Cliente (empresa)
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    /**
     * Sucursal
     */
    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    /**
     * Detalles (clientes/identificadores del grupo)
     */
    public function detalles()
    {
        return $this->hasMany(GrupoClienteDetalle::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    /**
     * Detalles activos
     */
    public function detallesActivos()
    {
        return $this->hasMany(GrupoClienteDetalle::class, 'IdGrupoCliente', 'IdGrupoCliente')
            ->where('ActivoInactivo', 1);
    }

    /**
     * Productos con precios del grupo
     */
    public function productos()
    {
        return $this->hasMany(GrupoClienteProducto::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    /**
     * Productos activos
     */
    public function productosActivos()
    {
        return $this->hasMany(GrupoClienteProducto::class, 'IdGrupoCliente', 'IdGrupoCliente')
            ->where('ActivoInactivo', 1);
    }

    /**
     * Mínimos por grupo de análisis
     */
    public function minimos()
    {
        return $this->hasMany(GrupoClienteMinimo::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    /**
     * Mínimos activos
     */
    public function minimosActivos()
    {
        return $this->hasMany(GrupoClienteMinimo::class, 'IdGrupoCliente', 'IdGrupoCliente')
            ->where('ActivoInactivo', 1);
    }

    // ==================== SCOPES ====================

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeInactivos($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    public function scopePorCliente($query, $clienteId = null)
    {
        $clienteId = $clienteId ?? session('cliente_id');
        return $query->where('IdCliente', $clienteId);
    }

    public function scopePorSucursal($query, $sucursalId = null)
    {
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        return $query->where('IdSucursal', $sucursalId);
    }

    // ==================== ACCESORS ====================

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Activo' : 'Inactivo';
    }

    public function getEstadoColorAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'success' : 'danger';
    }

    public function getFechaInsertaFormateadaAttribute()
    {
        return $this->FechaInserta 
            ? \Carbon\Carbon::parse($this->FechaInserta)->format('d/m/Y H:i') 
            : '-';
    }

    public function getTotalClientesAttribute()
    {
        return $this->detallesActivos()->count();
    }

    public function getTotalProductosAttribute()
    {
        return $this->productosActivos()->count();
    }

    public function getTotalMinimosAttribute()
    {
        return $this->minimosActivos()->count();
    }

    // ==================== MÉTODOS HELPER ====================

    /**
     * Buscar el grupo al que pertenece un cliente (identificador).
     * Un cliente solo puede estar en 1 grupo.
     */
    public static function obtenerGrupoDeIdentificador($idIdentificador, $idCliente, $idSucursal)
    {
        $detalle = GrupoClienteDetalle::where('IdIdentificador', $idIdentificador)
            ->where('IdCliente', $idCliente)
            ->where('IdSucursal', $idSucursal)
            ->where('ActivoInactivo', 1)
            ->first();

        if (!$detalle) {
            return null;
        }

        return self::find($detalle->IdGrupoCliente);
    }
}