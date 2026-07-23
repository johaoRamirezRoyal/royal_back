<?php

namespace App\Http\Requests\PerfilUsuario;

use Illuminate\Foundation\Http\FormRequest;

class InfoAdicionalUsuarioRequest extends FormRequest
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
            'tipo_documento' => ['nullable', 'integer'],
            'fecha_expedicion' => ['nullable', 'date'],
            'departamento_nacimiento' => ['nullable', 'string'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'direccion_vivienda' => ['nullable', 'string'],
            'genero' => ['nullable', 'string'],
            'ultimo_nivel_educativo' => ['nullable', 'string'],
            'correo_personal' => ['nullable', 'email'],
            'estrato' => ['nullable', 'integer'],
            'cedula_doc' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_user.required' => 'El usuario es obligatorio.',
            'id_user.integer' => 'El id del usuario debe ser un número entero.',
            'correo_personal.email' => 'El correo personal debe ser un correo electrónico válido.',
            'estrato.integer' => 'El estrato debe ser un número entero.',
        ];
    }
}
