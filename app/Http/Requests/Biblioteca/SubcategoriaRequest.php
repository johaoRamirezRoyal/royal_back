<?php

namespace App\Http\Requests\Biblioteca;
use Illuminate\Foundation\Http\FormRequest;


class SubcategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    } 

    public function rules(): array
    {
        $isRequired = ($this->isMethod("POST")) ? "required" : "sometimes";
        return [

            'id_categoria' => [
                $isRequired,
                'integer',
                'exists:biblioteca_categorias,id'
            ],

            'nombre' => [
                $isRequired,
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

            'id_categoria.required' =>
            'La id_categoria es obligatoria',

            'id_categoria.exists' =>
            'La categoría seleccionada no existe',

            'nombre.required' =>
            'El nombre de la subcategoría es obligatorio',

            'nombre.max' =>
            'El nombre no puede superar los 200 caracteres',

            'activo.in' =>
            'El estado activo debe ser 0 o 1'
        ];
    }
}