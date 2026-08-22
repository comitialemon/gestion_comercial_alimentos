<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class Operador extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_operador';
    protected $primaryKey = 'IdOperador';
    public $timestamps = false;

    protected $fillable = [
        'IdIdentificador', 
        'Iniciales', 
        'Clave', 
        'NombreAcceso',
        'DireccionDomicilio', 
        'TelefonoDomicilio', 
        'NumeroCelular',
        'IdOperadorTipo', 
        'ActivoInactivo',
    ];

    // ==================== RELACIONES ====================
    
    /**
     * Obtiene el identificador (CI/NIT) del operador
     */
    public function identificador()
    {
        return $this->belongsTo(Identificador::class, 'IdIdentificador', 'IdIdentificador');
    }

    /**
     * Obtiene el tipo de operador
     */
    public function tipo()
    {
        return $this->belongsTo(OperadorTipo::class, 'IdOperadorTipo', 'IdOperadorTipo');
    }

    /**
     * Obtiene las empresas asignadas a este operador (a través de sucursaldb)
     */
    public function empresas()
    {
        return $this->belongsToMany(
            Cliente::class,
            'todos_operador_sucursaldb',
            'IdOperador',
            'IdCliente'
        )->distinct();
    }

    /**
     * Obtiene las sucursales asignadas a este operador
     */
    public function sucursales()
    {
        return $this->belongsToMany(
            ClienteSucursal::class,
            'todos_operador_sucursaldb',
            'IdOperador',
            'IdSucursal'
        )->distinct();
    }

    // ==================== SCOPES ====================
    
    /**
     * Scope para operadores activos (0 = Activo, 1 = Inactivo)
     */
    public function scopeActivo($query)
    {
        return $query->where('ActivoInactivo', 0);  // ✅ 0 = Activo
    }

    /**
     * Scope para operadores inactivos
     */
    public function scopeInactivo($query)
    {
        return $query->where('ActivoInactivo', 1);  // ✅ 1 = Inactivo
    }

    /**
     * Scope para operadores de tipo PedidoClientes
     */
    public function scopePedidoClientes($query)
    {
        return $query->whereHas('tipo', function($q) {
            $q->where('Detalle', 'PedidoClientes');
        })->where('ActivoInactivo', 0);  // ✅ 0 = Activo
    }

    /**
     * Scope para operadores de un tipo específico
     */
    public function scopePorTipo($query, $tipoDetalle)
    {
        return $query->whereHas('tipo', function($q) use ($tipoDetalle) {
            $q->where('Detalle', $tipoDetalle);
        });
    }

    // ==================== MÉTODOS ESTÁTICOS ====================
    
    /**
     * Obtener todos los operadores de tipo PedidoClientes (activos)
     */
    public static function getOperadoresPedidoClientes()
    {
        $cacheKey = 'operadores_pedido_clientes';
        
        return cache()->remember($cacheKey, 3600, function() {
            return self::with(['identificador' => function($query) {
                    $query->select('IdIdentificador', 'Nombre', 'CI_NIT');
                }])
                ->pedidoClientes()  // ✅ Ya usa 0 = Activo
                ->select('IdOperador', 'IdIdentificador')
                ->get()
                ->map(function($operador) {
                    return (object) [
                        'IdIdentificador' => $operador->IdIdentificador,
                        'Nombre' => $operador->identificador->Nombre ?? 'Sin nombre',
                        'CI_NIT' => $operador->identificador->CI_NIT ?? '',
                    ];
                });
        });
    }

    /**
     * Obtener el IdIdentificador de un operador
     */
    public static function getIdIdentificadorByOperador($operadorId)
    {
        $cacheKey = 'operador_identificador_' . $operadorId;
        
        if (cache()->has($cacheKey)) {
            return cache()->get($cacheKey);
        }
        
        $operador = self::where('IdOperador', $operadorId)
            ->where('ActivoInactivo', 0)  // ✅ 0 = Activo
            ->first();
        
        $idIdentificador = $operador ? $operador->IdIdentificador : null;
        
        cache()->put($cacheKey, $idIdentificador, 3600);
        
        return $idIdentificador;
    }

    // ==================== ACCESORS ====================
    
    /**
     * Obtener el estado como texto
     */
    public function getEstadoTextoAttribute()
    {
        return $this->ActivoInactivo == 0 ? 'Activo' : 'Inactivo';
    }

    /**
     * Obtener el estado como badge (para UI)
     */
    public function getEstadoBadgeAttribute()
    {
        return $this->ActivoInactivo == 0 
            ? 'bg-green-100 text-green-800' 
            : 'bg-red-100 text-red-800';
    }
}