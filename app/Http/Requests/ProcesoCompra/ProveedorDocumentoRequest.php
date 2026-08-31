<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;

class ProveedorDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // POST /proveedores/{id}/documentos exige archivo; PUT /proveedores/documentos/{docId} lo admite opcional.
    private function esCreacion(): bool
    {
        return $this->route('docId') === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activo' => $this->has('activo') ? (int) $this->activo : 1,
        ]);
    }

    public function rules(): array
    {
        return [
            'archivo' => [$this->esCreacion() ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'tipo_documento' => ['required', 'integer', 'exists:tipo_documento_proveedor,id'],
            'activo' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Debe adjuntar el archivo del documento',
            'archivo.mimes' => 'El archivo debe ser jpg, jpeg, png, webp o pdf',
            'archivo.max' => 'El archivo no puede superar los 10MB',
            'tipo_documento.required' => 'Debe indicar el tipo de documento',
            'tipo_documento.exists' => 'El tipo de documento no existe',
            'activo.in' => 'El estado debe ser 0 o 1',
        ];
    }
}
