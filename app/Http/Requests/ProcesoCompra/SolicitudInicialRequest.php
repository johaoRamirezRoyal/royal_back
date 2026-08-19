<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolicitudInicialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'justificacion' => $this->justificacion ? trim($this->justificacion) : null,
            'iva' => $this->iva ? trim($this->iva) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'id_area' => ['required', 'integer', Rule::exists('areas', 'id')],
            'justificacion' => ['required', 'string', 'max:1000'],
            'fecha_solicitud' => ['nullable', 'date'],
            'iva' => ['nullable', 'string', 'max:200'],
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto' => ['required', 'string', 'max:1000'],
            'productos.*.cantidad' => ['required', 'string', 'max:200'],
            'productos.*.precio' => ['nullable', 'string', 'max:200'],
            'productos.*.iva' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_area.required' => 'Debe indicar el área',
            'id_area.exists' => 'El área seleccionada no existe',
            'justificacion.required' => 'La justificación es obligatoria',
            'justificacion.max' => 'La justificación no puede superar los 1000 caracteres',
            'fecha_solicitud.date' => 'La fecha de solicitud no es válida',
            'productos.required' => 'Debe agregar al menos un producto',
            'productos.min' => 'Debe agregar al menos un producto',
            'productos.*.producto.required' => 'El nombre del producto es obligatorio',
            'productos.*.cantidad.required' => 'La cantidad del producto es obligatoria',
        ];
    }

    public function toSolicitudData(): array
    {
        return [
            'id_area' => $this->id_area,
            'justificacion' => $this->justificacion,
            'fecha_solicitud' => $this->fecha_solicitud,
            'iva' => $this->iva,
        ];
    }

    public function toProductosData(): array
    {
        return array_map(fn ($producto) => [
            'producto' => $producto['producto'],
            'cantidad' => $producto['cantidad'],
            'precio' => $producto['precio'] ?? null,
            'iva' => $producto['iva'] ?? null,
        ], $this->productos);
    }
}