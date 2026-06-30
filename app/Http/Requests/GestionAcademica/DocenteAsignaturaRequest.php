<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;

class DocenteAsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("POST") ? "required" : "sometimes";
        return [
            'id_user' => [$isRequired, 'integer', 'exists:usuarios,id_user'],
            'asignaturas' => [$isRequired, 'array', 'min:1'],
            'asignaturas.*' => ['integer', 'exists:academico_asignatura,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_user.required' => 'El docente es obligatorio.',
            'id_user.exists' => 'El docente no existe.',
            'asignaturas.required' => 'Debe seleccionar al menos una asignatura.',
            'asignaturas.*.exists' => 'Una o más asignaturas no existen.',
        ];
    }
}
