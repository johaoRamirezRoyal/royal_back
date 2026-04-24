<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarInventarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "descripcion" => ['required', 'string', 'max:255'],

            "marca" => ['nullable', 'string', 'max:100'],

            "modelo" => ['nullable', 'string', 'max:100'],

            "precio" => ['nullable', 'numeric', 'min:0'],

            "estado" => [
                'integer',
                Rule::exists('estado', 'id'),
            ],

            "id_usuario" => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id_user')->where('estado', 'activo'),
            ],

            "activo" => [
                'required',
                'in:0,1',
            ],

            "fecha_compra" => [
                'date',
                'nullable'
            ],

            "id_area" => [
                'required',
                'integer',
                Rule::exists("areas", "id")->where('activo', 1),
            ],

            "id_categoria" => [
                'required',
                'integer',
                Rule::exists('categoria', 'id')
            ],

            "id_compra" => [
                'nullable',
                'integer',
            ]
        ];
    }

    public function messages(): array
    {
        return [
            // descripcion
            'descripcion.required' => 'La descripción es obligatoria',
            'descripcion.string' => 'La descripción debe ser un texto',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres',

            // marca
            'marca.string' => 'La marca debe ser un texto',
            'marca.max' => 'La marca no puede superar los 100 caracteres',

            // modelo
            'modelo.string' => 'El modelo debe ser un texto',
            'modelo.max' => 'El modelo no puede superar los 100 caracteres',

            // precio
            'precio.numeric' => 'El precio debe ser un valor numérico',
            'precio.min' => 'El precio no puede ser negativo',

            // estado
            'estado.required' => 'El estado es obligatorio',
            'estado.integer' => 'El estado debe ser un número válido',
            'estado.exists' => 'El estado seleccionado no existe',

            // id_usuario
            'id_usuario.required' => 'El usuario es obligatorio',
            'id_usuario.integer' => 'El usuario debe ser un número válido',
            'id_usuario.exists' => 'El usuario no existe o no está activo',

            // activo
            'activo.required' => 'El campo activo es obligatorio',
            'activo.in' => 'El campo activo solo puede ser 0 (inactivo) o 1 (activo)',

            // fecha_compra
            'fecha_compra.date' => 'La fecha de compra debe ser una fecha válida',

            // id_area
            'id_area.required' => 'El área es obligatoria',
            'id_area.integer' => 'El área debe ser un número válido',
            'id_area.exists' => 'El área no existe o no está activa',

            // id_categoria
            'id_categoria.required' => 'La categoría es obligatoria',
            'id_categoria.integer' => 'La categoría debe ser un número válido',
            'id_categoria.exists' => 'La categoría seleccionada no existe',

            // id_compra
            'id_compra.integer' => 'El ID de compra debe ser un número válido',
        ];
    }

    public function toInventarioCreate(): array {
        return [
            'descripcion' => $this->descripcion,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'precio' => $this->precio,
            'estado' => $this->estado,
            'id_usuario' => $this->id_usuario,
            'activo' => $this->activo,
            'fecha_compra' => $this->fecha_compra,
            'id_area' => $this->id_area,
            'id_categoria' => $this->id_categoria,
            'id_compra' => $this->id_compra,
        ];
    }
}
