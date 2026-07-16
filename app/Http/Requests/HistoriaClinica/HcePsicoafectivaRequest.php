<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HcePsicoafectivaRequest extends FormRequest
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
            'actividad_ludica_preferida' => ['nullable', 'string'],
            'expresion_afecto_familiar' => ['nullable', 'string'],
            'normas_disciplinarias_hogar' => ['nullable', 'string'],
            'reaccion_consecuencias' => ['nullable', 'string'],
            'llora_facilmente' => ['nullable', 'string'],
            'pataletas' => ['nullable', 'string'],
            'agresividad' => ['nullable', 'string'],
            'tics' => ['nullable', 'string'],
            'fobias' => ['nullable', 'string'],
            'mentiras' => ['nullable', 'string'],
            'insomnio' => ['nullable', 'string'],
            'duerme_con' => ['nullable', 'string'],
            'alimentacion' => ['nullable', 'string'],
            'dificultades_estomacales' => ['nullable', 'string'],
            'alergias' => ['nullable', 'string'],
            'control_esfinteres' => ['nullable', 'string'],
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
