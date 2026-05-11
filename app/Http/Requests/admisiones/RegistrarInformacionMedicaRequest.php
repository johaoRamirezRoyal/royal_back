<?php

namespace App\Http\Requests\Admisiones;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarInformacionMedicaRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        $requiredRule = $this->isMethod('post')
            ? 'required'
            : 'sometimes';
        return [

            'aspirante_id' => $requiredRule . '|integer|exists:admisiones_aspirantes,id',
            'id_inscripcion' => $requiredRule .  '|integer|exists:admisiones_inscripciones,id',

            'medico_nombre' => 'nullable|string|max:150',
            'medico_telefono' => 'nullable|string|max:20',

            'tiene_alergias' => 'nullable|boolean',
            'detalle_alergias' => 'nullable|string',

            'necesita_cuidados' => 'nullable|boolean',
            'detalle_cuidados' => 'nullable|string',

            'recibe_ayuda' => 'nullable|boolean',

            'terapia_ocupacional' => 'nullable|boolean',
            'terapia_lenguaje' => 'nullable|boolean',
            'terapia_psicologica' => 'nullable|boolean',
            'fonoaudiologia' => 'nullable|boolean',
            'terapia_otros' => 'nullable|boolean',

            'profesional_nombre' => 'nullable|string|max:150',
            'profesional_telefono' => 'nullable|string|max:20',
        ];
    }

    /**
     * Mensajes personalizados
     */
    public function messages(): array
    {
        return [

            'aspirante_id.required' => 'El ID del aspirante es obligatorio.',
            'aspirante_id.exists' => 'El aspirante no existe.',

            'id_inscripcion.required' => 'El ID de la inscripción es obligatorio.',
            'id_inscripcion.exists' => 'La inscripción no existe.',

            'medico_nombre.max' => 'El nombre del médico no puede superar 150 caracteres.',
            'medico_telefono.max' => 'El teléfono del médico no puede superar 20 caracteres.',

            'profesional_nombre.max' => 'El nombre del profesional no puede superar 150 caracteres.',
            'profesional_telefono.max' => 'El teléfono del profesional no puede superar 20 caracteres.',
        ];
    }

    /**
     * Normalizar datos antes de validar
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'tiene_alergias' => filter_var($this->tiene_alergias, FILTER_VALIDATE_BOOLEAN),
            'necesita_cuidados' => filter_var($this->necesita_cuidados, FILTER_VALIDATE_BOOLEAN),
            'recibe_ayuda' => filter_var($this->recibe_ayuda, FILTER_VALIDATE_BOOLEAN),
            'terapia_ocupacional' => filter_var($this->terapia_ocupacional, FILTER_VALIDATE_BOOLEAN),
            'terapia_lenguaje' => filter_var($this->terapia_lenguaje, FILTER_VALIDATE_BOOLEAN),
            'terapia_psicologica' => filter_var($this->terapia_psicologica, FILTER_VALIDATE_BOOLEAN),
            'fonoaudiologia' => filter_var($this->fonoaudiologia, FILTER_VALIDATE_BOOLEAN),
            'terapia_otros' => filter_var($this->terapia_otros, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}