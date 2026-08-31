<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;

class RechazarSolicitudInicialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'motivo' => $this->motivo ? trim($this->motivo) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:1300'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debe indicar el motivo del rechazo',
            'motivo.max' => 'El motivo no puede superar los 1300 caracteres',
        ];
    }
}
