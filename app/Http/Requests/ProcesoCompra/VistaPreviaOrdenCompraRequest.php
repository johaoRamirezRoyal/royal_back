<?php

namespace App\Http\Requests\ProcesoCompra;

use App\Services\ProcesoCompra\SolicitudesServices;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Correo redactado para el proveedor. Lo usa la vista previa tal cual y el envío lo
 * extiende con el PDF (EnviarOrdenCompraRequest). Que los destinatarios sean del
 * proveedor lo valida el service contra la BD.
 */
class VistaPreviaOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asunto' => ['required', 'string', 'max:200'],
            'mensaje' => ['required', 'string', 'max:20000'],
            'destinatarios' => ['required', 'array', 'min:1'],
            'destinatarios.*' => ['required', 'email'],
            'cc' => ['nullable', 'array', 'max:5'],
            'cc.*' => ['required', 'email:rfc'],
            'incluir' => ['nullable', 'array'],
            'incluir.*' => [Rule::in(SolicitudesServices::BLOQUES_ORDEN)],
        ];
    }

    public function messages(): array
    {
        return [
            'asunto.required' => 'El asunto es obligatorio',
            'asunto.max' => 'El asunto no puede superar los 200 caracteres',
            'mensaje.required' => 'El mensaje es obligatorio',
            'mensaje.max' => 'El mensaje es demasiado largo',
            'destinatarios.required' => 'Seleccione al menos un correo del proveedor',
            'destinatarios.min' => 'Seleccione al menos un correo del proveedor',
            'destinatarios.*.email' => 'Hay un destinatario con un correo inválido',
            'cc.max' => 'Máximo 5 correos en copia',
            'cc.*.email' => 'Hay un correo en copia inválido',
            'incluir.*.in' => 'Hay un bloque de datos no válido',
        ];
    }
}
