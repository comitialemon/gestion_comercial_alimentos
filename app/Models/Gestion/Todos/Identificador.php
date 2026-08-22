<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class Identificador extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_identificador';
    protected $primaryKey = 'IdIdentificador';
    public $timestamps = false;

    protected $fillable = [
        'CI_NIT',
        'Nombre',
        'IdOperadorIngreso',
        'FechaIngreso',
        'IdOperadorEdita',
        'FechaEdita',
    ];

    // ==================== RELACIONES ====================
    
    public function operador()
    {
        return $this->hasOne(Operador::class, 'IdIdentificador', 'IdIdentificador');
    }

    // ==================== SCOPES ====================
    
    public function scopePorNit($query, $nit)
    {
        return $query->where('CI_NIT', $nit);
    }

    /**
     * ✅ Scope: Solo identificadores que tienen operador tipo PedidoClientes activo
     */
    public function scopeConOperadorPedidoClientes($query)
    {
        return $query->whereHas('operador', function($q) {
            $q->whereHas('tipo', function($t) {
                $t->where('Detalle', 'PedidoClientes');
            })->where('ActivoInactivo', 0);  // ✅ 0 = Activo
        });
    }

    /**
     * ✅ OBTENER IDENTIFICADORES (SIN CACHÉ - PARA DEBUG)
     */
    public static function getIdentificadoresPedidoClientes()
    {
        // ✅ PRIMERO: LIMPIAR CACHÉ
        cache()->forget('identificadores_pedido_clientes');
        
        // ✅ SEGUNDO: CONSULTA DIRECTA SIN CACHÉ
        $result = self::conOperadorPedidoClientes()
            ->select('IdIdentificador', 'Nombre', 'CI_NIT')
            ->orderBy('Nombre')
            ->get();
        
        // ✅ TERCERO: LOG PARA DEBUG
        \Log::info('=== IDENTIFICADORES DESDE MODELO ===');
        \Log::info('Cantidad: ' . $result->count());
        \Log::info('Datos:', $result->toArray());
        
        return $result;
    }
}