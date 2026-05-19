<?php

namespace App\Models\Gestion\Inventario;

use Illuminate\Database\Eloquent\Model;

class CategoriaProducto extends Model
{
    protected $connection = 'mysql_gestion_comercial_alimentos';
    protected $table = 'inventario_menu_categoria';
    protected $primaryKey = 'id_categoria';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'id_padre',
        'imagen_url',
        'orden',
        'activo',
        'id_cliente',
    ];

    // Relación con el padre
    public function padre()
    {
        return $this->belongsTo(self::class, 'id_padre', 'id_categoria');
    }

    // Relación con los hijos
    public function hijos()
    {
        return $this->hasMany(self::class, 'id_padre', 'id_categoria')
                    ->orderBy('orden');
    }

    // Scope para filtrar por empresa (SOLO cliente, NO sucursal)
    public function scopePorContexto($query)
    {
        return $query->where('id_cliente', session('cliente_id'));
    }

    // Scope para obtener solo raíces
    public function scopeRaices($query)
    {
        return $query->whereNull('id_padre');
    }
    
    // Relación con productos a través de la tabla puente (con sucursal)
    public function productos($sucursalId = null)
    {
        $sucursalId = $sucursalId ?? session('cliente_sucursal_id');
        
        return $this->belongsToMany(
            ProductoVenta::class,
            'inventario_producto_categoria',
            'id_categoria',
            'id_detalle_producto'
        )->wherePivot('id_sucursal', $sucursalId);
    }

    /**
     * 🔥 Calcula el siguiente orden disponible para un padre específico
     */
    public static function getNextOrder($parentId = null)
    {
        $query = self::where('id_cliente', session('cliente_id'));
        
        if ($parentId) {
            $query->where('id_padre', $parentId);
        } else {
            $query->whereNull('id_padre');
        }
        
        $maxOrder = $query->max('orden');
        
        return ($maxOrder ?? -1) + 1;
    }

    /**
     * 🔥 Reordena las categorías de un padre específico
     */
    public static function reordenar($parentId = null)
    {
        $categorias = self::where('id_cliente', session('cliente_id'));
        
        if ($parentId) {
            $categorias->where('id_padre', $parentId);
        } else {
            $categorias->whereNull('id_padre');
        }
        
        $categorias = $categorias->orderBy('orden')->get();
        
        $orden = 0;
        foreach ($categorias as $cat) {
            $cat->update(['orden' => $orden]);
            $orden++;
        }
    }

    /**
     * 🔥 Reordena recursivamente todas las categorías (para mantener consistencia)
     */
    public static function reordenarTodo($parentId = null)
    {
        $orden = 0;
        
        $categorias = self::where('id_cliente', session('cliente_id'));
        
        if ($parentId) {
            $categorias->where('id_padre', $parentId);
        } else {
            $categorias->whereNull('id_padre');
        }
        
        $categorias = $categorias->orderBy('orden')->get();
        
        foreach ($categorias as $cat) {
            $cat->update(['orden' => $orden]);
            self::reordenarTodo($cat->id_categoria);
            $orden++;
        }
    }
}