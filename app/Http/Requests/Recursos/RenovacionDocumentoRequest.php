<?php

namespace App\Http\Requests\Recursos;

use App\Services\recursos\RecursosDocumentosServices;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenovacionDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_proceso' => ['required', 'integer', Rule::exists('procesos_documento', 'id')],
            'nom_documento' => ['required', 'string', 'max:200'],
            'version_doc' => ['required', 'string', 'max:200'],
            'fecha_vigencia' => ['required', 'date'],
            'fecha_revision' => ['nullable', 'date'],
            'categ_doc' => ['required', Rule::in(array_keys(RecursosDocumentosServices::CATEGORIAS))],
            'gestion_cambio' => ['nullable', 'string', 'max:1300'],
            'url_archivo' => ['required_if:categ_doc,1', 'nullable', 'string', 'max:200'],
            'proxima_fecha' => ['nullable', Rule::in(array_keys(RecursosDocumentosServices::PROXIMAS_FECHAS))],
            'tiempo_retencion' => ['nullable', Rule::in(RecursosDocumentosServices::TIEMPOS_RETENCION)],
            'archivo' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_proceso.required' => 'Selecciona el tipo de proceso',
            'tipo_proceso.exists' => 'El tipo de proceso seleccionado no existe',
            'nom_documento.required' => 'El nombre del documento es obligatorio',
            'version_doc.required' => 'La versión del documento es obligatoria',
            'fecha_vigencia.required' => 'La fecha de vigencia es obligatoria',
            'categ_doc.required' => 'Selecciona la categoría del documento',
            'url_archivo.required_if' => 'La URL del archivo es obligatoria para un documento Virtual',
        ];
    }
}
