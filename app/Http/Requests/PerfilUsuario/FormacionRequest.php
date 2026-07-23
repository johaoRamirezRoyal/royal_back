<?php

namespace App\Http\Requests\PerfilUsuario;

use Illuminate\Foundation\Http\FormRequest;

class FormacionRequest extends FormRequest
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
            'tipo_formacion' => [$isRequired, 'string', 'max:50'],
            'programa' => [$isRequired, 'string', 'max:150'],
            'institucion' => [$isRequired, 'string', 'max:300'],
            'fecha_grado' => [$isRequired, 'date'],
            'fecha_expedicion_certi' => [$isRequired, 'date'],
            'duracion' => [$isRequired, 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_user.required' => 'El usuario es obligatorio.',
            'tipo_formacion.required' => 'El tipo de formación es obligatorio.',
            'programa.required' => 'El programa es obligatorio.',
            'institucion.required' => 'La institución es obligatoria.',
            'fecha_grado.required' => 'La fecha de grado es obligatoria.',
            'fecha_expedicion_certi.required' => 'La fecha de expedición del certificado es obligatoria.',
            'duracion.required' => 'La duración es obligatoria.',
            'duracion.integer' => 'La duración debe ser un número entero.',
        ];
    }
}
