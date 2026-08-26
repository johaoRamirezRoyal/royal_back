<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MantenimientoIndicadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_categoria' => ['nullable', 'integer', 'in:1,2'],
            'id_anio' => ['nullable', 'integer', Rule::exists('anio_escolar', 'id')],
            'id_periodo' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_categoria.in' => 'El tipo de categoría debe ser 1 (Sistemas) o 2 (Operativos)',
            'id_anio.integer' => 'El año escolar debe ser un número entero',
            'id_anio.exists' => 'El año escolar no existe',
            'id_periodo.integer' => 'El periodo debe ser un número entero',
        ];
    }
}
