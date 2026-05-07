<?php
// app/Http/Controllers/Gestion/Inventario/BaseInventarioController.php

namespace App\Http\Controllers\Gestion\Inventario;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

abstract class BaseInventarioController extends Controller
{
    protected $model;
    protected $modelName;
    protected $withRelations = [];
    protected $fillable = [];
    protected $primaryKey = 'Id'; // Por defecto, pero cada controlador lo puede sobreescribir

    public function index()
    {
        $query = $this->model::porContexto();
        
        if (!empty($this->withRelations)) {
            $query->with($this->withRelations);
        }
        
        // Usar la primary key definida en el modelo o la que definimos en el controlador
        $primaryKey = $this->model::make()->getKeyName();
        
        $items = $query->orderBy($primaryKey, 'desc')
            ->paginate(20)
            ->withQueryString();

        // Obtener datos adicionales para selects si es necesario
        $extraData = $this->getExtraData();

        return Inertia::render("Gestion/Inventario/{$this->modelName}/Index", array_merge([
            'items' => $items,
            'fillable' => $this->fillable,
        ], $extraData));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->getValidationRules());
        
        if (in_array('IdCliente', (new $this->model)->getFillable())) {
            $data['IdCliente'] = session('cliente_id');
        }
        if (in_array('IdSucursal', (new $this->model)->getFillable())) {
            $data['IdSucursal'] = session('cliente_sucursal_id');
        }
        if (in_array('IdOperador', (new $this->model)->getFillable())) {
            $data['IdOperador'] = session('operador_id');
        }
        if (in_array('IOperador', (new $this->model)->getFillable())) {
            $data['IOperador'] = session('operador_id');
        }
        
        $this->model::create($data);
        
        return redirect()->back()->with('success', 'Registro creado correctamente.');
    }

    public function update(Request $request, $id)
    {
        $item = $this->model::findOrFail($id);
        $data = $request->validate($this->getValidationRules());
        
        $item->update($data);
        
        return redirect()->back()->with('success', 'Registro actualizado correctamente.');
    }

    public function destroy($id)
    {
        $item = $this->model::findOrFail($id);
        $item->delete();
        
        return redirect()->back()->with('success', 'Registro eliminado correctamente.');
    }

    protected function getExtraData()
    {
        return [];
    }

    abstract protected function getValidationRules();
}