<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsistenciaClaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['nullable', 'integer', 'exists:academico_asistencia_clase,id'],
            'id_horario_clase' => ['nullable', 'integer', 'exists:academico_horario_clase,id'],
            'fecha' => ['nullable', 'date_format:Y-m-d'],
            'estado' => ['nullable', 'string', Rule::in(['DICTADA', 'CANCELADA', 'REPROGRAMADA'])],
            'observacion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.exists' => 'La asistencia no existe.',
            'id_horario_clase.exists' => 'El horario de clase no existe.',
            'fecha.date_format' => 'La fecha debe tener formato Y-m-d.',
            'estado.in' => 'El estado debe ser DICTADA, CANCELADA o REPROGRAMADA.',
        ];
    }
}
