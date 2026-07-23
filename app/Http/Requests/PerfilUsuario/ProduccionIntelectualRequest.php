<?php

namespace App\Http\Requests\PerfilUsuario;

use Illuminate\Foundation\Http\FormRequest;

class ProduccionIntelectualRequest extends FormRequest
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
            'tipo_produccion' => [$isRequired, 'string', 'max:100'],
            'denominacion' => [$isRequired, 'string', 'max:100'],
            'nombre' => [$isRequired, 'string', 'max:200'],
            'objetivo' => ['nullable', 'string', 'max:200'],
            'descripcion_actividades' => [$isRequired, 'string', 'max:500'],
            'duracion' => [$isRequired, 'string', 'max:100'],
            'lugar' => [$isRequired, 'string', 'max:250'],
            'observacion' => [$isRequired, 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_user.required' => 'El usuario es obligatorio.',
            'tipo_produccion.required' => 'El tipo de producción es obligatorio.',
            'denominacion.required' => 'La denominación es obligatoria.',
            'nombre.required' => 'El nombre es obligatorio.',
            'descripcion_actividades.required' => 'La descripción de actividades es obligatoria.',
            'duracion.required' => 'La duración es obligatoria.',
            'lugar.required' => 'El lugar es obligatorio.',
            'observacion.required' => 'La observación es obligatoria.',
        ];
    }
}
