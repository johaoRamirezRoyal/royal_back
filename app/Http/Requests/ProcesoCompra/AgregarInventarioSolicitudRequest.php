<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgregarInventarioSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $idSolicitud = $this->route('id');

        return [
            'articulos' => ['required', 'array', 'min:1'],
            'articulos.*.id_producto' => [
                'required',
                'integer',
                Rule::exists('solicitud_productos', 'id')->where('id_solicitud', $idSolicitud),
            ],
            'articulos.*.cantidad' => ['required', 'integer', 'min:1'],
            'articulos.*.id_area' => ['required', 'integer', Rule::exists('areas', 'id')->where('activo', 1)],
            'articulos.*.id_usuario' => ['required', 'integer', Rule::exists('usuarios', 'id_user')->where('estado', 'activo')],
            'articulos.*.id_categoria' => ['nullable', 'integer', Rule::exists('categoria', 'id')],
            'articulos.*.marca' => ['nullable', 'string', 'max:200'],
            'articulos.*.modelo' => ['nullable', 'string', 'max:200'],
            'articulos.*.estado' => ['nullable', 'integer', Rule::exists('estado', 'id')],
            'articulos.*.precio' => ['nullable', 'numeric', 'min:0'],
            'articulos.*.fecha_compra' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'articulos.required' => 'Debe indicar al menos un artículo',
            'articulos.min' => 'Debe indicar al menos un artículo',
            'articulos.*.id_producto.required' => 'Debe indicar el artículo de la solicitud',
            'articulos.*.id_producto.exists' => 'El artículo no pertenece a esta solicitud',
            'articulos.*.cantidad.required' => 'Debe indicar la cantidad a ingresar',
            'articulos.*.cantidad.min' => 'La cantidad a ingresar debe ser al menos 1',
            'articulos.*.id_area.required' => 'Debe indicar el área del inventario',
            'articulos.*.id_area.exists' => 'El área no existe o no está activa',
            'articulos.*.id_usuario.required' => 'Debe indicar el usuario responsable',
            'articulos.*.id_usuario.exists' => 'El usuario no existe o no está activo',
            'articulos.*.id_categoria.required' => 'Debe indicar la categoría del inventario',
            'articulos.*.id_categoria.exists' => 'La categoría seleccionada no existe',
            'articulos.*.marca.max' => 'La marca no puede superar los 200 caracteres',
            'articulos.*.modelo.max' => 'El modelo no puede superar los 200 caracteres',
            'articulos.*.estado.exists' => 'El estado seleccionado no existe',
            'articulos.*.precio.numeric' => 'El precio debe ser un valor numérico',
            'articulos.*.precio.min' => 'El precio no puede ser negativo',
            'articulos.*.fecha_compra.date' => 'La fecha de compra no es válida',
        ];
    }
}