<?php
namespace App\Http\Controllers\Gestion\Inventario;

use App\Models\Gestion\Inventario\ProductoGrupo;
use App\Models\Gestion\Inventario\ProductoLinea;

class ProductoGrupoController extends BaseInventarioController
{
    protected $model = ProductoGrupo::class;
    protected $modelName = 'ProductoGrupo';
    protected $withRelations = ['linea'];
    protected $fillable = ['IdLinea', 'Grupo'];

    protected function getExtraData()
    {
        return [
            'lineas' => ProductoLinea::porContexto()->with('estado')->get(['IdLinea as id', 'Linea as nombre'])
        ];
    }

    protected function getValidationRules()
    {
        return [
            'IdLinea' => 'required|integer|exists:inventario_producto_linea,IdLinea',
            'Grupo' => 'required|string'
        ];
    }
}