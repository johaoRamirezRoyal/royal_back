<?php

namespace App\Http\Requests\Cafeteria;

use App\Enums\Cafeteria\TipoProducto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Crear o editar un producto. El código (SKU, PRD-0001) lo asigna el sistema. */
class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('codigo_barras')) {
            $this->merge(['codigo_barras' => $this->filled('codigo_barras') ? trim((string) $this->input('codigo_barras')) : null]);
        }
    }

    public function rules(): array
    {
        return [
            'id_categoria' => ['required', 'integer', Rule::exists('caf_categorias_productos', 'id')],
            'id_unidad_base' => ['required', 'integer', Rule::exists('caf_unidades_medida', 'id')],
            'codigo_barras' => ['nullable', 'string', 'max:60', Rule::unique('caf_productos', 'codigo_barras')->ignore($this->route('producto'))],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'tipo_producto' => ['required', Rule::enum(TipoProducto::class)],
            'controla_inventario' => ['required', 'boolean'],
            'es_vendible' => ['required', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_categoria.required' => 'Elige la categoría',
            'id_categoria.exists' => 'La categoría no existe',
            'id_unidad_base.required' => 'Elige la unidad',
            'id_unidad_base.exists' => 'La unidad no existe',
            'codigo_barras.unique' => 'Otro producto ya tiene ese código de barras',
            'nombre.required' => 'El nombre es obligatorio',
            'tipo_producto.required' => 'Elige el tipo de producto',
            'tipo_producto.enum' => 'El tipo de producto no es válido',
        ];
    }
}
