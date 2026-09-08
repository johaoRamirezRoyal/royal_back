<?php

namespace App\Http\Requests\Certificados;

use App\Services\certificados\CertificadosServices;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSolicitudCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lugar' => ['required', 'string', 'max:200'],
            'cargo' => ['required', 'string', 'max:200'],
            'nombre_entidad' => ['required', 'string', 'max:200'],
            'trabaja_act' => ['required', Rule::in(['Si', 'No'])],
            'tipo_cert' => ['required', Rule::in(array_keys(CertificadosServices::TIPOS))],
            'anio' => ['required_if:tipo_cert,2', 'nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'lugar.required' => 'El lugar de expedición del documento es obligatorio',
            'cargo.required' => 'El cargo que ocupa es obligatorio',
            'nombre_entidad.required' => 'La entidad dirigida en el certificado es obligatoria',
            'trabaja_act.required' => 'Indica si trabaja actualmente',
            'tipo_cert.required' => 'Selecciona el certificado a solicitar',
            'anio.required_if' => 'El año gravable es obligatorio para este certificado',
        ];
    }
}
