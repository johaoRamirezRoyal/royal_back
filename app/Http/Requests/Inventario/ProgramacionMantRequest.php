<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class ProgramacionMantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'descripcion' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'integer', 'in:1,2'],
            'fecha_registro' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_registro'],
            'periodo' => ['required', 'integer', 'exists:periodos,id'],
            'estado' => ['nullable', 'integer', 'between:0,4'],
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres',
            'tipo.in' => 'El tipo debe ser Sistemas u Operativos',
            'fecha_registro.required' => 'La fecha es obligatoria',
            'fecha_fin.after_or_equal' => 'La fecha fin no puede ser anterior a la de inicio',
            'periodo.required' => 'Selecciona el periodo',
            'periodo.exists' => 'El periodo no existe',
        ];
    }
}
