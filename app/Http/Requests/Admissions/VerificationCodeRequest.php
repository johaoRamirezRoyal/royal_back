<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;

class VerificationCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            'code' => 'required|digits:5',
        ];
    }

    public function messages()
    {
        return [
            'token.required' => 'El token es obligatorio.',
            'token.string' => 'El token debe ser una cadena válida.',
            'token.size' => 'El token no es válido.',

            'code.required' => 'El codigo de verificacion es obligatorio',
            'code.digits' => 'El codigo debe de ser solamente de 5 digitos y numeros',
        ];
    }
}
