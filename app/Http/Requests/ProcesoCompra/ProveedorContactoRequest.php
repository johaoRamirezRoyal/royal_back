<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;

class ProveedorContactoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre_contacto' => $this->nombre_contacto ? trim($this->nombre_contacto) : null,
            'telefono_contacto' => $this->telefono_contacto ? trim($this->telefono_contacto) : null,
            'correo_contacto' => $this->correo_contacto ? strtolower(trim($this->correo_contacto)) : null,
            'cargo_contacto' => $this->cargo_contacto ? trim($this->cargo_contacto) : null,
            'activo' => $this->has('activo') ? (int) $this->activo : 1,
        ]);
    }

    public function rules(): array
    {
        return [
            'nombre_contacto' => ['required', 'string', 'max:200'],
            'telefono_contacto' => ['nullable', 'string', 'max:200'],
            'correo_contacto' => ['nullable', 'email', 'max:200'],
            'cargo_contacto' => ['nullable', 'string', 'max:200'],
            'activo' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre_contacto.required' => 'El nombre del contacto es obligatorio',
            'nombre_contacto.max' => 'El nombre del contacto no puede superar los 200 caracteres',
            'correo_contacto.email' => 'El correo del contacto no es válido',
            'activo.in' => 'El estado debe ser 0 o 1',
        ];
    }

    public function toContactoData(): array
    {
        return [
            'nombre_contacto' => $this->nombre_contacto,
            'telefono_contacto' => $this->telefono_contacto,
            'correo_contacto' => $this->correo_contacto,
            'cargo_contacto' => $this->cargo_contacto,
            'activo' => $this->activo,
        ];
    }
}