<?php

namespace App\Http\Requests\ProcesoCompra;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;
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
            'estado' => $this->estado ? trim($this->estado) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'id_proveedor' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id_user')->where(fn ($q) => $q->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::PROVEEDOR)))->where('estado', 'activo'),
            ],
            // Solicitud final que se edita (las del legacy no tienen solicitud inicial).
            'id_solicitud' => ['nullable', 'integer', Rule::exists('solicitudes', 'id')],
            'iva' => ['nullable', 'string', 'max:200'],
            'cotizacion_doc' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            // Confirmación / estudio de la solicitud
            'estado' => ['nullable', 'string', 'max:50', Rule::in(['aprobada', 'rechazada', 'aplazada', 'pendiente'])],
            'fecha_solicitado' => ['nullable', 'date'],
            'fecha_aplazado' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1300'],
            'justificacion' => ['nullable', 'string', 'max:1000'],
            // Productos de la solicitud final: sin `id` es una línea nueva agregada en el estudio.
            'productos' => ['nullable', 'array'],
            'productos.*.id' => ['nullable', 'integer', Rule::exists('solicitud_productos', 'id')],
            'productos.*.producto' => ['required', 'string', 'max:1000'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
            'productos.*.precio' => ['nullable', 'string', 'max:200'],
            'productos.*.iva' => ['nullable', 'string', 'max:100'],
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
            'productos.*.id.exists' => 'Uno de los productos no existe',
            'productos.*.producto.required' => 'Todos los productos deben tener descripción',
            'productos.*.producto.max' => 'La descripción del producto no puede superar los 1000 caracteres',
            'productos.*.cantidad.required' => 'Todos los productos deben tener cantidad',
            'productos.*.cantidad.integer' => 'La cantidad de cada producto debe ser un número entero',
            'productos.*.cantidad.min' => 'La cantidad de cada producto debe ser al menos 1',
        ];
    }
}
