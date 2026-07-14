<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportarInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('inventario', 'id')],
            'id_log' => ['required', 'integer', Rule::exists('usuarios', 'id_user')],
            'descripcion' => ['required', 'string', 'max:500'],
            'id_anio' => ['required', 'integer', Rule::exists('anio_escolar', 'id')],
            'id_periodo' => ['required', 'integer', Rule::exists('periodos', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Los IDs del inventario son obligatorios',
            'ids.array' => 'Los IDs deben ser un arreglo',
            'ids.min' => 'Debe incluir al menos un elemento',
            'ids.*.integer' => 'Cada ID debe ser un número entero',
            'ids.*.distinct' => 'Los IDs no pueden repetirse',
            'ids.*.exists' => 'Uno o más IDs de inventario no existen',

            'id_log.required' => 'El usuario que reporta es obligatorio',
            'id_log.integer' => 'El usuario debe ser un número entero',
            'id_log.exists' => 'El usuario no existe',

            'descripcion.required' => 'La descripción del reporte es obligatoria',
            'descripcion.string' => 'La descripción debe ser un texto',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres',

            'id_anio.required' => 'El año escolar es obligatorio',
            'id_anio.integer' => 'El año escolar debe ser un número entero',
            'id_anio.exists' => 'El año escolar no existe',

            'id_periodo.required' => 'El periodo es obligatorio',
            'id_periodo.integer' => 'El periodo debe ser un número entero',
            'id_periodo.exists' => 'El periodo no existe',
        ];
    }

    public function toReportarInventario(): array
    {
        return [
            'ids' => $this->ids,
            'id_log' => $this->id_log,
            'descripcion' => $this->descripcion,
            'id_anio' => $this->id_anio,
            'id_periodo' => $this->id_periodo,
        ];
    }
}
