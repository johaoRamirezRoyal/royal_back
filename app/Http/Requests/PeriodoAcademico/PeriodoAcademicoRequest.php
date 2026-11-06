<?php

namespace App\Http\Requests\PeriodoAcademico;

use App\Models\AnioEscolar\Anio;
use App\Services\AnioEscolar\AnioEscolarServices;
use Illuminate\Foundation\Http\FormRequest;

class PeriodoAcademicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("post") ? 'required' : 'sometimes';
        return [
            // El año escolar se resuelve automáticamente a partir de fecha_inicio al
            // crear (PeriodoAcademicoServices::agregarPeriodoAcademico), así que nunca
            // es obligatorio; solo se valida si un caller decide enviarlo explícito.
            'id_anio_escolar' => [
                'sometimes',
                'integer',
                'exists:anio_escolar,id'
            ],

            'nombre' => [
                $isRequired,
                'string',
                'max:100'
            ],

            'fecha_inicio' => [
                $isRequired,
                'date'
            ],

            'fecha_fin' => [
                $isRequired,
                'date',
                'after:fecha_inicio'
            ],

            // Hasta cuándo se pueden registrar o corregir notas del periodo (NULL = hasta
            // fecha_fin). No antes del fin: es el plazo para corregir después de cerrarlo.
            'fecha_limite_notas' => [
                'nullable',
                'date',
                'after_or_equal:fecha_fin'
            ],

            'activo' => [
                'nullable',
                'boolean'
            ]
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $anioEscolar = Anio::find($this->id_anio_escolar);

            if (!$anioEscolar) {
                return;
            }

            // Rango válido según el tipo de calendario (A o B) configurado en
            // `configuracion_academica` — ver AnioEscolarServices::rangoDeAnioEscolar.
            $rango = app(AnioEscolarServices::class)->rangoDeAnioEscolar($anioEscolar);
            $fechaMinima = $rango['fecha_min'];
            $fechaMaxima = $rango['fecha_max'];

            if (
                $this->fecha_inicio &&
                $this->fecha_inicio < $fechaMinima
            ) {
                $validator->errors()->add(
                    'fecha_inicio',
                    "La fecha de inicio debe ser igual o posterior a {$fechaMinima}."
                );
            }

            if (
                $this->fecha_fin &&
                $this->fecha_fin > $fechaMaxima
            ) {
                $validator->errors()->add(
                    'fecha_fin',
                    "La fecha de finalización debe ser igual o anterior a {$fechaMaxima}."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'id_anio_escolar.required' => 'El año escolar es obligatorio.',
            'id_anio_escolar.exists' => 'El año escolar seleccionado no existe.',

            'nombre.required' => 'El nombre del periodo es obligatorio.',
            'nombre.string' => 'El nombre del periodo no es válido.',
            'nombre.max' => 'El nombre del periodo no puede superar los 100 caracteres.',

            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio no es válida.',

            'fecha_fin.required' => 'La fecha de finalización es obligatoria.',
            'fecha_fin.date' => 'La fecha de finalización no es válida.',
            'fecha_fin.after' => 'La fecha de finalización debe ser posterior a la fecha de inicio.',

            'fecha_limite_notas.date' => 'La fecha límite de notas no es válida.',
            'fecha_limite_notas.after_or_equal' => 'La fecha límite de notas no puede ser anterior al fin del periodo.',

            'activo.boolean' => 'El campo activo debe ser verdadero o falso.'
        ];
    }
}
