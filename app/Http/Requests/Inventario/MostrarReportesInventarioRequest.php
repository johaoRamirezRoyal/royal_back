<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MostrarReportesInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_inventario' => ['nullable', 'array'],
            'id_inventario.*' => ['integer', Rule::exists('inventario', 'id')],
            'id_user' => ['nullable', 'integer', Rule::exists('usuarios', 'id_user')],
            'id_anio' => ['nullable', 'integer', Rule::exists('anio_escolar', 'id')],
            'id_periodo' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer'],
            'tipo_categoria' => ['nullable', 'integer', 'in:1,2'],
            'tipo_reporte' => ['nullable', 'integer'],
            'sin_solucion' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_inventario.array' => 'Los IDs de inventario deben ser un arreglo',
            'id_inventario.*.integer' => 'Cada ID de inventario debe ser un número entero',
            'id_inventario.*.exists' => 'Uno o más IDs de inventario no existen',

            'id_user.integer' => 'El usuario debe ser un número entero',
            'id_user.exists' => 'El usuario no existe',

            'id_anio.integer' => 'El año escolar debe ser un número entero',
            'id_anio.exists' => 'El año escolar no existe',

            'id_periodo.integer' => 'El periodo debe ser un número entero',
            'id_periodo.exists' => 'El periodo no existe',

            'search.string' => 'La búsqueda debe ser un texto',
            'search.max' => 'La búsqueda no puede superar los 100 caracteres',

            'estado.integer' => 'El estado debe ser un número entero',

            'per_page.integer' => 'Los elementos por página deben ser un número entero',
            'per_page.min' => 'Debe haber al menos 1 elemento por página',
        ];
    }
}
