<?php

namespace App\Http\Requests\Institucion;

use Illuminate\Foundation\Http\FormRequest;

class CartaRecomendacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idioma' => 'required|string|in:es,en',
            // El formulario tiene muchas preguntas anidadas (estudiante/padres) — se valida
            // la forma general acá y el nombre del estudiante como mínimo indispensable, no
            // cada pregunta individual (el formato oficial no exige responder todas).
            'datos' => 'required|array',
            'datos.nombre_estudiante' => 'required|string|max:150',
        ];
    }

    public function messages(): array
    {
        return [
            'idioma.required' => 'El idioma es obligatorio.',
            'idioma.in' => 'El idioma debe ser "es" o "en".',
            'datos.required' => 'Los datos de la carta son obligatorios.',
            'datos.nombre_estudiante.required' => 'El nombre del estudiante es obligatorio.',
        ];
    }
}
