<?php
namespace App\Http\Controllers\Gestion\Inventario;

use App\Models\Gestion\Inventario\ProductoLinea;
use App\Models\Gestion\Inventario\ProductoEstado;

class ProductoLineaController extends BaseInventarioController
{
    protected $model = ProductoLinea::class;
    protected $modelName = 'ProductoLinea';
    protected $withRelations = ['estado'];
    protected $fillable = ['IdEstado', 'Linea'];

    protected function getExtraData()
    {
        return [
            'estados' => ProductoEstado::porContexto()->get(['IdEstado as id', 'Estado as nombre'])
        ];
    }

    protected function getValidationRules()
    {
        return [
            'IdEstado' => 'required|integer|exists:inventario_producto_estado,IdEstado',
            'Linea' => 'required|string|max:50'
        ];
    }
}