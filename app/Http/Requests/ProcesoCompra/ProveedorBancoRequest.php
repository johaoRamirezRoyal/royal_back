<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;

class ProveedorBancoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nom_banco' => $this->nom_banco ? trim($this->nom_banco) : null,
            'num_banco' => $this->num_banco ? trim($this->num_banco) : null,
            'tipo_cuenta' => $this->tipo_cuenta ? trim($this->tipo_cuenta) : null,
            'activo' => $this->has('activo') ? (int) $this->activo : 1,
        ]);
    }

    public function rules(): array
    {
        return [
            'nom_banco' => ['required', 'string', 'max:200'],
            'num_banco' => ['required', 'string', 'max:200'],
            'tipo_cuenta' => ['nullable', 'string', 'max:200'],
            'activo' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom_banco.required' => 'El nombre del banco es obligatorio',
            'nom_banco.max' => 'El nombre del banco no puede superar los 200 caracteres',
            'num_banco.required' => 'El número de cuenta es obligatorio',
            'num_banco.max' => 'El número de cuenta no puede superar los 200 caracteres',
            'activo.in' => 'El estado debe ser 0 o 1',
        ];
    }

    public function toBancoData(): array
    {
        return [
            'nom_banco' => $this->nom_banco,
            'num_banco' => $this->num_banco,
            'tipo_cuenta' => $this->tipo_cuenta,
            'activo' => $this->activo,
        ];
    }
}