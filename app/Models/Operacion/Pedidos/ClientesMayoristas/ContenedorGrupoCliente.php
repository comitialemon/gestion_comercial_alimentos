<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;

class ContenedorGrupoCliente extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_contenedor_grupo_cliente';
    protected $primaryKey = 'IdContenedorGrupoCliente';
    public $timestamps = false;

    protected $fillable = [
        'IdContenedor',
        'IdGrupoCliente',
        'IdCliente',
        'IdSucursal',
        'ActivoInactivo',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'IdContenedor' => 'integer',
        'IdGrupoCliente' => 'integer',
        'IdCliente' => 'integer',
        'IdSucursal' => 'integer',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    public function contenedor()
    {
        return $this->belongsTo(Contenedor::class, 'IdContenedor', 'IdContenedor');
    }

    public function grupoCliente()
    {
        return $this->belongsTo(GrupoCliente::class, 'IdGrupoCliente', 'IdGrupoCliente');
    }

    // ==================== SCOPES ====================

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopePorContenedor($query, $idContenedor)
    {
        return $query->where('IdContenedor', $idContenedor);
    }

    public function scopePorGrupo($query, $idGrupoCliente)
    {
        return $query->where('IdGrupoCliente', $idGrupoCliente);
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
}