<?php

namespace App\Http\Requests\Usuarios;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistrarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'correo' => strtolower(trim($this->correo)),
            'nombre' => trim($this->nombre),
            'apellido' => $this->apellido ? trim($this->apellido) : null,
            'telefono' => $this->telefono ? trim($this->telefono) : null,
        ]);
    }

    public function rules(): array
    {
        $usuarioId = $this->route('id'); // para update

        return [
            "documento" => [
                'required',
                'numeric',
                'digits_between:5,16',
                Rule::unique('usuarios', 'documento')->ignore($usuarioId, 'id'),
            ],

            "nombre" => [
                'required',
                'string',
                'max:100'
            ],

            "apellido" => [
                'nullable',
                'string',
                'max:100',
            ],

            "user" => [
                'required',
                'string',
                'max:200'
            ],

            "correo" => [
                'required',
                'email',
                'ends_with:@royalschool.edu.co',
                Rule::unique('usuarios', 'correo')->ignore($usuarioId, 'id'),
            ],

            "pass" => [
                $usuarioId ? 'nullable' : 'required', // requerido solo en create
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
            'documento.required' => 'El documento es obligatorio',
            'documento.numeric' => 'El documento debe ser numérico',
            'documento.digits_between' => 'El documento debe tener entre 5 y 16 dígitos',
            'documento.unique' => 'El documento ya está registrado',

            'nombre.required' => 'El nombre es obligatorio',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres',

            'apellido.max' => 'El apellido no puede superar los 100 caracteres',

            'user.required' => 'El nombre es obligatorio',
            'user.max' => 'El nombre no puede superar los 100 caracteres',

            'correo.required' => 'El correo es obligatorio',
            'correo.email' => 'El correo no es válido',
            'correo.ends_with' => 'El correo debe ser institucional (@royalschool.edu.co)',
            'correo.unique' => 'El correo ya está registrado',

            'pass.required' => 'La contraseña es obligatoria',
            'pass.min' => 'La contraseña debe tener al menos 6 caracteres',

            'perfil.required' => 'El perfil es obligatorio',
            'perfil.exists' => 'El perfil seleccionado no existe',

            'id_nivel.required' => 'El nivel es obligatorio',
            'id_nivel.exists' => 'El nivel seleccionado no existe',

            'id_curso.exists' => 'El curso seleccionado no existe',
        ];
    }

    public function toUsuarioData(): array
    {
        $data = [
            'documento' => $this->documento,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'user' => $this->user,
            'correo' => $this->correo,
            'perfil' => $this->perfil,
            'id_nivel' => $this->id_nivel,
            'id_curso' => $this->id_curso,
            'telefono' => $this->telefono,
        ];

        // Solo agregar contraseña si viene (útil para update)
        if ($this->filled('pass')) {
            $data['pass'] = bcrypt($this->pass);
        }

        return $data;
    }

        public function toUsuarioFormatCreate(): array
    {
        $data = [];

        if ($this->has('documento')) $data['documento'] = $this->documento;
        if ($this->has('nombre')) $data['nombre'] = trim($this->nombre);
        if ($this->has('apellido')) {
            $data['apellido'] = $this->apellido ? trim($this->apellido) : null;
        }
        if ($this->has('user')) $data['user'] = $this->user;
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
