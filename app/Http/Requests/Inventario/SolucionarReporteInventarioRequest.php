<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SolucionarReporteInventarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_reporte' => ['required', 'integer', Rule::exists('reportes', 'id')],
            'id_resp' => ['required', 'integer', Rule::exists('usuarios', 'id_user')],
            'fecha_respuesta' => ['nullable', 'date'],
            'descripcion' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_reporte.required' => 'El ID del reporte es obligatorio',
            'id_reporte.integer' => 'El ID del reporte debe ser un número entero',
            'id_reporte.exists' => 'El reporte no existe',

            'id_resp.required' => 'El responsable es obligatorio',
            'id_resp.integer' => 'El responsable debe ser un número entero',
            'id_resp.exists' => 'El responsable no existe',

            'fecha_respuesta.date' => 'La fecha de respuesta debe ser una fecha válida',

            'descripcion.required' => 'La descripción de la solución es obligatoria',
            'descripcion.string' => 'La descripción debe ser un texto',
            'descripcion.max' => 'La descripción no puede superar los 500 caracteres',
        ];
    }

    public function toSolucionarReporte(): array
    {
        return [
            'id_reporte' => $this->id_reporte,
            'id_resp' => $this->id_resp,
            'fecha_respuesta' => $this->fecha_respuesta,
            'descripcion' => $this->descripcion,
        ];
    }
}
