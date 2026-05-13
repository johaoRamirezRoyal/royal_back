<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferenciaFamiliarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requireRule = ($this->isMethod('POST')) ? 'required' : 'sometimes';
        return [

            'id_inscripcion' => [
                $requireRule,
                'integer',
                'exists:inscripciones,id'
            ],

            'nombre' => [
                $requireRule,
                'string',
                'max:150'
            ],

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
    }

    public function messages(): array
    {
        return [
            'id_inscripcion.exists' => 'La inscripción no existe.',
            'nombre.required' => 'El nombre es obligatorio.',
        ];
    }
}
