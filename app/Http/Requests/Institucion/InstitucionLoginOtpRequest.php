<?php

namespace App\Http\Requests\Institucion;

use Illuminate\Foundation\Http\FormRequest;

class InstitucionLoginOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string|size:64',
            'code' => 'required|digits:5',
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'El token es obligatorio.',
            'token.string' => 'El token debe ser una cadena válida.',
            'token.size' => 'El token no es válido.',
            'code.required' => 'El código de verificación es obligatorio.',
            'code.digits' => 'El código debe tener 5 dígitos.',
        ];
    }
}
