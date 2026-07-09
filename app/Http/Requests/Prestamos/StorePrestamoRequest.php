<?php

namespace App\Http\Requests\Prestamos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_inventario' => [
                'required',
                'integer',
                Rule::exists('inventario', 'id')->where('activo', 1),
            ],
            'id_user_entrega' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id_user')->where('estado', 'activo'),
            ],
            'id_user_prestamo' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id_user')->where('estado', 'activo'),
            ],
            'fecha_compromiso' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_inventario.required' => 'El ID del inventario es obligatorio.',
            'id_inventario.integer'  => 'El ID del inventario debe ser un número entero.',
            'id_inventario.exists'   => 'El inventario no existe o no está activo.',

            'id_user_entrega.required' => 'El ID del usuario que entrega es obligatorio.',
            'id_user_entrega.integer'  => 'El ID del usuario que entrega debe ser un número entero.',
            'id_user_entrega.exists'   => 'El usuario que entrega no existe o no está activo.',

            'id_user_prestamo.required' => 'El ID del usuario que recibe el préstamo es obligatorio.',
            'id_user_prestamo.integer'  => 'El ID del usuario que recibe el préstamo debe ser un número entero.',
            'id_user_prestamo.exists'   => 'El usuario del préstamo no existe o no está activo.',

            'fecha_compromiso.date_format' => 'La fecha de compromiso debe tener el formato Y-m-d H:i:s.',
            'observacion.max' => 'La observación no debe superar los 500 caracteres.',
        ];
    }
}
