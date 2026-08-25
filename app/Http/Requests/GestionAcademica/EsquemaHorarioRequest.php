<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;

class EsquemaHorarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'nombre' => [$isRequired, 'string', 'max:255'],
            // id_nivel apunta a nivel_academico (no a `nivel`) desde
            // 2026_08_25_030000_migrate_curso_and_esquema_nivel_to_nivel_academico.
            'id_nivel' => [$isRequired, 'integer', 'exists:nivel_academico,id'],
            'id_anio_escolar' => [$isRequired, 'integer', 'exists:anio_escolar,id'],
            'activo' => ['nullable', 'boolean'],
            'id' => [$this->isMethod('PUT') ? 'required' : 'nullable', 'integer', 'exists:academico_esquema_horario,id'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'exists:academico_esquema_horario,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'id_nivel.required' => 'El nivel es obligatorio.',
            'id_nivel.exists' => 'El nivel no existe.',
            'id_anio_escolar.required' => 'El año escolar es obligatorio.',
            'id_anio_escolar.exists' => 'El año escolar no existe.',
            'id.exists' => 'El esquema de horario no existe.',
            'ids.*.exists' => 'Uno o más esquemas de horario no existen.',
        ];
    }
}
