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
        // El id viaja en el body, no en la ruta (PUT /asignaturas no tiene {id} — ver
        // GestionAcademicaController::actualizarAsignatura). $this->route('id') siempre
        // daba null acá, así que ->ignore() nunca excluía la propia fila y editar una
        // asignatura sin cambiar su código fallaba con "código ya está en uso".
        $id = $this->input('id');

        return [
            'nombre' => [$isRequired, 'string', 'max:255'],
            'codigo' => [
                $isRequired, 'string', 'max:50',
                Rule::unique('academico_asignatura', 'codigo')->ignore($id),
            ],
            'abreviatura' => [$isRequired, 'string', 'max:20'],
            'color' => [$isRequired, 'string', 'max:20'],
            'id_area' => ['nullable', 'integer', Rule::exists('academico_area', 'id')],
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
