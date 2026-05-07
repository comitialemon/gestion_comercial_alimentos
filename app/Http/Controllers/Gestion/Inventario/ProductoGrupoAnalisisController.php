<?php
namespace App\Http\Controllers\Gestion\Inventario;

use App\Models\Gestion\Inventario\ProductoGrupoAnalisis;

class ProductoGrupoAnalisisController extends BaseInventarioController
{
    protected $model = ProductoGrupoAnalisis::class;
    protected $modelName = 'ProductoGrupoAnalisis';
    protected $fillable = ['Grupo'];

    protected function getValidationRules()
    {
        return [
            'Grupo' => 'required|string'
        ];
    }
}