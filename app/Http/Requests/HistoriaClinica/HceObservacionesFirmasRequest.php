<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HceObservacionesFirmasRequest extends FormRequest
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
            'observaciones' => ['nullable', 'string'],
            'firma_padre' => ['nullable', 'string'],
            'firma_madre' => ['nullable', 'string'],
            'firma_psicologa' => ['nullable', 'string'],
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
