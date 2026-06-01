<?php

namespace App\Http\Requests\Admisiones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarInscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        return [

            'estado' => [
                'sometimes',
                'string',
                Rule::in([
                    'BORRADOR',
                    'PENDIENTE',
                    'EN_REVISION',
                    'APROBADO',
                    'RECHAZADO',
                    'CANCELADO'
                ])
            ],

            'id_usuario_registro' => [
                $isUpdate ? 'sometimes' : 'required',
                'integer',
                'exists:usuarios,id_user'
            ],

            'updated_by' => [
                'sometimes',
                'integer',
                'exists:usuarios,id_user'
            ],

            'anio_academico' => [
                'sometimes',
                'integer',
                'exists:anio_escolar,id'
            ],

            'fecha_inscripcion' => [
                'nullable',
                'date'
            ],
        ];
    }

    public function messages(): array
    {
        return [

            // Required
            'id_usuario_registro.required' => 'El ID del usuario que registra la inscripción es obligatorio.',
            'id_usuario_registro.exists' => 'El usuario que registra la inscripción no existe.',

            // Unique
            'codigo.unique' => 'El código de inscripción ya existe, intente nuevamente.',

            // Types
            'codigo.string' => 'El código debe ser un texto válido.',
            'estado.string' => 'El estado debe ser un texto válido.',
            'fecha_inscripcion.date' => 'La fecha de inscripción debe ser válida.',

            // Max
            'codigo.max' => 'El código no puede superar los 30 caracteres.',

            // Exists
            'anio_academico.exists' => 'El año académico seleccionado no existe.',

            // In
            'estado.in' => 'El estado seleccionado no es válido.',

            'updated_by.exists' => 'El usuario que ha actualizado la inscripción no existe.',
            'updated_by.integer' => 'El campo de updated_by debe ser un número entero.'
        ];
    }

    public function attributes(): array
    {
        return [
            'codigo' => 'código de inscripción',
            'estado' => 'estado de inscripción',
            'fecha_inscripcion' => 'fecha de inscripción',
            'id_usuario_registro' => 'ID del usuario que registra la inscripción',
        ];
    }
}
