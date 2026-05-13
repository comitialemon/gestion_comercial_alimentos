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
        'id_sucursal'
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

    // Scope para filtrar por empresa actual
    public function scopePorContexto($query)
    {
        return $query->where('id_cliente', session('cliente_id'))
                     ->where('id_sucursal', session('cliente_sucursal_id'));
    }

    // Scope para obtener solo raíces
    public function scopeRaices($query)
    {
        return $query->whereNull('id_padre');
    }
    
    // Relación con productos a través de la tabla puente
    public function productos()
    {
        return $this->belongsToMany(
            ProductoVenta::class,
            'inventario_producto_categoria',
            'id_categoria',
            'id_detalle_producto'
        );
    }
}