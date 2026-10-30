<?php

namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;

class ProgramacionMantFiltroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_anio' => ['nullable', 'integer'],
            'id_periodo' => ['nullable', 'integer'],
            'mes' => ['nullable', 'string', 'max:20'],
            'estado' => ['nullable', 'integer', 'between:0,4'],
            'tipo' => ['nullable', 'integer', 'in:1,2'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            's' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Filtros en el shape de ProgramacionMantServices::consulta(). */
    public function filtros(): array
    {
        $f = $this->validated();
        $f['search'] = isset($f['s']) ? trim($f['s']) : null;

        return $f;
    }
}
