<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Nuevo precio de un producto. Sin punto de venta = precio general. */
class PrecioProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id_punto_venta' => $this->filled('id_punto_venta') ? $this->input('id_punto_venta') : null,
            'vigencia_hasta' => $this->filled('vigencia_hasta') ? $this->input('vigencia_hasta') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'id_punto_venta' => ['nullable', 'integer', Rule::exists('caf_puntos_venta', 'id')],
            'precio' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'vigencia_desde' => ['required', 'date_format:Y-m-d'],
            'vigencia_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:vigencia_desde'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_punto_venta.exists' => 'El punto de venta no existe',
            'precio.required' => 'El precio es obligatorio',
            'precio.min' => 'El precio no puede ser negativo',
            'vigencia_desde.required' => 'Indica desde cuándo rige',
            'vigencia_desde.date_format' => 'La fecha debe tener el formato AAAA-MM-DD',
            'vigencia_hasta.date_format' => 'La fecha debe tener el formato AAAA-MM-DD',
            'vigencia_hasta.after_or_equal' => 'La vigencia termina antes de empezar',
        ];
    }
}
