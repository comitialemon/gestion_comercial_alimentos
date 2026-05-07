<?php
namespace App\Http\Controllers\Gestion\Inventario;

use App\Models\Gestion\Inventario\Almacen;

class AlmacenController extends BaseInventarioController
{
    protected $model = Almacen::class;
    protected $modelName = 'Almacen';
    protected $fillable = ['Almacen', 'AlmacenPrincipal'];

    protected function getValidationRules()
    {
        return [
            'Almacen' => 'required|string',
            'AlmacenPrincipal' => 'required|boolean'
        ];
    }
}