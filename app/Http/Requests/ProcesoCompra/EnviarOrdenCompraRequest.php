<?php

namespace App\Http\Requests\ProcesoCompra;

/** Correo redactado (ver VistaPreviaOrdenCompraRequest) más el PDF opcional de la orden. */
class EnviarOrdenCompraRequest extends VistaPreviaOrdenCompraRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'adjuntar_pdf' => ['required', 'boolean'],
            'orden_pdf' => ['required_if_accepted:adjuntar_pdf', 'nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'orden_pdf.required_if_accepted' => 'Debe adjuntar la orden de compra',
            'orden_pdf.mimes' => 'La orden de compra debe ser un PDF',
            'orden_pdf.max' => 'La orden de compra no puede superar los 10MB',
        ];
    }
}
