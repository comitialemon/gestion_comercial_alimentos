<?php

namespace App\Http\Requests\Gestion\Menu;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            'menus.*' => 'integer' // ❌ SIN VALIDACIÓN DE EXISTENCIA
        ];
    }

    /**
     * Validación adicional después de la validación base
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $menuIds = $this->input('menus', []);
            
            if (!empty($menuIds)) {
                // Verificar qué IDs existen
                $existentes = DB::connection('mysql_gestion_comercial_alimentos')
                    ->table('menu_administrador')
                    ->whereIn('Id', $menuIds)
                    ->pluck('Id')
                    ->toArray();
                
                $invalidos = array_diff($menuIds, $existentes);
                
                if (!empty($invalidos)) {
                    // SOLO ADVERTENCIA, NO ERROR
                    Log::warning('IDs de menú inválidos encontrados', [
                        'invalidos' => $invalidos,
                        'total' => count($invalidos)
                    ]);
                    
                    // 🔥 OPCIONAL: Si quieres mostrar error solo si TODOS son inválidos
                    // if (count($invalidos) === count($menuIds)) {
                    //     $validator->errors()->add('menus', 'Ninguno de los menús seleccionados existe en la base de datos');
                    // }
                }
            }
        });
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'operador_id.required' => 'Debes seleccionar un operador',
            'operador_id.exists' => 'El operador seleccionado no existe',
        ];
    }
}