<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Reemplaza el horario de una cafetería o de un punto: { horarios: [{ id_dia_semana, hora_apertura, hora_cierre }] }. */
class HorariosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'horarios' => ['present', 'array', 'max:50'],
            'horarios.*.id_dia_semana' => ['required', 'integer', Rule::exists('dias_semana', 'id')],
            'horarios.*.hora_apertura' => ['required', 'date_format:H:i'],
            'horarios.*.hora_cierre' => ['required', 'date_format:H:i', 'after:horarios.*.hora_apertura'],
        ];
    }

    public function messages(): array
    {
        return [
            'horarios.present' => 'Envía la lista de franjas (vacía para quitar el horario)',
            'horarios.*.id_dia_semana.exists' => 'El día no es válido',
            'horarios.*.hora_apertura.date_format' => 'La hora de apertura debe tener el formato HH:MM',
            'horarios.*.hora_cierre.date_format' => 'La hora de cierre debe tener el formato HH:MM',
            'horarios.*.hora_cierre.after' => 'La hora de cierre debe ser posterior a la de apertura',
        ];
    }
}
