<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndicadorGestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categoria' => ['required', 'integer', Rule::exists('categoria', 'id')],
            'anio' => ['required', 'integer', Rule::exists('anio_escolar', 'id')],
            'periodo' => ['required', 'integer', 'min:1'],
            'cantidad_mantenimientos' => ['required', 'integer', 'min:0'],
            'cantidad_equipos' => ['required', 'integer', 'min:1'],
            'gestion' => ['nullable', 'string', 'max:2000'],
            'analisis' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'categoria.required' => 'La categoría es obligatoria',
            'categoria.exists' => 'La categoría no existe',
            'anio.required' => 'El año escolar es obligatorio',
            'anio.exists' => 'El año escolar no existe',
            'periodo.required' => 'El periodo es obligatorio',
            'cantidad_mantenimientos.required' => 'La cantidad de mantenimientos es obligatoria',
            'cantidad_mantenimientos.min' => 'La cantidad de mantenimientos no puede ser negativa',
            'cantidad_equipos.required' => 'La cantidad de equipos es obligatoria',
            'cantidad_equipos.min' => 'La cantidad de equipos debe ser al menos 1',
        ];
    }
}
