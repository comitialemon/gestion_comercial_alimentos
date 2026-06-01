<?php

namespace App\Models\Operacion\Produccion;

use Illuminate\Database\Eloquent\Model;
use App\Models\Gestion\Inventario\ProductoDetalle;

class Cronograma extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'operacion_produccion_cronograma';
    protected $primaryKey = 'IdCronograma';
    public $timestamps = false;

    protected $fillable = [
        'Lunes',
        'Martes',
        'Miercoles',
        'Jueves',
        'Viernes',
        'Sabado',
        'Domingo',
        'IdCliente',
        'IdOperador',
    ];

    protected $casts = [
        'Lunes' => 'integer',
        'Martes' => 'integer',
        'Miercoles' => 'integer',
        'Jueves' => 'integer',
        'Viernes' => 'integer',
        'Sabado' => 'integer',
        'Domingo' => 'integer',
    ];

    // ==================== SCOPES ====================
    
    /**
     * Scope para filtrar por cliente actual
     */
    public function scopePorContexto($query)
    {
        return $query->where('IdCliente', session('cliente_id'));
    }

    /**
     * Scope para filtrar por operador actual
     */
    public function scopePorOperador($query)
    {
        return $query->where('IdOperador', session('operador_id'));
    }

    /**
     * Scope para obtener solo registros activos
     */
    public function scopeActivos($query)
    {
        return $query->where('Activo', 1);
    }

    // ==================== RELACIONES ====================
    
    public function producto_lunes()
    {
        return $this->belongsTo(ProductoDetalle::class, 'Lunes', 'IdProducto');
    }

    public function producto_martes()
    {
        return $this->belongsTo(ProductoDetalle::class, 'Martes', 'IdProducto');
    }

    public function producto_miercoles()
    {
        return $this->belongsTo(ProductoDetalle::class, 'Miercoles', 'IdProducto');
    }

    public function producto_jueves()
    {
        return $this->belongsTo(ProductoDetalle::class, 'Jueves', 'IdProducto');
    }

    public function producto_viernes()
    {
        return $this->belongsTo(ProductoDetalle::class, 'Viernes', 'IdProducto');
    }

    public function producto_sabado()
    {
        return $this->belongsTo(ProductoDetalle::class, 'Sabado', 'IdProducto');
    }

    public function producto_domingo()
    {
        return $this->belongsTo(ProductoDetalle::class, 'Domingo', 'IdProducto');
    }

    // ==================== MÉTODOS AUXILIARES ====================
    
    /**
     * Obtener el producto para un día específico
     */
    public function getProductoPorDia($dia)
    {
        $diasValidos = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
        
        if (!in_array($dia, $diasValidos)) {
            return null;
        }
        
        $productoId = $this->$dia;
        
        if (!$productoId) {
            return null;
        }
        
        return ProductoDetalle::find($productoId);
    }

    /**
     * Verificar si un producto está programado en algún día
     */
    public function productoEstaProgramado($productoId)
    {
        $dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
        
        foreach ($dias as $dia) {
            if ($this->$dia == $productoId) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Obtener lista de productos programados con su día
     */
    public function getProductosProgramados()
    {
        $productos = [];
        $dias = ['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];
        
        foreach ($dias as $dia) {
            if ($this->$dia) {
                $producto = ProductoDetalle::find($this->$dia);
                if ($producto) {
                    $productos[] = [
                        'dia' => $dia,
                        'producto_id' => $this->$dia,
                        'producto_codigo' => $producto->Codigo,
                        'producto_descripcion' => $producto->Descripcion,
                    ];
                }
            }
        }
        
        return $productos;
    }
}