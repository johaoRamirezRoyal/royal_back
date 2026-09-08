<?php

namespace App\Http\Requests\PermisosLicencias;

use Illuminate\Foundation\Http\FormRequest;

class ActualizarPermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Solo son editables la fecha/hora/tiempo/detalle/evidencia — tipo y motivo del permiso no cambian tras la solicitud. */
    public function rules(): array
    {
        return [
            'fecha_permiso' => ['nullable', 'date'],
            'fecha_retorno' => ['nullable', 'date'],
            'dias_permiso' => ['nullable', 'string', 'max:200'],
            'hora_salida' => ['nullable', 'date_format:H:i'],
            'tiempo_permiso' => ['nullable', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'evidencia_permiso' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
