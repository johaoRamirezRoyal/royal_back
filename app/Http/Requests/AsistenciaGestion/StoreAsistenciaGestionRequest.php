<?php

namespace App\Http\Requests\AsistenciaGestion;

use Illuminate\Foundation\Http\FormRequest;

class StoreAsistenciaGestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_user' => [
                'required',
                'integer',
                'exists:usuarios,id_user',
            ],
            'fecha_asistencia' => [
                'required',
                'date',
            ],
            'hora_asistencia' => [
                'required',
                'date_format:H:i:s',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_user.required' => 'El ID del usuario es obligatorio.',
            'id_user.integer' => 'El ID del usuario debe ser numérico.',
            'id_user.exists' => 'El usuario seleccionado no existe.',
            'fecha_asistencia.required' => 'La fecha de asistencia es obligatoria.',
            'fecha_asistencia.date' => 'La fecha de asistencia no tiene un formato válido.',
            'hora_asistencia.required' => 'La hora de asistencia es obligatoria.',
            'hora_asistencia.date_format' => 'La hora de asistencia debe tener el formato HH:MM:SS.',
        ];
    }
}
