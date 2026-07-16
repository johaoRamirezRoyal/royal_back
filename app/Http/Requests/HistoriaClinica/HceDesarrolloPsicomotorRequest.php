<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HceDesarrolloPsicomotorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("POST") ? "required" : "sometimes";

        return [
            'id_inscripcion' => [$isRequired, 'integer'],
            'sostenimiento_cabeza' => ['nullable', 'string'],
            'sentarse_solo' => ['nullable', 'string'],
            'equilibrio' => ['nullable', 'string'],
            'gateo' => ['nullable', 'string'],
            'caminar' => ['nullable', 'string'],
            'seguimiento_ocular' => ['nullable', 'string'],
            'agarre_pinza' => ['nullable', 'string'],
            'abotonarse' => ['nullable', 'string'],
            'recorte' => ['nullable', 'string'],
            'trazo' => ['nullable', 'string'],
            'lateralidad' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
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
