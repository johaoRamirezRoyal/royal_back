<?php

namespace App\Http\Requests\Prestamos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePrestamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => [
                'required',
                'integer',
                Rule::exists('prestamos_inventario', 'id'),
            ],
            'id_user_recibe' => [
                'nullable',
                'integer',
                Rule::exists('usuarios', 'id_user')->where('estado', 'activo'),
            ],
            'fecha_devolucion' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'El ID del préstamo es obligatorio.',
            'id.integer'  => 'El ID del préstamo debe ser un número entero.',
            'id.exists'   => 'El préstamo no existe.',

            'id_user_recibe.integer' => 'El ID del usuario que recibe debe ser un número entero.',
            'id_user_recibe.exists'  => 'El usuario que recibe no existe o no está activo.',

            'fecha_devolucion.date_format' => 'La fecha de devolución debe tener el formato Y-m-d H:i:s.',
            'observacion.max' => 'La observación no debe superar los 500 caracteres.',
        ];
    }
}
