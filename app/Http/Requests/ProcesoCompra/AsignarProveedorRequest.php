<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AsignarProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iva' => $this->iva ? trim($this->iva) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'id_proveedor' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id_user')->where('perfil', 17)->where('estado', 'activo'),
            ],
            'iva' => ['nullable', 'string', 'max:200'],
            'cotizacion_doc' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_proveedor.required' => 'Debe seleccionar el proveedor',
            'id_proveedor.exists' => 'El proveedor seleccionado no existe o no está habilitado',
            'cotizacion_doc.required' => 'Debe adjuntar la cotización',
            'cotizacion_doc.mimes' => 'La cotización debe ser PDF, JPG, JPEG, PNG o WEBP',
            'cotizacion_doc.max' => 'La cotización no puede superar los 10MB',
            'iva.max' => 'El IVA no puede superar los 200 caracteres',
        ];
    }
}