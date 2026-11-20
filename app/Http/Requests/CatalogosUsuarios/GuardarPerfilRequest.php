<?php

namespace App\Http\Requests\CatalogosUsuarios;

use App\Enums\TipoPerfil;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarPerfilRequest extends FormRequest
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
            'tipo' => [$req, Rule::enum(TipoPerfil::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del perfil es obligatorio.',
            'tipo.required' => 'El tipo de perfil es obligatorio.',
            'tipo.enum' => 'El tipo de perfil no es válido.',
        ];
    }
}
