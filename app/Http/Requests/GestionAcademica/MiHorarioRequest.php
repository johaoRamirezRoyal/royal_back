<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;

class MiHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod('POST') ? 'required' : 'nullable';

        return [
            'id_curso' => [$isRequired, 'integer', 'exists:curso,id'],
            'id_asignatura' => [$isRequired, 'integer', 'exists:academico_asignatura,id'],
            'id_franja_horaria' => [$isRequired, 'integer', 'exists:academico_franja_horaria,id'],
            'id_anio_escolar' => [$isRequired, 'integer', 'exists:anio_escolar,id'],
            'ids' => [$this->isMethod('DELETE') ? 'required' : 'nullable', 'array'],
            'ids.*' => ['integer', 'exists:academico_horario_clase,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_curso.exists' => 'El curso no existe.',
            'id_asignatura.exists' => 'La asignatura no existe.',
            'id_franja_horaria.exists' => 'La franja horaria no existe.',
            'ids.*.exists' => 'Uno o más horarios no existen.',
        ];
    }
}
