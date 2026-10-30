<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class ProgramacionAvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'integer', 'between:0,4'],
            'fecha_registro' => ['required', 'date'],
            'observacion' => ['nullable', 'string', 'max:255'],
            'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Selecciona el estado',
            'fecha_registro.required' => 'La fecha es obligatoria',
            'observacion.max' => 'La observación no puede superar los 255 caracteres',
            'archivo.mimes' => 'El archivo debe ser PDF o imagen (JPG, PNG)',
            'archivo.max' => 'El archivo no puede superar los 10 MB',
        ];
    }
}
