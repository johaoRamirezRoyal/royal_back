<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;

class FamilyRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string|size:64',
            'documento' => 'required|string|max:50',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'email' => 'required|email|max:140',
            'telefono' => 'required|string|max:20',
        ];
    }

    public function messages()
    {
        return [
            // ── Token ──
            'token.required' => 'El token es obligatorio.',
            'token.string' => 'El token debe ser una cadena válida.',
            'token.size' => 'El token no es válido.',

            // ── Documento ──
            'documento.required' => 'El número de documento es obligatorio.',
            'documento.string' => 'El documento debe ser una cadena de texto.',
            'documento.max' => 'El documento no debe superar los 50 caracteres.',

            // ── Nombre ──
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.string' => 'El nombre debe ser una cadena de texto.',
            'nombre.max' => 'El nombre no debe superar los 100 caracteres.',

            // ── Apellido ──
            'apellido.required' => 'El apellido es obligatorio.',
            'apellido.string' => 'El apellido debe ser una cadena de texto.',
            'apellido.max' => 'El apellido no debe superar los 100 caracteres.',

            // ── Email ──
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'email.max' => 'El correo no debe superar los 140 caracteres.',

            // ── Teléfono ──
            'telefono.required' => 'El teléfono de contacto es obligatorio.',
            'telefono.string' => 'El teléfono debe ser una cadena de texto.',
            'telefono.max' => 'El teléfono no debe superar los 20 caracteres.',
        ];
    }
}
