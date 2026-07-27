<?php

namespace App\Http\Requests\Enfermeria;

use Illuminate\Foundation\Http\FormRequest;

class EnfermeriaAtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'id_user' => [
                $isRequired,
                'integer',
                'exists:usuarios,id_user',
            ],
            'motivo' => [
                $isRequired,
                'integer',
                'exists:enfermeria_categoria,id',
            ],
            'tratamiento' => [
                'nullable',
                'string',
            ],
            'envio' => [
                'nullable',
                'integer',
                'in:0,1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_user.required' => 'El estudiante es obligatorio',
            'id_user.integer' => 'El ID del estudiante debe ser un número entero',
            'id_user.exists' => 'El estudiante seleccionado no existe',

            'motivo.required' => 'El motivo de la atención es obligatorio',
            'motivo.integer' => 'El motivo debe ser un número entero',
            'motivo.exists' => 'La categoría seleccionada no existe',

            'tratamiento.string' => 'El tratamiento debe ser una cadena de texto',

            'envio.integer' => 'El campo envío debe ser un número entero',
            'envio.in' => 'El campo envío debe ser 0 (no enviar) o 1 (sí enviar)',
        ];
    }
}
