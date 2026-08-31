<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;

class AprobarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'observacion' => $this->observacion ? trim($this->observacion) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'observacion' => ['nullable', 'string', 'max:1300'],
        ];
    }

    public function messages(): array
    {
        return [
            'observacion.max' => 'La observación no puede superar los 1300 caracteres',
        ];
    }
}
