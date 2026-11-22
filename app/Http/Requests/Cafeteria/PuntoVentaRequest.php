<?php

namespace App\Http\Requests\Cafeteria;

use App\Enums\Cafeteria\EstadoPuntoVenta;
use App\Enums\Cafeteria\TipoPuntoVenta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crear (POST cafeterias/{cafeteria}/puntos-venta) o editar (PUT puntos-venta/{puntoVenta}) un
 * punto. El código lo asigna el sistema (PV-001… dentro de la cafetería).
 */
class PuntoVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::enum(TipoPuntoVenta::class)],
            'ubicacion' => ['nullable', 'string', 'max:200'],
            'estado' => ['sometimes', Rule::enum(EstadoPuntoVenta::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'tipo.required' => 'Elige el tipo de punto de venta',
            'tipo.enum' => 'El tipo de punto de venta no es válido',
            'estado.enum' => 'El estado no es válido',
        ];
    }
}
