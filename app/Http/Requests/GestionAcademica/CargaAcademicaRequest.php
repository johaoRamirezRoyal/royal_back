<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;

class CargaAcademicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $idRequired = $this->isMethod("POST") ? "required" : "sometimes";
        return [
            'id_curso' => [$idRequired, 'integer', 'exists:curso,id'],
            'id_docente_asignatura' => [$idRequired, 'integer', 'exists:academico_docente_asignatura,id'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_curso.required' => 'El curso es obligatorio.',
            'id_curso.exists' => 'El curso no existe.',
            'id_docente_asignatura.required' => 'La asignatura del docente es obligatoria.',
            'id_docente_asignatura.exists' => 'La asignatura del docente no existe.',
        ];
    }
}
