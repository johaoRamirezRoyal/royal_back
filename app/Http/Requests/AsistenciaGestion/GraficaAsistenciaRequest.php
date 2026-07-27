<?php

namespace App\Http\Requests\AsistenciaGestion;

use Illuminate\Foundation\Http\FormRequest;

class GraficaAsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'top' => ['nullable', 'integer', 'min:1', 'max:50'],
            'hora_limite' => ['nullable', 'date_format:H:i:s'],
            'id_usuario' => ['nullable', 'integer', 'exists:usuarios,id_user'],
            'id_perfil' => ['nullable', 'integer'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'top.integer' => 'El top debe ser numérico.',
            'top.min' => 'El top debe ser al menos 1.',
            'top.max' => 'El top no puede ser mayor a 50.',
            'hora_limite.date_format' => 'La hora límite debe tener el formato HH:MM:SS.',
            'id_usuario.integer' => 'El ID del usuario debe ser numérico.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            'id_perfil.integer' => 'El ID del perfil debe ser numérico.',
            'fecha_desde.date' => 'La fecha desde no tiene un formato válido.',
            'fecha_hasta.date' => 'La fecha hasta no tiene un formato válido.',
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
            'per_page.integer' => 'El número de registros por página debe ser numérico.',
            'per_page.min' => 'Debe haber al menos 1 registro por página.',
            'per_page.max' => 'No puede haber más de 100 registros por página.',
        ];
    }
}
