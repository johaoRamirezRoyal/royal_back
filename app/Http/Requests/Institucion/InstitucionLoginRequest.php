<?php

namespace App\Http\Requests\Institucion;

use Illuminate\Foundation\Http\FormRequest;

class InstitucionLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_institucion' => 'required|integer|exists:instituciones,id',
            'nit' => 'required|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'id_institucion.required' => 'Selecciona una institución.',
            'id_institucion.exists' => 'Institución o NIT incorrectos.',
            'nit.required' => 'El NIT es obligatorio.',
            'nit.max' => 'El NIT no puede superar los 50 caracteres.',
        ];
    }
}
