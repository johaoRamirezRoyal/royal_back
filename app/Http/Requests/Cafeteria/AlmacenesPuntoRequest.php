<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;

/** Reemplaza los almacenes de un punto: { almacenes: [{ id_almacen, es_predeterminado }] }. */
class AlmacenesPuntoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'almacenes' => ['present', 'array', 'max:50'],
            'almacenes.*.id_almacen' => ['required', 'integer', 'distinct'],
            'almacenes.*.es_predeterminado' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'almacenes.present' => 'Envía la lista de almacenes (vacía para quitarlos)',
            'almacenes.*.id_almacen.distinct' => 'Un almacén está repetido',
        ];
    }
}
