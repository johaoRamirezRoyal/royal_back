<?php

namespace App\Http\Requests\LlegadasTarde;

use Illuminate\Foundation\Http\FormRequest;

class LlegadaTardeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_alumno' => [
                'required',
                'integer',
                'exists:usuarios,id_user'
            ],

            // 'id_periodo_academico' => [
            //     'required',
            //     'integer',
            //     'exists:periodo_academico,id'
            // ],

            'fecha' => [
                'required',
                'date'
            ],

            'hora' => [
                'required',
                'date_format:H:i'
            ],

            'justificada' => [
                'nullable',
                'boolean'
            ],

            'observacion' => [
                'nullable',
                'string',
                'max:1000'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'id_alumno.required' => 'El alumno es obligatorio.',
            'id_alumno.integer' => 'El ID del alumno debe ser numérico.',
            'id_alumno.exists' => 'El alumno seleccionado no existe.',

            'id_periodo_academico.required' => 'El período académico es obligatorio.',
            'id_periodo_academico.integer' => 'El ID del período académico debe ser numérico.',
            'id_periodo_academico.exists' => 'El período académico seleccionado no existe.',

            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date' => 'La fecha no tiene un formato válido.',

            'hora.required' => 'La hora es obligatoria.',
            'hora.date_format' => 'La hora debe tener el formato HH:MM.',

            'justificada.boolean' => 'El campo justificada debe ser verdadero o falso.',

            'observacion.string' => 'La observación debe ser un texto.',
            'observacion.max' => 'La observación no puede superar los 1000 caracteres.'
        ];
    }
}
