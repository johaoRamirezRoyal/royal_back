<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;

class FranjaHorariaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_anio_escolar' => ['nullable', 'integer', 'exists:anio_escolar,id'],
            'id_esquema' => ['nullable', 'integer', 'exists:academico_esquema_horario,id'],
            'id_curso' => ['nullable', 'integer', 'exists:curso,id'],
            'id_dia_semana' => ['nullable', 'integer', 'exists:dias_semana,id'],
            'hora_inicio' => ['nullable', 'date_format:H:i:s'],
            'hora_fin' => ['nullable', 'date_format:H:i:s'],
            'orden' => ['nullable', 'integer', 'min:1'],
            'asignable' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'max:20'],
            'etiqueta' => ['nullable', 'string', 'max:100'],
            'aplicar_todos_dias' => ['nullable', 'boolean'],
            'id' => ['nullable', 'integer', 'exists:academico_franja_horaria,id'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'exists:academico_franja_horaria,id'],
            'franjas' => ['nullable', 'array'],
            'franjas.*.id' => ['required_with:franjas', 'integer', 'exists:academico_franja_horaria,id'],
            'franjas.*.orden' => ['required_with:franjas', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_anio_escolar.exists' => 'El año escolar no existe.',
            'id_esquema.exists' => 'El esquema de horario no existe.',
            'id_curso.exists' => 'El curso no existe.',
            'id_dia_semana.exists' => 'El día de la semana no existe.',
            'hora_inicio.date_format' => 'La hora debe tener formato H:i:s.',
            'hora_fin.date_format' => 'La hora debe tener formato H:i:s.',
            'id.exists' => 'La franja horaria no existe.',
            'ids.*.exists' => 'Una o más franjas no existen.',
            'franjas.*.id.required_with' => 'Cada franja debe tener un id.',
            'franjas.*.orden.required_with' => 'Cada franja debe tener un orden.',
        ];
    }
}
