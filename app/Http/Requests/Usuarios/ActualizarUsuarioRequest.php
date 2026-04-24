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
        $data = [];

        if ($this->has('documento')) $data['documento'] = $this->documento;
        if ($this->has('nombre')) $data['nombre'] = trim($this->nombre);
        if ($this->has('apellido')) {
            $data['apellido'] = $this->apellido ? trim($this->apellido) : null;
        }
        if ($this->has('correo')) $data['correo'] = $this->correo;
        if ($this->filled('pass')) $data['pass'] = $this->pass;
        if ($this->has('perfil')) $data['perfil'] = $this->perfil;
        if ($this->has('id_nivel')) $data['id_nivel'] = $this->id_nivel;
        if ($this->has('id_curso')) $data['id_curso'] = $this->id_curso;
        if ($this->has('telefono')) $data['telefono'] = $this->telefono;

        $data['fecha_editado'] = now();

        return $data;
    }
}
