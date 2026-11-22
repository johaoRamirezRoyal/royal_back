<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Crear o editar una cafetería. El código lo asigna el sistema (CAF-001…) y el estado va por
 * PUT …/estado: ninguno de los dos se manda aquí.
 */
class CafeteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
        ];
    }
}
