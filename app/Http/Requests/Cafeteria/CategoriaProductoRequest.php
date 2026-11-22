<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear o editar una categoría del catálogo. Sin padre = categoría raíz. Código CAT-001, del sistema. */
class CategoriaProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('id_categoria_padre')) {
            $this->merge(['id_categoria_padre' => $this->filled('id_categoria_padre') ? $this->input('id_categoria_padre') : null]);
        }
    }

    public function rules(): array
    {
        return [
            'id_categoria_padre' => ['nullable', 'integer', Rule::exists('caf_categorias_productos', 'id')],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_categoria_padre.exists' => 'La categoría padre no existe',
            'nombre.required' => 'El nombre es obligatorio',
        ];
    }
}
