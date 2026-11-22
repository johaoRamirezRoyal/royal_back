<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Asignar a una persona a una cafetería con una función. Quién asigna sale de la sesión. */
class AsignacionPersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_cafeteria' => ['required', 'integer', Rule::exists('caf_cafeterias', 'id')],
            'id_user' => ['required', 'integer'],
            'id_rol_operativo' => ['required', 'integer', Rule::exists('caf_roles_operativos', 'id')],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_cafeteria.required' => 'Elige la cafetería',
            'id_cafeteria.exists' => 'La cafetería no existe',
            'id_user.required' => 'Elige a la persona',
            'id_rol_operativo.required' => 'Elige la función',
            'id_rol_operativo.exists' => 'La función no existe',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio',
        ];
    }
}
