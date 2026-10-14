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
        'EsProductoUnico',   // ✅ NUEVO: 1 = rejilla, 0 = normal
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
        'CapacidadTotal'  => 'decimal:2',
        'ActivoInactivo'  => 'integer',
        'IdTipoContenedor'=> 'integer',
        'EsProductoUnico' => 'boolean',   // ✅ NUEVO
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

    /**
     * ✅ NUEVO SCOPE: Solo contenedores de producto único (rejilla)
     */
    public function scopeProductoUnico($query)
    {
        return $query->where('EsProductoUnico', 1);
    }

    /**
     * ✅ NUEVO SCOPE: Solo contenedores normales (varios productos)
     */
    public function scopeNormales($query)
    {
        return $query->where('EsProductoUnico', 0);
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

    public function tipoContenedor()
    {
        return $this->belongsTo(ContenedorTipo::class, 'IdTipoContenedor', 'IdTipoContenedor');
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

    public function pedidosDetalles()
    {
        return $this->hasMany(PedidoClienteDetalle::class, 'IdContenedor', 'IdContenedor');
    }

    public function gruposClientes()
    {
        return $this->hasMany(ContenedorGrupoCliente::class, 'IdContenedor', 'IdContenedor');
    }

    public function gruposClientesActivos()
    {
        return $this->hasMany(ContenedorGrupoCliente::class, 'IdContenedor', 'IdContenedor')
            ->where('ActivoInactivo', 1);
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

    /**
     * ✅ NUEVO ACCESOR: Texto legible del tipo de contenedor
     * Devuelve "Producto Único" o "Normal"
     */
    public function getTipoContenedorTextoAttribute()
    {
        return $this->EsProductoUnico ? 'Producto Único' : 'Normal';
    }

    public function getTotalGruposAttribute()
    {
        return $this->gruposClientesActivos()->count();
    }

    public function getTotalClientesAttribute()
    {
        $total = 0;
        foreach ($this->gruposClientesActivos as $grupoContenedor) {
            $grupo = $grupoContenedor->grupoCliente;
            if ($grupo) {
                $total += GrupoClienteDetalle::where('IdGrupoCliente', $grupo->IdGrupoCliente)
                    ->where('ActivoInactivo', 1)
                    ->count();
            }
        }
        return $total;
    }
}