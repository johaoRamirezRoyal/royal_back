<?php

namespace App\Http\Requests\Cafeteria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Agregar (proveedor y producto) o editar un producto que ofrece un proveedor. */
class ProveedorProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['codigo_producto_proveedor', 'precio_referencia', 'cantidad_minima'] as $campo) {
            if ($this->has($campo) && ! $this->filled($campo)) {
                $this->merge([$campo => null]);
            }
        }
    }

    public function rules(): array
    {
        $creando = $this->isMethod('post');

        return [
            'id_proveedor' => [$creando ? 'required' : 'prohibited', 'integer'],
            'id_producto' => [$creando ? 'required' : 'prohibited', 'integer', Rule::exists('caf_productos', 'id')],
            'codigo_producto_proveedor' => ['nullable', 'string', 'max:60'],
            'precio_referencia' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'cantidad_minima' => ['nullable', 'numeric', 'gt:0', 'max:99999999999'],
            'es_preferido' => ['sometimes', 'boolean'],
            'activo' => [$creando ? 'prohibited' : 'sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_proveedor.required' => 'Elige el proveedor',
            'id_producto.required' => 'Elige el producto',
            'id_producto.exists' => 'El producto no existe',
            'id_proveedor.prohibited' => 'El proveedor de un vínculo no se cambia',
            'id_producto.prohibited' => 'El producto de un vínculo no se cambia',
            'activo.prohibited' => 'Un vínculo nuevo siempre empieza activo',
            'precio_referencia.min' => 'El precio no puede ser negativo',
            'cantidad_minima.gt' => 'La cantidad mínima debe ser mayor que 0',
        ];
    }
}
