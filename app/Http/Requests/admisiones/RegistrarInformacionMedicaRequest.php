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
        if ($this->isMethod('post')) {
            return [
                'informacion_medica' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'informacion_medica.*.id_inscripcion' => 'required|integer|exists:admisiones_inscripciones,id',

                'informacion_medica.*.medico_nombre' => 'nullable|string|max:150',
                'informacion_medica.*.medico_telefono' => 'nullable|string|max:20',

                'informacion_medica.*.tiene_alergias' => 'nullable|boolean',
                'informacion_medica.*.detalle_alergias' => 'nullable|string',

                'informacion_medica.*.necesita_cuidados' => 'nullable|boolean',
                'informacion_medica.*.detalle_cuidados' => 'nullable|string',

                'informacion_medica.*.recibe_ayuda' => 'nullable|boolean',

                'informacion_medica.*.terapia_ocupacional' => 'nullable|boolean',
                'informacion_medica.*.terapia_lenguaje' => 'nullable|boolean',
                'informacion_medica.*.terapia_psicologica' => 'nullable|boolean',
                'informacion_medica.*.fonoaudiologia' => 'nullable|boolean',
                'informacion_medica.*.terapia_otros' => 'nullable|boolean',

                'informacion_medica.*.profesional_nombre' => 'nullable|string|max:150',
                'informacion_medica.*.profesional_telefono' => 'nullable|string|max:20',
            ];
        }

        return [
            'id_inscripcion' => 'sometimes|integer|exists:admisiones_inscripciones,id',

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
            'informacion_medica.required' => 'Debe enviar al menos un registro de información médica.',
            'informacion_medica.array' => 'El formato de información médica es inválido.',
            'informacion_medica.min' => 'Debe enviar al menos un registro de información médica.',

            'informacion_medica.*.id_inscripcion.required' => 'El ID de la inscripción es obligatorio.',
            'informacion_medica.*.id_inscripcion.exists' => 'La inscripción no existe.',

            'informacion_medica.*.medico_nombre.max' => 'El nombre del médico no puede superar 150 caracteres.',
            'informacion_medica.*.medico_telefono.max' => 'El teléfono del médico no puede superar 20 caracteres.',

            'informacion_medica.*.profesional_nombre.max' => 'El nombre del profesional no puede superar 150 caracteres.',
            'informacion_medica.*.profesional_telefono.max' => 'El teléfono del profesional no puede superar 20 caracteres.',

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
        $booleanFields = [
            'tiene_alergias',
            'necesita_cuidados',
            'recibe_ayuda',
            'terapia_ocupacional',
            'terapia_lenguaje',
            'terapia_psicologica',
            'fonoaudiologia',
            'terapia_otros',
        ];

        if ($this->isMethod('post') && is_array($this->input('informacion_medica'))) {
            $items = $this->input('informacion_medica');

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach ($booleanFields as $field) {
                    if (array_key_exists($field, $item)) {
                        $items[$index][$field] = filter_var($item[$field], FILTER_VALIDATE_BOOLEAN);
                    }
                }
            }

            $this->merge(['informacion_medica' => $items]);

            return;
        }

        $normalized = [];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $normalized[$field] = filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN);
            }
        }

        if (! empty($normalized)) {
            $this->merge($normalized);
        }
    }
}
