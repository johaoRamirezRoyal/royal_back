<?php

namespace App\Http\Requests\Admisiones;

use App\Enums\ViveCon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;


class RegistrarAspiranteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Información básica
            'nombre_completo' => ['required', 'string', 'max:255'],
            'lugar_nacimiento' => ['nullable', 'string', 'max:150'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'edad' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sexo' => ['nullable', 'string', 'max:20'],
            'lengua_materna' => ['nullable', 'string', 'max:100'],
            'otros_idiomas' => ['nullable', 'string'],
            'religion' => ['nullable', 'string', 'max:100'],

            // Entorno familiar
            'vive_con' => ['nullable', new Enum(ViveCon::class)],
            'num_hermanos' => ['nullable', 'integer', 'min:0'],
            'posicion_entre_hermanos' => ['nullable', 'integer', 'min:1'],
            'tiene_hermanos_colegio' => ['nullable', 'boolean'],
            'info_hermanos_colegio' => [
                'nullable', 
                'string', 
                'required_if:tiene_hermanos_colegio,1'
                ],

            // Historial y aplicación
            'antecedentes_escolares' => ['nullable', 'string'],
            'grado_aplica' => ['required', 'string', 'max:50'],
            'anio_academico' => ['required', 'string', 'max:20'],

            // Metadatos
            'fecha_registro' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            // Requeridos
            'nombre_completo.required' => 'El nombre completo es obligatorio.',
            'grado_aplica.required' => 'El grado al que aplica es obligatorio.',
            'anio_academico.required' => 'El año académico es obligatorio.',
            'info_hermanos_colegio.required_if' => 'La información de hermanos en el colegio es obligatoria cuando hay hermanos en el colegio.',

            // Tipos
            'edad.integer' => 'La edad debe ser un número entero.',
            'num_hermanos.integer' => 'El número de hermanos debe ser un número entero.',
            'posicion_entre_hermanos.integer' => 'La posición entre hermanos debe ser un número entero.',
            'tiene_hermanos_colegio.boolean' => 'El campo de hermanos en el colegio debe ser verdadero o falso.',

            // Rangos
            'edad.min' => 'La edad no puede ser negativa.',
            'edad.max' => 'La edad no puede ser mayor a 100.',
            'num_hermanos.min' => 'El número de hermanos no puede ser negativo.',
            'posicion_entre_hermanos.min' => 'La posición entre hermanos debe ser al menos 1.',

            // Strings
            'nombre_completo.max' => 'El nombre completo no puede superar los 255 caracteres.',
            'lugar_nacimiento.max' => 'El lugar de nacimiento no puede superar los 150 caracteres.',
            'sexo.max' => 'El campo sexo no puede superar los 20 caracteres.',
            'lengua_materna.max' => 'La lengua materna no puede superar los 100 caracteres.',
            'religion.max' => 'La religión no puede superar los 100 caracteres.',
            'grado_aplica.max' => 'El grado no puede superar los 50 caracteres.',
            'anio_academico.max' => 'El año académico no puede superar los 20 caracteres.',

            // Fechas
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
            'fecha_registro.date' => 'La fecha de registro debe ser una fecha válida.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre_completo' => 'nombre completo',
            'lugar_nacimiento' => 'lugar de nacimiento',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'edad' => 'edad',
            'sexo' => 'sexo',
            'lengua_materna' => 'lengua materna',
            'otros_idiomas' => 'otros idiomas',
            'religion' => 'religión',
            'vive_con' => 'convivencia familiar',
            'num_hermanos' => 'número de hermanos',
            'posicion_entre_hermanos' => 'posición entre hermanos',
            'tiene_hermanos_colegio' => 'hermanos en el colegio',
            'info_hermanos_colegio' => 'información de hermanos en el colegio',
            'antecedentes_escolares' => 'antecedentes escolares',
            'grado_aplica' => 'grado al que aplica',
            'anio_academico' => 'año académico',
            'fecha_registro' => 'fecha de registro',
        ];
    }
}
