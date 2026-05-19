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
            // El ID de inscripción sigue siendo un campo único global en la petición
            'id_inscripcion' => $requiredRule . '|integer|exists:admisiones_inscripciones,id',

            // Validamos que 'documentos' sea obligatoriamente un array
            'documentos' => $requiredRule . '|array|min:1|max:15',

            // Validamos el archivo binario dentro de cada posición del array
            'documentos.*.archivo' => $requiredRule . '|file|mimes:pdf,jpg,png,jpeg|max:5120', // Máx 5MB por archivo

            // Validamos el tipo de documento específico para cada archivo del array
            'documentos.*.tipo_documento' => [
                $requiredRule,
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
                ])
            ]
        ];
    }

    /**
     * Mensajes personalizados
     */
    public function messages(): array
    {
        return [
            'id_inscripcion.required' => 'La inscripción es obligatoria.',
            'id_inscripcion.exists'   => 'La inscripción no existe.',

            'documentos.required'     => 'Debe enviar al menos un documento.',
            'documentos.array'        => 'Los documentos deben venir en un formato de lista válido.',
            'documentos.min'          => 'Debe enviar al menos un documento.',
            'documentos.max'          => 'No puedes subir más de 15 documentos a la vez.',

            // Mensajes utilizando el asterisco (*) para identificar dinámicamente la posición del error
            'documentos.*.archivo.required'        => 'El archivo físico es obligatorio.',
            'documentos.*.archivo.file'            => 'El elemento cargado debe ser un archivo válido.',
            'documentos.*.archivo.mimes'           => 'Solo se permiten archivos en formato PDF, JPG, PNG o JPEG.',
            'documentos.*.archivo.max'             => 'El archivo no puede pesar más de 5MB.',

            'documentos.*.tipo_documento.required' => 'El tipo de documento es obligatorio.',
            'documentos.*.tipo_documento.in'       => 'El tipo de documento seleccionado no es válido.',
        ];
    }
}
