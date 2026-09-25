<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;

class GrupoClienteDetalle extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_grupo_cliente_detalle';
    protected $primaryKey = 'IdGrupoClienteDetalle';
    public $timestamps = false;

    protected $fillable = [
        'IdGrupoCliente',
        'IdIdentificador',
        'IdCliente',
        'IdSucursal',
        'ActivoInactivo',
        'IdOperadorInserta',
        'IdOperadorActualiza',
        'FechaInserta',
        'FechaActualiza',
    ];

    protected $casts = [
        'IdGrupoClienteDetalle' => 'integer',
        'IdGrupoCliente' => 'integer',
        'IdIdentificador' => 'integer',
        'IdCliente' => 'integer',
        'IdSucursal' => 'integer',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    /**
     * Grupo al que pertenece
     */
    public function grupoCliente()
    {
        return $this->belongsTo(GrupoCliente::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    /**
     * Cliente/Operador (identificador)
     */
    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
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

    public function scopePorGrupo($query, $idGrupoCliente)
    {
        return $query->where('IdGrupoCliente', $idGrupoCliente);
    }

    public function scopePorIdentificador($query, $idIdentificador)
    {
        return $query->where('IdIdentificador', $idIdentificador);
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

    public function getFechaInsertaFormateadaAttribute()
    {
        return $this->FechaInserta 
            ? \Carbon\Carbon::parse($this->FechaInserta)->format('d/m/Y H:i') 
            : '-';
    }
}