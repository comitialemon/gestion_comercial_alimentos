<?php

namespace App\Models\Operacion\Pedidos\ClientesMayoristas;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contenedor extends Model
{
    use HasFactory;

    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_pedidos_clientes_contenedor';
    protected $primaryKey = 'IdContenedor';
    public $timestamps = false;

    protected $fillable = [
        'IdTipoContenedor',
        'Codigo',
        'CapacidadTotal',
        'ActivoInactivo',
        'IdCliente',
        'IdSucursal',
        'IdOperadorInserta',
        'FechaInserta',
        'IdOperadorActualiza',
        'FechaActualiza',
    ];

    protected $casts = [
        'CapacidadTotal' => 'decimal:2',
        'ActivoInactivo' => 'integer',
        'IdTipoContenedor' => 'integer',
    ];

    // ==================== SCOPES ====================

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

    public function scopePorOperador($query, $operadorId = null)
    {
        $operadorId = $operadorId ?? session('operador_id');
        return $query->where('IdOperadorInserta', $operadorId);
    }

    public function scopeBorradores($query)
    {
        return $query->where('ActivoInactivo', 0);
    }

    public function scopeActivos($query)
    {
        return $query->where('ActivoInactivo', 1);
    }

    public function scopeBorradorPorOperador($query)
    {
        return $query->porCliente()
            ->porSucursal()
            ->porOperador()
            ->borradores();
    }

    public static function existeBorradorActivo()
    {
        return self::borradorPorOperador()->exists();
    }

    public static function obtenerBorradorActivo()
    {
        return self::borradorPorOperador()->first();
    }

    public static function obtenerOCrearBorrador($data)
    {
        $borrador = self::borradorPorOperador()->first();

        if ($borrador) {
            $borrador->update($data);
            return $borrador;
        }

        return self::create($data);
    }

    // ==================== RELACIONES ====================

    /**
     * Relación con el tipo de contenedor
     */
    public function tipoContenedor()
    {
        return $this->belongsTo(ContenedorTipo::class, 'IdTipoContenedor', 'IdTipoContenedor');
    }

    /**
     * Relación con grupos de análisis (N:N)
     */
    public function gruposAnalisis()
    {
        return $this->belongsToMany(
            \App\Models\Gestion\Inventario\ProductoGrupoAnalisis::class,
            'operacion_pedidos_clientes_contenedor_grupo',
            'IdContenedor',
            'IdGrupoAnalisis'
        );
    }

    /**
     * Relación con sucursal
     */
    public function sucursal()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\ClienteSucursal::class, 'IdSucursal', 'IdClienteSucursal');
    }

    /**
     * Relación con cliente
     */
    public function cliente()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Cliente::class, 'IdCliente', 'IdCliente');
    }

    /**
     * Relación con operador que insertó
     */
    public function operadorInserta()
    {
        return $this->belongsTo(\App\Models\Gestion\Todos\Operador::class, 'IdOperadorInserta', 'IdOperador');
    }

    // ==================== RELACIONES CON PEDIDOS ====================

    /**
     * Relación con los detalles de pedidos (para saber en qué pedidos se usa este contenedor)
     */
    public function pedidosDetalles()
    {
        return $this->hasMany(PedidoClienteDetalle::class, 'IdContenedor', 'IdContenedor');
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

    public function getTipoNombreAttribute()
    {
        return $this->tipoContenedor ? $this->tipoContenedor->Nombre : '-';
    }

    public function getGruposNombresAttribute()
    {
        return $this->gruposAnalisis->pluck('Grupo')->implode(', ');
    }

    public function getTotalProductosAttribute()
    {
        return $this->contarProductosActivos();
    }

    // ==================== MÉTODOS PARA PRODUCTOS ====================

    /**
     * OBTENER TODOS LOS PRODUCTOS ACTIVOS DE TODOS LOS GRUPOS ASOCIADOS
     * Útil para el modal de pedidos
     */
    public function getProductosAttribute()
    {
        return \App\Models\Gestion\Inventario\ProductoDetalle::where('IdCliente', $this->IdCliente)
            ->whereIn('IdGrupoAnalisis', $this->gruposAnalisis->pluck('IdGrupoAnalisis'))
            ->where('ActivoInactivo', 0)
            ->orderBy('Descripcion')
            ->get();
    }

    /**
     * CONTAR PRODUCTOS ACTIVOS DE TODOS LOS GRUPOS ASOCIADOS
     */
    public function contarProductosActivos()
    {
        return \App\Models\Gestion\Inventario\ProductoDetalle::where('IdCliente', $this->IdCliente)
            ->whereIn('IdGrupoAnalisis', $this->gruposAnalisis->pluck('IdGrupoAnalisis'))
            ->where('ActivoInactivo', 0)
            ->count();
    }

    /**
     * OBTENER PRODUCTOS AGRUPADOS POR GRUPO DE ANÁLISIS
     * Útil para mostrar en el modal de pedidos con separación por grupos
     */
    public function getProductosAgrupadosAttribute()
    {
        $productos = $this->productos;
        
        if ($productos->isEmpty()) {
            return collect([]);
        }
        
        return $productos->groupBy('IdGrupoAnalisis')->map(function($items, $grupoId) {
            $grupo = \App\Models\Gestion\Inventario\ProductoGrupoAnalisis::find($grupoId);
            return [
                'grupo_id' => $grupoId,
                'grupo_nombre' => $grupo ? $grupo->Grupo : 'Sin grupo',
                'productos' => $items->map(function($producto) {
                    return [
                        'IdProducto' => $producto->IdProducto,
                        'Codigo' => $producto->Codigo,
                        'Descripcion' => $producto->Descripcion,
                        'Precio' => $producto->Precio,
                    ];
                })->values(),
            ];
        })->values();
    }

    /**
     * OBTENER PRODUCTOS CON CANTIDAD MÁXIMA (para el modal de pedidos)
     * La cantidad máxima es la CapacidadTotal del contenedor
     */
    public function getProductosConMaximoAttribute()
    {
        return $this->productos->map(function($producto) {
            return [
                'IdProducto' => $producto->IdProducto,
                'Codigo' => $producto->Codigo,
                'Descripcion' => $producto->Descripcion,
                'Precio' => $producto->Precio,
                'IdGrupoAnalisis' => $producto->IdGrupoAnalisis,
                'CantidadMaxima' => $this->CapacidadTotal, // La capacidad total del contenedor
            ];
        });
    }

    // ==================== MÉTODO PARA DEBUG ====================

    /**
     * OBTENER GRUPOS CON SUS PRODUCTOS (para debug)
     */
    public function getGruposConProductosAttribute()
    {
        $result = [];
        foreach ($this->gruposAnalisis as $grupo) {
            $productos = \App\Models\Gestion\Inventario\ProductoDetalle::where('IdCliente', $this->IdCliente)
                ->where('IdGrupoAnalisis', $grupo->IdGrupoAnalisis)
                ->where('ActivoInactivo', 0)
                ->count();
            
            $result[] = [
                'grupo' => $grupo->Grupo,
                'total_productos' => $productos,
            ];
        }
        return $result;
    }
}