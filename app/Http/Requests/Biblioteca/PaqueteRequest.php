<?php

namespace App\Http\Requests\Biblioteca;

use Illuminate\Foundation\Http\FormRequest;

class PaqueteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requireRule = $this->isMethod('POST')
            ? 'required'
            : 'sometimes';

        return [

            'nombre' => [
                $requireRule,
                'string',
                'max:150'
            ],

            'id_log' => [
                'nullable',
                'integer'
            ],

            'activo' => [
                'nullable',
                'integer',
                'in:0,1'
            ],

            'estado' => [
                'nullable',
                'string',
                'max:50'
            ],

            'fechareg' => [
                'nullable',
                'date'
            ],
        ];
    }

    public function messages(): array
    {
        return [

            'nombre.required' => 'El nombre del paquete es obligatorio.',

            'codigo.unique' => 'El código del paquete ya existe.',

            'activo.in' => 'El campo activo solo acepta 0 o 1.',
        ];
    }
}
