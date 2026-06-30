<?php

namespace App\Http\Requests\GestionAcademica;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignaturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("POST") ? "required" : "sometimes";
        $id = $this->route('id');

        return [
            'nombre' => [$isRequired, 'string', 'max:255'],
            'codigo' => [
                $isRequired, 'string', 'max:50',
                Rule::unique('academico_asignatura', 'codigo')->ignore($id),
            ],
            'abreviatura' => [$isRequired, 'string', 'max:20'],
            'color' => [$isRequired, 'string', 'max:20'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'El código ya está en uso.',
            'abreviatura.required' => 'La abreviatura es obligatoria.',
            'color.required' => 'El color es obligatorio.',
        ];
    }
}
