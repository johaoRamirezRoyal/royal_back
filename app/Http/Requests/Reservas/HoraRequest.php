<?php

namespace App\Http\Requests\Reservas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            // Formato "HH:MM - HH:MM" (ver parseRangoHora en el frontend,
            // src/pages/Reservas/helpers/agruparReservas.utils.ts).
            'horas' => [
                $isRequired,
                'string',
                'max:50',
                Rule::unique('horas', 'horas')
                    ->where(fn ($q) => $q->where('activo', 1))
                    ->ignore($this->route('id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'horas.required' => 'La franja horaria es obligatoria',
            'horas.string' => 'La franja horaria debe ser una cadena de texto',
            'horas.max' => 'La franja horaria no puede superar los 50 caracteres',
            'horas.unique' => 'Ya existe una franja horaria activa con ese valor',
        ];
    }
}
