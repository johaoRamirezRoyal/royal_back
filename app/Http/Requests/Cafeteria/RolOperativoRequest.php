<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;

/** Crear o editar una función del personal (rol operativo). El código sale del nombre al crearla. */
class RolOperativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
        ];
    }
}
