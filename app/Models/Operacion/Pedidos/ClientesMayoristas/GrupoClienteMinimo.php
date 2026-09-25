<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;

class GrupoClienteMinimo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_grupo_cliente_minimo';
    protected $primaryKey = 'IdGrupoClienteMinimo';
    public $timestamps = false;

    protected $fillable = [
        'IdGrupoCliente',
        'IdGrupoAnalisis',
        'CantidadMinimaGrupo',
        'ActivoInactivo',
        'IdOperadorInserta',
        'IdOperadorActualiza',
        'FechaInserta',
        'FechaActualiza',
    ];

    protected $casts = [
        'IdGrupoClienteMinimo' => 'integer',
        'IdGrupoCliente' => 'integer',
        'IdGrupoAnalisis' => 'integer',
        'CantidadMinimaGrupo' => 'decimal:2',
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
     * Grupo de análisis
     */
    public function grupoAnalisis()
    {
        return $this->belongsTo(ProductoGrupoAnalisis::class, 'IdGrupoAnalisis', 'IdGrupoAnalisis');
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

    public function scopePorGrupoAnalisis($query, $idGrupoAnalisis)
    {
        return $query->where('IdGrupoAnalisis', $idGrupoAnalisis);
    }

    // ==================== ACCESORS ====================

    public function getCantidadMinimaFormateadaAttribute()
    {
        return number_format($this->CantidadMinimaGrupo, 2, ',', '.');
    }

    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 1 ? 'Activo' : 'Inactivo';
    }
}