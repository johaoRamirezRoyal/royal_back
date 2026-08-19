<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerificarSolicitudRequest extends FormRequest
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
            'observacion' => $this->observacion ? trim($this->observacion) : null,
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
            'decision' => ['required', Rule::in(['aprobar', 'devolver', 'rechazar'])],
            'observacion' => ['nullable', 'string', 'max:1300'],
        ];
    }

    public function messages(): array
    {
        return [
            'cantidad.required' => 'Debe evaluar la cantidad',
            'cantidad.in' => 'La cantidad debe ser Si o No',
            'calidad.required' => 'Debe evaluar la calidad',
            'calidad.in' => 'La calidad debe ser Si o No',
            'precios.required' => 'Debe evaluar los precios',
            'precios.in' => 'Los precios deben ser Si o No',
            'plazos.required' => 'Debe evaluar los plazos',
            'plazos.in' => 'Los plazos deben ser Si o No',
            'decision.required' => 'Debe indicar la decisión (aprobar, devolver o rechazar)',
            'decision.in' => 'La decisión debe ser aprobar, devolver o rechazar',
            'observacion.max' => 'La observación no puede superar los 1300 caracteres',
        ];
    }
}