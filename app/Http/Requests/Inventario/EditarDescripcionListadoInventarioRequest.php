<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditarDescripcionListadoInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:200'],
            'nueva_descripcion' => ['required', 'string', 'max:200', 'different:descripcion'],
            'id_area' => ['required', 'integer', Rule::exists('areas', 'id')],
            'id_usuario' => ['required', 'integer', Rule::exists('usuarios', 'id_user')],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción actual del grupo es obligatoria',
            'descripcion.string' => 'La descripción debe ser un texto',
            'descripcion.max' => 'La descripción no puede superar los 200 caracteres',

            'nueva_descripcion.required' => 'La nueva descripción es obligatoria',
            'nueva_descripcion.string' => 'La nueva descripción debe ser un texto',
            'nueva_descripcion.max' => 'La nueva descripción no puede superar los 200 caracteres',
            'nueva_descripcion.different' => 'La nueva descripción debe ser diferente a la actual',

            'id_area.required' => 'El área es obligatoria',
            'id_area.integer' => 'El área debe ser un número entero',
            'id_area.exists' => 'El área no existe',

            'id_usuario.required' => 'El usuario es obligatorio',
            'id_usuario.integer' => 'El usuario debe ser un número entero',
            'id_usuario.exists' => 'El usuario no existe',
        ];
    }
}
