<?php

namespace App\Http\Requests\AsistenciaGestion;

use Illuminate\Foundation\Http\FormRequest;

class FiltroAsistenciaGestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_usuario' => ['nullable', 'integer', 'exists:usuarios,id_user'],
            'id_perfil' => ['nullable', 'integer'],
            'fecha' => ['nullable', 'date'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'hora_desde' => ['nullable', 'date_format:H:i:s'],
            'hora_hasta' => ['nullable', 'date_format:H:i:s'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_usuario.integer' => 'El ID del usuario debe ser numérico.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_perfil.integer' => 'El ID del perfil debe ser numérico.',
            'fecha.date' => 'La fecha no tiene un formato válido.',
            'fecha_desde.date' => 'La fecha desde no tiene un formato válido.',
            'fecha_hasta.date' => 'La fecha hasta no tiene un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'hora_desde.date_format' => 'La hora desde debe tener el formato HH:MM:SS.',
            'hora_hasta.date_format' => 'La hora hasta debe tener el formato HH:MM:SS.',
            'per_page.integer' => 'El número de registros por página debe ser numérico.',
            'per_page.min' => 'Debe haber al menos 1 registro por página.',
            'per_page.max' => 'No puede haber más de 100 registros por página.',
        ];
    }
}
