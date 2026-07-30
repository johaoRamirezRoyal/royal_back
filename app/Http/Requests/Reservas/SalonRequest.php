<?php

namespace App\Http\Requests\Reservas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'nombre' => [
                $isRequired,
                'string',
                'max:150',
                Rule::unique('salones', 'nombre')
                    ->where(fn($q) => $q->where('activo', 1))
                    ->ignore($this->route('id')),
            ],
            'portatil' => [
                $isRequired,
                'integer',
                'min:0',
            ],
            'sonido' => [
                $isRequired,
                'integer',
                'in:0,1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.string' => 'El nombre debe ser una cadena de texto',
            'nombre.max' => 'El nombre no puede superar los 150 caracteres',
            'nombre.unique' => 'Ya existe un salón activo con ese nombre',

            'portatil.integer' => 'La capacidad de portátiles debe ser un número entero',
            'portatil.min' => 'La capacidad de portátiles no puede ser negativa',

            'sonido.integer' => 'El campo sonido debe ser un número entero',
            'sonido.in' => 'El campo sonido debe ser 0 (no tiene) o 1 (sí tiene)',
        ];
    }
}
