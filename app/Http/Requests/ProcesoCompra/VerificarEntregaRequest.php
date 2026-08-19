<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerificarEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observacion_cant' => $this->observacion_cant ? trim($this->observacion_cant) : null,
            'observacion_calidad' => $this->observacion_calidad ? trim($this->observacion_calidad) : null,
            'observacion_precios' => $this->observacion_precios ? trim($this->observacion_precios) : null,
            'observacion_plazo' => $this->observacion_plazo ? trim($this->observacion_plazo) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'cantidad' => ['required', Rule::in(['Si', 'No'])],
            'observacion_cant' => ['nullable', 'string', 'max:1300'],
            'calidad' => ['required', Rule::in(['Si', 'No'])],
            'observacion_calidad' => ['nullable', 'string', 'max:1300'],
            'precios' => ['required', Rule::in(['Si', 'No'])],
            'observacion_precios' => ['nullable', 'string', 'max:1300'],
            'plazos' => ['required', Rule::in(['Si', 'No'])],
            'observacion_plazo' => ['nullable', 'string', 'max:1300'],
            'factura_doc' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'decision' => ['required', Rule::in(['cerrar', 'devolucion'])],
        ];
    }

    public function messages(): array
    {
        return [
            'cantidad.required' => 'Debe validar la cantidad recibida',
            'cantidad.in' => 'La cantidad debe ser Si o No',
            'calidad.required' => 'Debe validar la calidad recibida',
            'calidad.in' => 'La calidad debe ser Si o No',
            'precios.required' => 'Debe validar los precios',
            'precios.in' => 'Los precios deben ser Si o No',
            'plazos.required' => 'Debe validar los plazos',
            'plazos.in' => 'Los plazos deben ser Si o No',
            'factura_doc.required' => 'Debe adjuntar la factura',
            'factura_doc.mimes' => 'La factura debe ser PDF, JPG, JPEG, PNG o WEBP',
            'factura_doc.max' => 'La factura no puede superar los 10MB',
            'decision.required' => 'Debe indicar si cierra la compra o activa devolución',
            'decision.in' => 'La decisión debe ser cerrar o devolucion',
        ];
    }
}