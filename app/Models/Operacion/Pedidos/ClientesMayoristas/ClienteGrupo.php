<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Todos\Identificador;
use App\Models\Gestion\Todos\Cliente;
use App\Models\Gestion\Todos\ClienteSucursal;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;

class ClienteGrupo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_cliente_grupo';
    protected $primaryKey = 'IdClienteGrupo';
    public $timestamps = false;

    protected $fillable = [
        'IdIdentificador',
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
        'IdIdentificador' => 'integer',
        'IdCliente' => 'integer',
        'IdSucursal' => 'integer',
        'IdGrupoAnalisis' => 'integer',
        'CantidadMinimaGrupo' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'FechaInserta' => 'datetime',
        'FechaActualiza' => 'datetime',
    ];

    // ==================== RELACIONES ====================

    /**
     * Relación con el Identificador (persona/cliente final)
     */
    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    /**
     * Relación con el Cliente (empresa)
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'IdCliente', 'IdCliente');
    }

    /**
     * Relación con la Sucursal
     */
    public function sucursal()
    {
        return $this->belongsTo(ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    /**
     * Relación con el Grupo de Análisis
     */
    public function grupoAnalisis()
    {
        return $this->belongsTo(ProductoGrupoAnalisis::class, 'IdGrupoAnalisis', 'IdGrupoAnalisis');
    }

    // ==================== SCOPES ====================

    /**
     * Solo registros activos
     */
    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    /**
     * Solo registros inactivos
     */
    public function scopeInactivos($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    /**
     * Filtrar por identificador (cliente final)
     */
    public function scopePorIdentificador($query, $identificadorId)
    {
        return $query->where('IdIdentificador', $identificadorId);
    }

    /**
     * Filtrar por cliente (empresa)
     */
    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('IdCliente', $clienteId);
    }

    /**
     * Filtrar por sucursal
     */
    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('IdSucursal', $sucursalId);
    }

    /**
     * Filtrar por grupo de análisis
     */
    public function scopePorGrupo($query, $grupoId)
    {
        return $query->where('IdGrupoAnalisis', $grupoId);
    }

    /**
     * Filtrar por varios grupos a la vez
     */
    public function scopePorGrupos($query, array $gruposIds)
    {
        return $query->whereIn('IdGrupoAnalisis', $gruposIds);
    }

    // ==================== ACCESORS ====================

    /**
     * Cantidad mínima formateada (ej: 360.00)
     */
    public function getCantidadMinimaFormateadaAttribute()
    {
        return number_format($this->CantidadMinimaGrupo, 2, ',', '.');
    }

    /**
     * Estado en texto (Activo / Inactivo)
     */
    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Activo' : 'Inactivo';
    }
}