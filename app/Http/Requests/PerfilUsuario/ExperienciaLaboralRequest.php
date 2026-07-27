<?php

namespace App\Http\Requests\PerfilUsuario;

use Illuminate\Foundation\Http\FormRequest;

class ExperienciaLaboralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("POST") ? "required" : "sometimes";
        $isUpdated = $this->isMethod("PUT") ? "required" : "sometimes";

        return [
            'id' => [$isUpdated, 'integer'],
            'id_user' => [$isRequired, 'integer'],
            'nombre_empresa' => ['nullable', 'string', 'max:350'],
            'cargo' => ['nullable', 'string', 'max:200'],
            'fecha_ingreso' => ['nullable', 'date'],
            'fecha_retiro' => ['nullable', 'date'],
            'certificado_trabajo' => ['nullable', 'string'],
            'fecha_certificado' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_user.required' => 'El usuario es obligatorio.',
            'nombre_empresa.max' => 'El nombre de la empresa no debe exceder 350 caracteres.',
            'cargo.max' => 'El cargo no debe exceder 200 caracteres.',
        ];
    }
}
