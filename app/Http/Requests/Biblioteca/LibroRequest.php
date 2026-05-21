<?php

namespace App\Http\Requests\Biblioteca;

use Illuminate\Foundation\Http\FormRequest;

class LibroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        $isRequired = ($this->isMethod("POST")) ? "required" : "sometimes";
        
        return [

            'titulo' => [
                $isRequired,
                'string',
                'max:200'
            ],

            'autor' => [
                $isRequired,
                'string',
                'max:200'
            ],

            'editorial' => [
                'nullable',
                'string',
                'max:200'
            ],

            'edicion' => [
                'nullable',
                'string',
                'max:200'
            ],

            'id_categoria' => [
                $isRequired,
                'integer',
                'exists:biblioteca_categorias,id'
            ],

            'id_subcategoria' => [
                'nullable',
                'integer',
                'exists:biblioteca_subcategoria,id'
            ],

            'observacion' => [
                'nullable',
                'string'
            ],

            'foto' => [
                'nullable',
                'string'
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

            'titulo.required' =>
            'El título es obligatorio',

            'titulo.max' =>
            'El título no puede superar 200 caracteres',


            'autor.required' =>
            'El autor es obligatorio',

            'autor.max' =>
            'El autor no puede superar 200 caracteres',


            'editorial.max' =>
            'La editorial no puede superar 200 caracteres',

            'edicion.max' =>
            'La edición no puede superar 200 caracteres',


            'id_categoria.required' =>
            'La categoría es obligatoria',

            'id_categoria.exists' =>
            'La categoría seleccionada no existe',


            'id_subcategoria.exists' =>
            'La subcategoría seleccionada no existe',


            'activo.in' =>
            'El estado debe ser 0 o 1'
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->activo ?? 1
        ]);
    }
}
