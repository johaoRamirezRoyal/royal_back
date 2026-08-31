<?php

namespace App\Http\Requests\Institucion;

use Illuminate\Foundation\Http\FormRequest;

class InstitucionEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|min:10|max:140',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'El correo es un campo obligatorio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.min' => 'El correo debe tener al menos 10 caracteres.',
            'email.max' => 'El correo no puede superar los 140 caracteres.',
        ];
    }
}
