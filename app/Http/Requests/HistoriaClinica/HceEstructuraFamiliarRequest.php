<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HceEstructuraFamiliarRequest extends FormRequest
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
            'relacion_padres' => ['nullable', 'string'],
            'otras_personas_significativas' => ['nullable', 'string'],
            'antecedentes_familiares_fisicos' => ['nullable', 'string'],
            'antecedentes_familiares_psicologicos' => ['nullable', 'string'],
            'comentario_familiar_relevante' => ['nullable', 'string'],
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
