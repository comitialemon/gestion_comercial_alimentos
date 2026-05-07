<?php

namespace App\Http\Requests\Gestion\Todos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIdentificadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ci' => [
                'required',
                'numeric',
                Rule::unique('mysql_gestion_comercial_alimentos.todos_identificador', 'CI_NIT')
            ],
            'nombre' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'ci.required' => 'El CI/NIT es obligatorio',
            'ci.numeric' => 'El CI/NIT debe ser un número',
            'ci.unique' => 'Este CI/NIT ya está registrado',
            'nombre.required' => 'El nombre es obligatorio',
        ];
    }
}