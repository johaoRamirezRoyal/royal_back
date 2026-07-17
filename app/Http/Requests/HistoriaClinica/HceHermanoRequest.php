<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HceHermanoRequest extends FormRequest
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
            'nombre' => [$isRequired, 'string', 'max:255'],
            'edad' => ['nullable', 'integer'],
            'tipo_relacion' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_inscripcion.required' => 'La inscripción es obligatoria.',
            'nombre.required' => 'El nombre es obligatorio.',
        ];
    }
}
