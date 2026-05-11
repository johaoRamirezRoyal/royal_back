<?php

namespace App\Http\Requests\Admisiones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdmisionesDocumentoRequest extends FormRequest
{
    /**
     * Autorizar request
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

            'id_inscripcion' => $requiredRule .
                '|integer|exists:admisiones_inscripciones,id',

            'tipo_documento' => [
                $requiredRule ,
                'string',
                'max:255',
                Rule::in([
                    'solicitud',
                    'registro_civil',
                    'tarjeta_identidad',
                    'constancia_estudio',
                    'certificados_notas',
                    'foto_aspirante',
                    'foto_padres',
                    'carta_recomendacion',
                    'recomendacion_psicologo',
                    'carta_laboral',
                    'declaracion_renta',
                    'cedula_padres',
                ])]
        ];
    }

    /**
     * Mensajes personalizados
     */
    public function messages(): array
    {
        return [
            'id_inscripcion.required' =>
            'La inscripción es obligatoria.',

            'id_inscripcion.exists' =>
            'La inscripción no existe.',
        ];
    }
}
