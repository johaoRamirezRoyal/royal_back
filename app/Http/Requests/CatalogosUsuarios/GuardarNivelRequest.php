<?php

namespace App\Http\Requests\CatalogosUsuarios;

use Illuminate\Foundation\Http\FormRequest;

class GuardarNivelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'nombre' => "$req|string|max:190",
            'id_nivel_academico' => 'nullable|integer|exists:nivel_academico,id',
            'grupo_correo_distribucion' => 'nullable|string|max:60',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del nivel es obligatorio.',
            'id_nivel_academico.exists' => 'El nivel académico elegido no existe.',
        ];
    }
}
