<?php

namespace App\Models\Gestion\Todos;

use Illuminate\Database\Eloquent\Model;

class OperadorTipo extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'todos_operador_tipo';
    protected $primaryKey = 'IdOperadorTipo';
    public $timestamps = false;

    protected $fillable = [
        'Detalle',
    ];

    // ==================== RELACIONES ====================
    
    public function operadores()
    {
        return $this->hasMany(Operador::class, 'IdOperadorTipo', 'IdOperadorTipo');
    }

    /**
     * ✅ NUEVO: Obtener el tipo PedidoClientes
     */
    public static function getPedidoClientesTipo()
    {
        $cacheKey = 'operador_tipo_pedido_clientes';
        
        return cache()->remember($cacheKey, 3600, function() {
            return self::where('Detalle', 'PedidoClientes')->first();
        });
    }
}