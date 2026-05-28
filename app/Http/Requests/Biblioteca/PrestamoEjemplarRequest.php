<?php

namespace App\Http\Requests\Biblioteca;

use Illuminate\Foundation\Http\FormRequest;

class PrestamoEjemplarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRequired = $this->isMethod("POST") ? "required" : "sometimes";
        return [
            'codigo_ejemplar' => $isRequired . '|string|exists:biblioteca_ejemplares,codigo',

            'id_usuario' => $isRequired . '|integer|exists:usuarios,id_user',

            //'fecha_prestamo' => $isRequired . '|date',

            'fecha_devolucion' => $isRequired . '|date|after_or_equal:fecha_prestamo',

            'observacion' => 'nullable|string|max:500',

            'id_devuelto' => 'nullable|integer|exists:biblioteca_prestamos,id',

            'fecha_devuelto' => 'nullable|date|after_or_equal:fecha_prestamo',

            'id_log' => $isRequired . '|integer'
        ];
    }

    public function messages(): array
    {
        return [

            'id_ejemplar.required' =>
            'El ejemplar es obligatorio.',

            'id_ejemplar.exists' =>
            'El ejemplar no existe.',

            'id_usuario.required' =>
            'El usuario es obligatorio.',

            'id_usuario.exists' =>
            'El usuario no existe.',

            'fecha_prestamo.required' =>
            'La fecha de préstamo es obligatoria.',

            'fecha_devolucion.after_or_equal' =>
            'La devolución no puede ser anterior al préstamo.',

            'fecha_devuelto.after_or_equal' =>
            'La fecha de devolución real no puede ser anterior al préstamo.',

            'id_log.required' =>
            'El usuario de registro es obligatorio.'
        ];
    }
}
