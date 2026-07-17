<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HceCognitivoLenguajeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("POST") ? "required" : "sometimes";
        $isUpdated = $this->isMethod("PUT") ? "required" : "sometimes";
        return [
            'id' => [$isUpdated, 'integer'],
            'id_inscripcion' => [$isRequired, 'integer'],
            'comprension_instrucciones' => ['nullable', 'string'],
            'desarrollo_lectoescritura' => ['nullable', 'string'],
            'dominio_segunda_lengua' => ['nullable', 'string'],
            'lenguaje_estado' => ['nullable', 'string'],
            'updated_by' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_inscripcion.required' => 'La inscripción es obligatoria.',
        ];
    }
}
