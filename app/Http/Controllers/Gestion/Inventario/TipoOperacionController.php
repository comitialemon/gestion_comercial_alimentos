<?php
namespace App\Http\Controllers\Gestion\Inventario;

use App\Models\Gestion\Inventario\TipoOperacion;

class TipoOperacionController extends BaseInventarioController
{
    protected $model = TipoOperacion::class;
    protected $modelName = 'TipoOperacion';
    protected $fillable = ['Detalle', 'Concepto', 'ActivoInactivo'];

    protected function getValidationRules()
    {
        return [
            'Detalle' => 'required|string',
            'Concepto' => 'required|string|max:30',
            'ActivoInactivo' => 'required|boolean'
        ];
    }
}