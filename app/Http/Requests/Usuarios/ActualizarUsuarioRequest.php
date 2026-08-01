<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarUsuarioRequest extends FormRequest
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
        return [
            "documento" => [
                'numeric',
                'digits_between:5,16'
            ],

            "nombre" => [
                'string',
                'max:100'
            ],

            "apellido" => [
                'nullable',
                'string',
                'max:100',
            ],

            "correo" => [
                'email',
                'ends_with:@royalschool.edu.co',
                'unique:usuarios,correo',
            ],

            "pass" => [
                'string',
                'min:6'
            ],

            'perfil' => [
                'integer',
                Rule::exists('perfiles', 'id_perfil'),
            ],

            'id_nivel' => [
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
        ];
    }

    public function toUsuarioFormatCreate(): array
    {
        return $this->toUsuarioData();
    }

    public function toUsuarioData(): array
    {
        $data = $this->only([
            'documento',
            'nombre',
            'apellido',
            'correo',
            'perfil',
            'id_nivel',
            'id_curso',
            'telefono',
        ]);

        // Solo agregar contraseña si viene (útil para update)
        if ($this->filled('pass')) {
            $data['pass'] = bcrypt($this->pass);
        }

        return $data;
    }
}
