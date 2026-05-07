<?php
namespace App\Http\Controllers\Gestion\Inventario;

use App\Models\Gestion\Inventario\ProductoEstado;

class ProductoEstadoController extends BaseInventarioController
{
    protected $model = ProductoEstado::class;
    protected $modelName = 'ProductoEstado';
    protected $fillable = ['Estado'];

    protected function getValidationRules()
    {
        return [
            'Estado' => 'required|string|max:30'
        ];
    }
}