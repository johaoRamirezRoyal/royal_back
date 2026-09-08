<?php

namespace App\Http\Requests\PermisosLicencias;

use Illuminate\Foundation\Http\FormRequest;

class StorePermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validación deliberadamente laxa en los campos "de detalle" — el formulario cambia
     * según `tipo_permiso` (Parcial vs. Día completo), replicar dos juegos de reglas
     * required_if no aporta frente a validar en el frontend, que ya decide qué mostrar.
     */
    public function rules(): array
    {
        return [
            'tipo_permiso' => ['required', 'integer', 'exists:permiso_tipo,id'],
            'motivo_permiso' => ['required', 'integer', 'exists:permiso_motivo,id'],
            'tipo_permiso_detalle' => ['nullable', 'string', 'max:100'],
            'fecha_permiso' => ['nullable', 'date'],
            'fecha_retorno' => ['nullable', 'date'],
            'dias_permiso' => ['nullable', 'string', 'max:200'],
            'hora_salida' => ['nullable', 'date_format:H:i'],
            'tiempo_permiso' => ['nullable', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'id_user_asignado' => ['nullable', 'integer', 'exists:usuarios,id_user'],
            'evidencia_permiso' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_permiso.required' => 'Selecciona el tipo de permiso',
            'motivo_permiso.required' => 'Selecciona el motivo del permiso',
        ];
    }
}
