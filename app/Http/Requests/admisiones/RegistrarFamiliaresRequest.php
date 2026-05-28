<?php

namespace App\Http\Requests\Admisiones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarFamiliaresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'familiares' => [
                'required',
                'array',
                'min:1'
            ],

            'familiares.*.id_inscripcion' => [
                'required',
                'integer',
                'exists:admisiones_inscripciones,id'
            ],

            'familiares.*.tipo_parentesco' => [
                'required',
                Rule::in(['Padre', 'Madre', 'Acudiente']),
            ],

            'familiares.*.nombre_completo' => [
                'required',
                'string',
                'max:255'
            ],

            'familiares.*.documento_identidad' => [
                'nullable',
                'string',
                'max:20'
            ],

            'familiares.*.lugar_expedicion_doc' => [
                'nullable',
                'string',
                'max:100'
            ],

            'familiares.*.estado_civil' => [
                'nullable',
                'string',
                'max:50'
            ],

            'familiares.*.idiomas' => [
                'nullable',
                'string',
                'max:255'
            ],

            'familiares.*.direccion_residencia' => [
                'nullable',
                'string',
                'max:255'
            ],

            'familiares.*.telefono_fijo' => [
                'nullable',
                'string',
                'max:20'
            ],

            'familiares.*.celular' => [
                'nullable',
                'string',
                'max:20'
            ],

            'familiares.*.email' => [
                'nullable',
                'email',
                'max:150'
            ],

            'familiares.*.profesion' => [
                'nullable',
                'string',
                'max:100'
            ],

            'familiares.*.empresa_labora' => [
                'nullable',
                'string',
                'max:150'
            ],

            'familiares.*.cargo_ocupacion' => [
                'nullable',
                'string',
                'max:100'
            ],

            'familiares.*.telefono_oficina' => [
                'nullable',
                'string',
                'max:20'
            ],

            'familiares.*.fecha_registro' => [
                'nullable',
                'date'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'familiares.required' => 'Debe enviar al menos un familiar.',
            'familiares.array' => 'El formato de familiares es inválido.',

            'familiares.*.id_inscripcion.required' => 'La inscripción es obligatoria.',
            'familiares.*.id_inscripcion.exists' => 'La inscripción seleccionada no existe.',

            'familiares.*.tipo_parentesco.required' => 'El tipo de parentesco es obligatorio.',

            'familiares.*.nombre_completo.required' => 'El nombre completo es obligatorio.',
        ];
    }
}
