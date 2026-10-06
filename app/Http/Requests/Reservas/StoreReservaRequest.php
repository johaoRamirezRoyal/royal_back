<?php

namespace App\Http\Requests\Reservas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_salon' => [
                'required',
                'integer',
                Rule::exists('salones', 'id')->where('activo', 1),
            ],
            'fecha_reserva' => ['required'],
            'hora_reserva' => ['required'],
            'id_user' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id_user')->where('estado', 'activo'),
            ],
            'portatil' => ['nullable', 'integer', 'min:0'],
            'sonido' => ['nullable', 'boolean'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'detalle_reserva' => ['nullable', 'string', 'max:1000'],
            'reserva_grupo' => ['nullable', 'boolean'],
            // "Mostrar encuesta": JPG (data URL base64) armado en el frontend con la info de
            // la reserva + QR de la encuesta del salón, que se envía por correo al usuario.
            // ~5 MB de base64 ≈ 3.7 MB de imagen, de sobra para 1080px de ancho.
            'mostrar_encuesta' => ['nullable', 'boolean'],
            'imagen_encuesta' => ['nullable', 'required_if_accepted:mostrar_encuesta', 'string', 'max:5000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'id_salon.required' => 'El salón es obligatorio.',
            'id_salon.integer' => 'El salón debe ser un número entero.',
            'id_salon.exists' => 'El salón no existe o no está activo.',

            'fecha_reserva.required' => 'La fecha de la reserva es obligatoria.',

            'hora_reserva.required' => 'La hora de la reserva es obligatoria.',

            'id_user.required' => 'El usuario es obligatorio.',
            'id_user.integer' => 'El usuario debe ser un número entero.',
            'id_user.exists' => 'El usuario no existe o no está activo.',

            'portatil.integer' => 'Los portátiles deben ser un número entero.',
            'portatil.min' => 'La cantidad de portátiles no puede ser negativa.',

            'sonido.boolean' => 'El campo sonido debe ser verdadero o falso.',

            'titulo.max' => 'El título no debe superar los 255 caracteres.',
            'detalle_reserva.max' => 'El detalle no debe superar los 1000 caracteres.',
            'reserva_grupo.boolean' => 'El campo reserva_grupo debe ser verdadero o falso.',
            'mostrar_encuesta.boolean' => 'El campo mostrar_encuesta debe ser verdadero o falso.',
            'imagen_encuesta.required_if_accepted' => 'Falta la imagen con el QR de la encuesta.',
            'imagen_encuesta.max' => 'La imagen con el QR de la encuesta es demasiado grande.',
        ];
    }
}
