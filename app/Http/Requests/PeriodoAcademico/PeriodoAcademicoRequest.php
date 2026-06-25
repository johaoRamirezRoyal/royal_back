<?php

namespace App\Http\Requests\PeriodoAcademico;

use App\Models\AnioEscolar\Anio;
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
            'id_anio_escolar' => [
                $isRequired,
                'integer',
                'exists:anio_escolar,id'
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

            $fechaMinima = "{$anioEscolar->anio_inicio}-01-01";
            $fechaMaxima = "{$anioEscolar->anio_fin}-12-31";

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

            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio no es válida.',

            'fecha_fin.required' => 'La fecha de finalización es obligatoria.',
            'fecha_fin.date' => 'La fecha de finalización no es válida.',
            'fecha_fin.after' => 'La fecha de finalización debe ser posterior a la fecha de inicio.',

            'activo.boolean' => 'El campo activo debe ser verdadero o falso.'
        ];
    }
}
