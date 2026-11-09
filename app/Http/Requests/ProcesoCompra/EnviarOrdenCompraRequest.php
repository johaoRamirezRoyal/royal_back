<?php

namespace App\Http\Requests\ProcesoCompra;

use Illuminate\Foundation\Http\FormRequest;

class EnviarOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orden_pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'orden_pdf.required' => 'Debe adjuntar la orden de compra',
            'orden_pdf.mimes' => 'La orden de compra debe ser un PDF',
            'orden_pdf.max' => 'La orden de compra no puede superar los 10MB',
        ];
    }
}
