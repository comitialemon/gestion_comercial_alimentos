<?php
namespace App\Http\Controllers\Gestion\Inventario;

use App\Models\Gestion\Inventario\UnidadMedida;

class UnidadMedidaController extends BaseInventarioController
{
    protected $model = UnidadMedida::class;
    protected $modelName = 'UnidadMedida';
    protected $fillable = ['UnidadMedida'];

    protected function getValidationRules()
    {
        return [
            'UnidadMedida' => 'required|string'
        ];
    }
}