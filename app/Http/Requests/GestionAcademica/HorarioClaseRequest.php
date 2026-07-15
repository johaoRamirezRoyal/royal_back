<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HorarioClaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_franja_horaria' => ['nullable', 'integer', 'exists:academico_franja_horaria,id'],
            'id_carga_academica' => ['nullable', 'integer', 'exists:academico_carga_academica,id'],
            'tipo' => ['nullable', 'string', Rule::in(['CLASE', 'PLANEACION', 'REUNION', 'CLUB', 'LIBRE', 'RECESO', 'ALMUERZO'])],
            'id' => ['nullable', 'integer', 'exists:academico_horario_clase,id'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'exists:academico_horario_clase,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_franja_horaria.exists' => 'La franja horaria no existe.',
            'id_carga_academica.exists' => 'La carga académica no existe.',
            'tipo.in' => 'El tipo debe ser CLASE, PLANEACION, REUNION, CLUB, LIBRE, RECESO o ALMUERZO.',
            'ids.*.exists' => 'Uno o más horarios no existen.',
        ];
    }
}
