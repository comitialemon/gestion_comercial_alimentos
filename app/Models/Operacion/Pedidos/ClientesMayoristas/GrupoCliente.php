<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;

class GrupoCliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_grupo_cliente';  // ✅ CORREGIDO
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

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    public function detalles()
    {
        return $this->hasMany(GrupoClienteDetalle::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    public function detallesActivos()
    {
        return $this->hasMany(GrupoClienteDetalle::class, 'IdGrupoCliente', 'IdGrupoCliente')
            ->where('ActivoInactivo', 1);
    }

    public function productos()
    {
        return $this->hasMany(GrupoClienteProducto::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    public function productosActivos()
    {
        return $this->hasMany(GrupoClienteProducto::class, 'IdGrupoCliente', 'IdGrupoCliente')
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

    // ==================== MÉTODOS HELPER ====================

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