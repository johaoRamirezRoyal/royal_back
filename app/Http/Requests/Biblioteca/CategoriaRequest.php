<?php

namespace App\Http\Requests\Biblioteca;
use Illuminate\Foundation\Http\FormRequest;

class CategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = ($this->isMethod("POST")) ? "required" : "sometimes";
        return [
            'nombre' => [
                $isRequired,
                'string',
                'max:200'
            ],

            'clasificacion' => [
                'nullable',
                'string',
                'max:200'
            ],

            'color' => [
                'nullable',
                'string',
                'max:200'
            ],

            'activo' => [
                'nullable',
                'integer',
                'in:0,1'
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.max' => 'El nombre no puede superar los 200 caracteres',

            'clasificacion.max' => 'La clasificación es demasiado larga',

            'color.max' => 'El color es demasiado largo',

            'activo.in' => 'El estado activo debe ser 0 o 1'
        ];
    }
}
