<?php

namespace App\Http\Requests\DocumentosVarios;

use Illuminate\Foundation\Http\FormRequest;

class DocumentoVarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("POST") ? "required" : "sometimes";
        $isUpdated = $this->isMethod("PUT") ? "required" : "sometimes";

        return [
            'id' => [$isUpdated, 'integer'],
            'tipo_doc' => [$isRequired, 'string', 'max:200'],
            'id_user' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_doc.required' => 'El tipo de documento es obligatorio.',
            'tipo_doc.max' => 'El tipo de documento no debe exceder 200 caracteres.',
        ];
    }
}
