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
                'string',
                'in:si,no',
            ],
            'sonido' => [
                $isRequired,
                'string',
                'in:si,no',
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

            'portatil.string' => 'El campo portátil debe ser una cadena de texto',
            'portatil.in' => 'El campo portátil debe ser "si" o "no"',

            'sonido.string' => 'El campo sonido debe ser una cadena de texto',
            'sonido.in' => 'El campo sonido debe ser "si" o "no"',
        ];
    }
}
