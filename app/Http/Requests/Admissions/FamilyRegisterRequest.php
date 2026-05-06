<?php

namespace App\Http\Requests\Admissions;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Override;

class FamilyRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => 'required|string|size:64',
            'documento' => ['required', 'string', 'max:50', Rule::unique('usuarios', 'documento')],
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'correo' => ['required', 'email', 'max:140', Rule::unique('usuarios', 'correo')],
            'telefono' => ['required', 'string', 'max:20', Rule::unique('usuarios', 'telefono')],
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
            'documento.unique' => 'Ya existe una cuenta registrada con este documento.',
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
            'correo.required' => 'El correo electrónico es obligatorio.',
            'correo.email' => 'El formato del correo electrónico no es válido.',
            'correo.unique' => 'Ya existe una cuenta registrada con este correo.',
            'correo.max' => 'El correo no debe superar los 140 caracteres.',
            
            // ── Teléfono ──
            'telefono.required' => 'El teléfono de contacto es obligatorio.',
            'telefono.string' => 'El teléfono debe ser una cadena de texto.',
            'telefono.unique' => 'Ya existe una cuenta registrada con este telefono.',
            'telefono.max' => 'El teléfono no debe superar los 20 caracteres.',
        ];
    }

    #[Override]
    protected function failedValidation(Validator $validator): never
    {
        $errors = $validator->errors()->all();
        $count = count($errors);

        throw new HttpResponseException(response()->json([
            'message' => $errors[0].($count > 1 ? " (y {$count} errores más)" : ''),
            'errors' => $validator->errors(),
        ], 422));
    }
}
