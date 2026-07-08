<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsistenciaEstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_asistencia_clase' => [
                'required',
                'integer',
                Rule::exists('academico_asistencia_clase', 'id')
                    ->whereNotIn('estado', ['CANCELADA', 'REPROGRAMADA']),
            ],
            'estudiantes' => ['required', 'array', 'min:1'],
            'estudiantes.*.id_alumno' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id_user')
                    ->where('estado', 'activo')
                    ->where('perfil', 16),
            ],
            'estudiantes.*.estado' => [
                'required',
                'string',
                Rule::in(['AUSENTE', 'TARDE', 'PERMISO']),
            ],
            'estudiantes.*.observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_asistencia_clase.required' => 'El ID de la asistencia de clase es obligatorio.',
            'id_asistencia_clase.integer'  => 'El ID de la asistencia de clase debe ser un número entero.',
            'id_asistencia_clase.exists'   => 'La clase no existe o su estado es CANCELADA o REPROGRAMADA.',

            'estudiantes.required' => 'Debe enviar al menos un estudiante.',
            'estudiantes.array'    => 'El campo estudiantes debe ser un arreglo.',
            'estudiantes.min'      => 'Debe enviar al menos un estudiante.',

            'estudiantes.*.id_alumno.required' => 'El ID del alumno es obligatorio.',
            'estudiantes.*.id_alumno.integer'  => 'El ID del alumno debe ser un número entero.',
            'estudiantes.*.id_alumno.exists'   => 'El alumno no existe, no está activo o no tiene perfil de estudiante.',

            'estudiantes.*.estado.required' => 'El estado de la asistencia es obligatorio.',
            'estudiantes.*.estado.string'   => 'El estado debe ser una cadena de texto.',
            'estudiantes.*.estado.in'       => 'El estado debe ser AUSENTE, TARDE o PERMISO.',
        ];
    }
}
