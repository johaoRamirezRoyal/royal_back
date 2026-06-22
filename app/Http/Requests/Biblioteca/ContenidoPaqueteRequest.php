<?php

namespace App\Http\Requests\Biblioteca;

use Illuminate\Foundation\Http\FormRequest;

class ContenidoPaqueteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = ($this->isMethod("POST")) ? "required" : "sometimes";
        return [
            'titulo' => [$isRequired, 'string', 'max:200'],
            'id_paquete' => [$isRequired, 'integer', 'exists:biblioteca_paquete,id'],
            'id_log' => ['required', 'integer', 'exists:usuarios,id_user']
        ];
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título es obligatorio.',
            'titulo.max' => 'El título no puede superar los 200 caracteres.',

            'id_paquete.required' => 'El paquete es obligatorio.',
            'id_paquete.integer' => 'El identificador del paquete debe ser numérico.',
            'id_paquete.exists' => 'El paquete seleccionado no existe.',

            'codigo.required' => 'El código es obligatorio.',
            'codigo.max' => 'El código no puede superar los 200 caracteres.',

            'id_log.required' => 'El identificador del usuario es obligatorio.',
            'id_log.integer' => 'El identificador del usuario debe ser numérico.',
            'id_log.exists' => 'El usuario seleccionado no existe.',
        ];
    }
}
