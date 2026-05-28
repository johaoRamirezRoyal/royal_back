<?php
namespace App\Http\Requests\Admisiones;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarFamiliarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        $isRequired = $this->isMethod('put') || $this->isMethod('patch');
        
        return [

            'id_inscripcion' => [
                $isRequired ? 'sometimes' : 'required',
                'integer',
                'exists:admisiones_inscripciones,id'
            ],

            'tipo_parentesco' => [
                $isRequired ? 'sometimes' : 'required',
                Rule::in(['Padre', 'Madre', 'Acudiente']),
            ],
                
            'nombre_completo' => [
                $isRequired ? 'sometimes' : 'required',
                'string',
                'max:255'
            ],

            'documento_identidad' => [
                'nullable',
                'string',
                'max:20'
            ],

            'lugar_expedicion_doc' => [
                'nullable',
                'string',
                'max:100'
            ],

            'estado_civil' => [
                'nullable',
                'string',
                'max:50'
            ],

            'idiomas' => [
                'nullable',
                'string',
                'max:255'
            ],

            'direccion_residencia' => [
                'nullable',
                'string',
                'max:255'
            ],

            'telefono_fijo' => [
                'nullable',
                'string',
                'max:20'
            ],

            'celular' => [
                'nullable',
                'string',
                'max:150'
            ],

            'email' => [
                'nullable',
                'email',
                'max:150'
            ],

            'profesion' => [
                'nullable',
                'string',
                'max:100'
            ],

            'empresa_labora' => [
                'nullable',
                'string',
                'max:150'
            ],

            'cargo_ocupacion' => [
                'nullable',
                'string',
                'max:100'
            ],

            'telefono_oficina' => [
                'nullable',
                'string',
                'max:20'
            ],

            'fecha_registro' => [
                'nullable',
                'date'
            ],
        ];
    }

    public function messages(): array
    {
        return [

            // Requeridos
            'id_inscripcion.required' => 'No puedes continuar si no cuentas con un registro de inscripción.',
            'tipo_parentesco.required' => 'El tipo de familiar es obligatorio.',
            'nombre_completo.required' => 'El nombre completo es obligatorio.',

            // Exists
            'id_inscripcion.exists' => 'La inscripción seleccionada no existe.',
            // Tipos
            'id_inscripcion.integer' => 'El ID de la inscripción debe ser un número entero.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'fecha_registro.date' => 'La fecha de registro debe ser válida.',

            // Longitudes
            'nombre_completo.max' => 'El nombre completo no puede superar los 255 caracteres.',
            'documento_identidad.max' => 'El documento de identidad no puede superar los 20 caracteres.',
            'lugar_expedicion_doc.max' => 'El lugar de expedición no puede superar los 100 caracteres.',
            'estado_civil.max' => 'El estado civil no puede superar los 50 caracteres.',
            'idiomas.max' => 'Los idiomas no pueden superar los 255 caracteres.',
            'direccion_residencia.max' => 'La dirección de residencia no puede superar los 255 caracteres.',
            'telefono_fijo.max' => 'El teléfono fijo no puede superar los 20 caracteres.',
            'celular.max' => 'El celular no puede superar los 20 caracteres.',
            'email.max' => 'El correo electrónico no puede superar los 150 caracteres.',
            'profesion.max' => 'La profesión no puede superar los 100 caracteres.',
            'empresa_labora.max' => 'La empresa no puede superar los 150 caracteres.',
            'cargo_ocupacion.max' => 'El cargo u ocupación no puede superar los 100 caracteres.',
            'telefono_oficina.max' => 'El teléfono de oficina no puede superar los 20 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'aspirante_id' => 'aspirante',
            'tipo_parentesco' => 'tipo de familiar',
            'nombre_completo' => 'nombre completo',
            'documento_identidad' => 'documento de identidad',
            'lugar_expedicion_doc' => 'lugar de expedición del documento',
            'estado_civil' => 'estado civil',
            'idiomas' => 'idiomas',
            'direccion_residencia' => 'dirección de residencia',
            'telefono_fijo' => 'teléfono fijo',
            'celular' => 'celular',
            'email' => 'correo electrónico',
            'profesion' => 'profesión',
            'empresa_labora' => 'empresa donde labora',
            'cargo_ocupacion' => 'cargo u ocupación',
            'telefono_oficina' => 'teléfono de oficina',
            'fecha_registro' => 'fecha de registro',
        ];
    }
}