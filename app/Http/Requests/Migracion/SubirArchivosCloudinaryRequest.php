<?php

namespace App\Http\Requests\Migracion;

use Illuminate\Foundation\Http\FormRequest;

class SubirArchivosCloudinaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'directorio' => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9_\-\/]*$/', 'not_regex:/\.\./'],
            // 20 = max_file_uploads por defecto de PHP
            'archivos' => ['required', 'array', 'min:1', 'max:20'],
            'archivos.*' => ['file', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'directorio.regex' => 'El directorio solo admite letras, números, guiones y "/".',
            'directorio.not_regex' => 'El directorio no puede contener "..".',
            'archivos.required' => 'Debes enviar al menos un archivo.',
            'archivos.max' => 'Máximo 20 archivos por petición.',
            'archivos.*.file' => 'Cada elemento de archivos debe ser un archivo.',
            'archivos.*.max' => 'Cada archivo puede pesar máximo 10 MB.',
        ];
    }
}
