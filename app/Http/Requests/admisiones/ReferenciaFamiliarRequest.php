<?php

namespace App\Http\Requests\Admisiones;

use Illuminate\Foundation\Http\FormRequest;

class ReferenciaFamiliarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isPost = $this->isMethod('POST');
        $requireRule = $isPost ? 'required' : 'sometimes';

        $baseRules = [
            'id_inscripcion' => [
                $requireRule,
                'integer',
                'exists:admisiones_inscripciones,id'
            ],

            'nombre' => array_filter([
                $isPost ? 'required_without:referencias' : 'sometimes',
                $isPost ? 'prohibits:referencias' : null,
                'string',
                'max:150'
            ]),

            'parentesco' => [
                'nullable',
                'string',
                'max:100'
            ],

            'direccion_residencia' => [
                'nullable',
                'string',
                'max:255'
            ],

            'telefono_residencia' => [
                'nullable',
                'string',
                'max:30'
            ],

            'recomendacion_colegio' => [
                'nullable',
                'string',
                'max:1000'
            ],

            'motivo_ingreso' => [
                'nullable',
                'string',
                'max:1500'
            ],
        ];

        $batchRules = $isPost ? [
            'referencias' => [
                'required_without:nombre',
                'prohibits:nombre',
                'array',
                'min:1'
            ],
            
            'referencias.*.nombre' => [
                'required',
                'string',
                'max:150'
            ],

            'referencias.*.parentesco' => [
                'nullable',
                'string',
                'max:100'
            ],

            'referencias.*.direccion_residencia' => [
                'nullable',
                'string',
                'max:255'
            ],

            'referencias.*.telefono_residencia' => [
                'nullable',
                'string',
                'max:30'
            ],

            'referencias.*.recomendacion_colegio' => [
                'nullable',
                'string',
                'max:1000'
            ],

            'referencias.*.motivo_ingreso' => [
                'nullable',
                'string',
                'max:1500'
            ],
        ] : [];

        return array_merge($baseRules, $batchRules);
    }

    public function messages(): array
    {
        return [
            'id_inscripcion.required' => 'La inscripción es obligatoria.',
            'id_inscripcion.exists' => 'La inscripción no existe.',

            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.required_without' => 'El nombre es obligatorio si no envía un arreglo de referencias.',
            'nombre.prohibits' => 'No puede enviar "nombre" y "referencias" al mismo tiempo.',

            'referencias.required_without' => 'Debe enviar al menos una referencia.',
            'referencias.prohibits' => 'No puede enviar "referencias" junto con "nombre" en la raíz.',
            'referencias.array' => 'El formato de referencias es inválido.',
            'referencias.min' => 'Debe enviar al menos una referencia.',

            'referencias.*.nombre.required' => 'El nombre de la referencia es obligatorio.',
            'referencias.*.nombre.max' => 'El nombre de la referencia no puede superar 150 caracteres.',

            'referencias.*.parentesco.max' => 'El parentesco no puede superar 100 caracteres.',
            'referencias.*.direccion_residencia.max' => 'La dirección no puede superar 255 caracteres.',
            'referencias.*.telefono_residencia.max' => 'El teléfono no puede superar 30 caracteres.',
            'referencias.*.recomendacion_colegio.max' => 'La recomendación no puede superar 1000 caracteres.',
            'referencias.*.motivo_ingreso.max' => 'El motivo de ingreso no puede superar 1500 caracteres.',
        ];
    }
}
