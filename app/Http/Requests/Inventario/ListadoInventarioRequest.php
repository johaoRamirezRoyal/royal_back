<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListadoInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['id_area', 'id_categoria', 'estado'] as $key) {
            if ($this->has($key) && !is_array($this->input($key))) {
                $this->merge([$key => [$this->input($key)]]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'id_usuario' => ['nullable', 'integer', Rule::exists('usuarios', 'id_user')],
            'id_area' => ['nullable', 'array'],
            'id_area.*' => ['integer', Rule::exists('areas', 'id')],
            'id_categoria' => ['nullable', 'array'],
            'id_categoria.*' => ['integer', Rule::exists('categoria', 'id')],
            'tipo_categoria' => ['nullable', 'integer', 'in:1,2'],
            'estado' => ['nullable', 'array'],
            'estado.*' => ['integer', Rule::exists('estado', 'id')],
            's' => ['nullable', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_usuario.integer' => 'El usuario debe ser un número entero',
            'id_usuario.exists' => 'El usuario no existe',

            'id_area.array' => 'Las áreas deben ser un arreglo',
            'id_area.*.integer' => 'Cada área debe ser un número entero',
            'id_area.*.exists' => 'Una o más áreas no existen',

            'id_categoria.array' => 'Las categorías deben ser un arreglo',
            'id_categoria.*.integer' => 'Cada categoría debe ser un número entero',
            'id_categoria.*.exists' => 'Una o más categorías no existen',

            'tipo_categoria.integer' => 'La subcategoría debe ser un número entero',
            'tipo_categoria.in' => 'La subcategoría solo puede ser 1 o 2',

            'estado.array' => 'Los estados deben ser un arreglo',
            'estado.*.integer' => 'Cada estado debe ser un número entero',
            'estado.*.exists' => 'Uno o más estados no existen',

            's.string' => 'La búsqueda debe ser un texto',
            's.max' => 'La búsqueda no puede superar los 100 caracteres',

            'descripcion.string' => 'La descripción debe ser un texto',
            'descripcion.max' => 'La descripción no puede superar los 200 caracteres',

            'per_page.integer' => 'Los elementos por página deben ser un número entero',
            'per_page.min' => 'Debe haber al menos 1 elemento por página',
        ];
    }
}
