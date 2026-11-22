<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;

/** Crear o editar una unidad de medida. El código sale de la abreviatura al crearla. */
class UnidadMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:60'],
            'abreviatura' => ['required', 'string', 'max:12'],
            'precision_decimal' => ['required', 'integer', 'between:0,3'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'abreviatura.required' => 'La abreviatura es obligatoria',
            'precision_decimal.required' => 'Indica cuántos decimales admite',
            'precision_decimal.between' => 'Los decimales van de 0 a 3',
        ];
    }
}
