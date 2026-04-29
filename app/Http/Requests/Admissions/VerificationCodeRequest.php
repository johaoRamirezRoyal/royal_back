<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Contracts\Validation\ValidationRule;
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
             'email' => 'required|email|min:10|max:140',
            'verificationCode' => 'required|digits:5'
        ];
    }

    public function messages() {
        return [
                'email.required' => 'El correo es un campo obligatorio.',
                'email.email' => 'El correo no tiene un formato valido.',
                'email.min' => 'El correo debe tener al menos 10 caracteres',
                'email.max' => 'El correo no puede superar los 140 caracteres',

                'verificationCode.required' => 'El codigo de verificacion es obligatorio',
                'verificationCode.digits' => 'El codigo debe de ser solamente de 5 digitos y numeros'
        ];
    }
}
