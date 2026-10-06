<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndicadorGestionFiltroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_categoria' => ['required', 'integer', Rule::in([1, 2])],
            'id_anio' => ['nullable', 'integer', Rule::exists('anio_escolar', 'id')],
            'periodo' => ['nullable', 'integer', 'min:1'],
            'id_categoria' => ['nullable', 'integer', Rule::exists('categoria', 'id')],
        ];
    }
}
