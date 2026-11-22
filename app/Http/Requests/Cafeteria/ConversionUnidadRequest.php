<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear (unidades y factor) o editar (factor y activa) una conversión: 1 origen = factor destino. */
class ConversionUnidadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creando = $this->isMethod('post');

        return [
            'id_unidad_origen' => [$creando ? 'required' : 'prohibited', 'integer', Rule::exists('caf_unidades_medida', 'id')],
            'id_unidad_destino' => [$creando ? 'required' : 'prohibited', 'integer', Rule::exists('caf_unidades_medida', 'id')],
            'factor_conversion' => [$creando ? 'required' : 'sometimes', 'numeric', 'gt:0', 'max:999999999999'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_unidad_origen.required' => 'Elige la unidad de origen',
            'id_unidad_destino.required' => 'Elige la unidad de destino',
            'id_unidad_origen.prohibited' => 'Las unidades de una conversión no se cambian: crea otra',
            'id_unidad_destino.prohibited' => 'Las unidades de una conversión no se cambian: crea otra',
            'factor_conversion.required' => 'El factor es obligatorio',
            'factor_conversion.gt' => 'El factor debe ser mayor que 0',
        ];
    }
}
