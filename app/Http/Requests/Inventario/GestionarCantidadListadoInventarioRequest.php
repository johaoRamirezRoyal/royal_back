<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GestionarCantidadListadoInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:200'],
            'id_area' => ['required', 'integer', Rule::exists('areas', 'id')],
            'id_usuario' => ['required', 'integer', Rule::exists('usuarios', 'id_user')],
            'cantidad' => ['required', 'integer', 'min:1'],
            'id_log' => ['nullable', 'integer', Rule::exists('usuarios', 'id_user')],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción del grupo es obligatoria',
            'descripcion.string' => 'La descripción debe ser un texto',
            'descripcion.max' => 'La descripción no puede superar los 200 caracteres',

            'id_area.required' => 'El área es obligatoria',
            'id_area.integer' => 'El área debe ser un número entero',
            'id_area.exists' => 'El área no existe',

            'id_usuario.required' => 'El usuario es obligatorio',
            'id_usuario.integer' => 'El usuario debe ser un número entero',
            'id_usuario.exists' => 'El usuario no existe',

            'cantidad.required' => 'La cantidad es obligatoria',
            'cantidad.integer' => 'La cantidad debe ser un número entero',
            'cantidad.min' => 'La cantidad debe ser al menos 1',

            'id_log.integer' => 'El usuario log debe ser un número entero',
            'id_log.exists' => 'El usuario log no existe',
        ];
    }
}
