<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HceHistoriaEscolarRequest extends FormRequest
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
            'edad_escolarizacion' => ['nullable', 'string'],
            'nombre_colegio' => ['nullable', 'string'],
            'adaptacion' => ['nullable', 'string'],
            'relacion_companeros' => ['nullable', 'string'],
            'relacion_profesores' => ['nullable', 'string'],
            'fortalezas_academicas' => ['nullable', 'string'],
            'dificultades_academicas' => ['nullable', 'string'],
            'refuerzo_academico' => ['nullable', 'string'],
            'anos_perdidos_causas' => ['nullable', 'string'],
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
