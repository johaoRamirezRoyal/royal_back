<?php

namespace App\Http\Requests\Tramites;

use Illuminate\Foundation\Http\FormRequest;

class StoreTramiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validación deliberadamente laxa en los campos "de detalle" — el formulario cambia
     * según `tipo_tramite` (ver TramitesServices::CAMPOS_TRAMITE) y replicar 6 juegos de
     * reglas required_if distintos no aporta frente a validar en el frontend, que ya
     * decide qué mostrar según el tipo elegido.
     */
    public function rules(): array
    {
        return [
            'tipo_tramite' => ['required', 'integer', 'exists:tramite_tipo,id'],
            'motivo' => ['nullable', 'string', 'max:200'],
            'mencion_certificado' => ['nullable', 'string', 'max:200'],
            'otra' => ['nullable', 'string', 'max:200'],
            'entidad_certificado' => ['nullable', 'string', 'max:200'],
            'correo' => ['nullable', 'email', 'max:200'],
            'modo_entrega' => ['nullable', 'string', 'max:200'],
            'anio_grabable' => ['nullable', 'string', 'max:200'],
            'eps_actual' => ['nullable', 'integer'],
            'eps_traslado' => ['nullable', 'integer'],
            'eps_grupo' => ['nullable', 'integer', 'in:1,2'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date'],
            'grupo_familiar_ids' => ['nullable', 'array'],
            'grupo_familiar_ids.*' => ['integer', 'exists:tramite_grupo_familiar,id'],
            'archivos' => ['nullable', 'array'],
            'archivos.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_tramite.required' => 'Selecciona el trámite o servicio',
            'tipo_tramite.exists' => 'El trámite seleccionado no existe',
        ];
    }
}
