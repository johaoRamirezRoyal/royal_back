<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HceRemisionRequest extends FormRequest
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
            'neurodesarrollo' => ['nullable', 'string'],
            'fonoaudiologia' => ['nullable', 'string'],
            'psicologia_clinica' => ['nullable', 'string'],
            'psicologia_aprendizaje' => ['nullable', 'string'],
            'terapia_ocupacional' => ['nullable', 'string'],
            'otra' => ['nullable', 'string'],
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
