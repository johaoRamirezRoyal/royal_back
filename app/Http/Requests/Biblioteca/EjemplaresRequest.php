<?php

namespace App\Http\Requests\Biblioteca;

use Illuminate\Foundation\Http\FormRequest;

class EjemplaresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("POST") ? "required" : "sometimes";
        return [
            'id_libro' => $isRequired . '|integer|exists:biblioteca_libros,id',
            'cantidad' => $isRequired . '|integer|min:1|max:100',
            'estado' => 'nullable|integer|in:0,1',
            'id_log' => $isRequired . '|integer'
        ];
    }

    public function messages(): array
    {
        return [
            'id_libro.required' => 'El libro es obligatorio.',
            'id_libro.exists' => 'El libro seleccionado no existe.',

            'cantidad.required' => 'La cantidad es obligatoria.',
            'cantidad.integer' => 'La cantidad debe ser numérica.',
            'cantidad.min' => 'Debe crear al menos un ejemplar.',
            'cantidad.max' => 'La cantidad excede el límite permitido.',

            'estado.in' => 'Estado inválido.',

            'id_log.required' => 'El usuario de registro es obligatorio.'
        ];
    }
}
