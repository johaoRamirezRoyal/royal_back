<?php

namespace App\Http\Requests\HistoriaClinica;

use Illuminate\Foundation\Http\FormRequest;

class HceEmbarazoPartoRequest extends FormRequest
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
            'embarazo_tipo' => ['nullable', 'string'],
            'tiempo_gestacion' => ['nullable', 'string'],
            'parto_tipo' => ['nullable', 'string'],
            'uso_forceps' => ['nullable', 'string'],
            'dificultades_embarazo' => ['nullable', 'string'],
            'requirio_oxygeno_nacer' => ['nullable', 'string'],
            'lloro_inmediatamente_nacer' => ['nullable', 'string'],
            'presento_ictericia' => ['nullable', 'string'],
            'sufrio_anoxia' => ['nullable', 'string'],
            'convulsiono' => ['nullable', 'string'],
            'presento_erupciones_piel' => ['nullable', 'string'],
            'permanecio_hospital_mas_tiempo' => ['nullable', 'string'],
            'estado_salud_madre' => ['nullable', 'string'],
            'estado_emocional_madre' => ['nullable', 'string'],
            'estado_nino_al_nacer' => ['nullable', 'string'],
            'alimentacion_nino_seno' => ['nullable', 'string'],
            'juega_solo_o_con_otros' => ['nullable', 'string'],
            'sostenia_tetero_solo' => ['nullable', 'string'],
            'usa_cuchara_tenedor' => ['nullable', 'string'],
            'descripcion_alimentacion_hijo' => ['nullable', 'string'],
            'tiene_rabietas' => ['nullable', 'string'],
            'llora_facilmente' => ['nullable', 'string'],
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
