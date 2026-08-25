<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registra la asistencia de un bloque fusionado (dos o más franjas seguidas de la misma
 * clase, ver agruparRunsPorDia/useTakeAttendance en el frontend) con una sola sesión de
 * estado/observación/excepciones de alumnos aplicada a cada franja del bloque a la vez —
 * antes esto era N llamadas independientes a /asistencias-clase + /asistencias-estudiante
 * desde el frontend, sin ninguna atomicidad entre ellas.
 */
class AsistenciaClaseLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids_horario_clase' => ['required', 'array', 'min:1'],
            'ids_horario_clase.*' => ['integer', 'exists:academico_horario_clase,id'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'estado' => ['required', 'string', Rule::in(['DICTADA', 'CANCELADA', 'REPROGRAMADA'])],
            'observacion' => ['nullable', 'string', 'max:255'],
            'estudiantes' => ['nullable', 'array'],
            'estudiantes.*.id_alumno' => [
                'required_with:estudiantes',
                'integer',
                Rule::exists('usuarios', 'id_user')
                    ->where('estado', 'activo')
                    ->where('perfil', 16),
            ],
            'estudiantes.*.estado' => [
                'required_with:estudiantes',
                'string',
                Rule::in(['AUSENTE', 'TARDE', 'PERMISO']),
            ],
            'estudiantes.*.observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'ids_horario_clase.required' => 'Debe enviar al menos una franja de horario.',
            'ids_horario_clase.*.exists' => 'Alguna de las franjas de horario no existe.',
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date_format' => 'La fecha debe tener formato Y-m-d.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser DICTADA, CANCELADA o REPROGRAMADA.',

            'estudiantes.*.id_alumno.required_with' => 'El ID del alumno es obligatorio.',
            'estudiantes.*.id_alumno.exists' => 'El alumno no existe, no está activo o no tiene perfil de estudiante.',
            'estudiantes.*.estado.required_with' => 'El estado de la asistencia es obligatorio.',
            'estudiantes.*.estado.in' => 'El estado debe ser AUSENTE, TARDE o PERMISO.',
        ];
    }
}
