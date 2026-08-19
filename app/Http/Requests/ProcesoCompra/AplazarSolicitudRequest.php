<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;

class AplazarSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_aplazado' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_aplazado.required' => 'Debe indicar la fecha de aplazamiento',
            'fecha_aplazado.date' => 'La fecha de aplazamiento no es válida',
        ];
    }
}