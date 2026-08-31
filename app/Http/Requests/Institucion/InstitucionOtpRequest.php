<?php

namespace App\Http\Requests\Institucion;

use Illuminate\Foundation\Http\FormRequest;

class InstitucionOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|digits:5',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de verificación es obligatorio.',
            'code.digits' => 'El código debe tener 5 dígitos.',
        ];
    }
}
