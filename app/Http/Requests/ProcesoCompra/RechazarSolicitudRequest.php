<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;

class RechazarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => $this->motivo ? trim($this->motivo) : null,
            'observacion' => $this->observacion ? trim($this->observacion) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:1300'],
            'observacion' => ['nullable', 'string', 'max:1300'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe indicar el motivo del rechazo',
            'motivo.max' => 'El motivo no puede superar los 1300 caracteres',
            'observacion.max' => 'La observación no puede superar los 1300 caracteres',
        ];
    }
}