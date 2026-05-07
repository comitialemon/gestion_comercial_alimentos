<?php

namespace App\Http\Requests\Gestion\Menu;

use Illuminate\Foundation\Http\FormRequest;

class AsignarMenuRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado
     */
    public function authorize(): bool
    {
        return $this->session()->has('operador_id') && $this->session()->has('cliente_id');
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            'operador_id' => [
                'required',
                'integer',
                'exists:mysql_gestion_comercial_alimentos.todos_operador,IdOperador'
            ],
            'menus' => 'nullable|array',
            'menus.*' => [
                'integer',
                'exists:mysql_gestion_comercial_alimentos.menu_administrador,Id'
            ]
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'operador_id.required' => 'Debes seleccionar un operador',
            'operador_id.exists' => 'El operador seleccionado no existe',
            'menus.*.exists' => 'Algún menú seleccionado no existe',
        ];
    }
}