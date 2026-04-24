<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarUsuarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        $usuarioId = $this->route('id');

        return [
            "documento" => [
                'required',
                'numeric',
                'digits_between:5,16'
            ],

            "nombre" => [
                'required',
                'string',
                'max:100'
            ],

            "apellido" => [
                'required',
                'string',
                'max:100',
            ],

            "correo" => [
                'required',
                'email',
                'ends_with:@royalschool.edu.co',
                'unique:usuarios,correo',
            ],

            "pass" => [
                'required',
                'string',
                'min:6'
            ],

            'perfil' => [
                'required',
                'integer',
                Rule::exists('perfiles', 'id_perfil'),
            ],

            'id_nivel' => [
                'required',
                'integer',
                Rule::exists('nivel', 'id'),
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:20'
            ],

            'id_curso' => [
                'nullable',
                'integer',
                Rule::exists('cursos', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'correo.ends_with' => 'El correo debe ser institucional (@royalschool.edu.co)',
            'documento.unique' => 'El documento ya está registrado a un usuario',
            'correo.unique' => 'El correo ya está registrado a un usuario',
        ];
    }

    public function toUsuarioFormatCreate(): array
    {
        return [
            'documento' => $this->documento,
            'nombre' => trim($this->nombre),
            'apellido' => $this->apellido ? trim($this->apellido) : null,
            'correo' => $this->correo,
            'pass' => $this->pass,
            'perfil' => $this->perfil,
            'id_nivel' => $this->id_nivel,
            'id_curso' => $this->id_curso,
            'telefono' => $this->telefono,
            'fechareg' => now(),
        ];
    }
}
