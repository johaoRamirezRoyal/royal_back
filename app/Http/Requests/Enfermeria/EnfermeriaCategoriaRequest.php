<?php

namespace App\Http\Requests\Enfermeria;

use Illuminate\Foundation\Http\FormRequest;

class EnfermeriaCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'nombre' => [
                $isRequired,
                'string',
                'max:200',
            ],
            'tratamiento_sugerido' => [
                'nullable',
                'string',
                'max:350',
            ],
            'activo' => [
                'nullable',
                'integer',
                'in:0,1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.string' => 'El nombre debe ser una cadena de texto',
            'nombre.max' => 'El nombre no puede superar los 200 caracteres',

            'tratamiento_sugerido.string' => 'El tratamiento sugerido debe ser una cadena de texto',
            'tratamiento_sugerido.max' => 'El tratamiento sugerido no puede superar los 350 caracteres',

            'activo.integer' => 'El estado debe ser un número entero',
            'activo.in' => 'El estado debe ser 0 (inactivo) o 1 (activo)',
        ];
    }
}
