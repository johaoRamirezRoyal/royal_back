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
     * Solo normaliza si el campo viene en el request: esta actualización es parcial
     * (el frontend solo manda los campos que realmente cambió), así que no se puede
     * inyectar la llave si no vino — eso la haría "cambiada" para toUsuarioData().
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('id_nivel_2') && ! $this->id_nivel_2) {
            $this->merge(['id_nivel_2' => null]);
        }
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

            'id_nivel_2' => [
                'nullable',
                'integer',
                'different:id_nivel',
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
                Rule::exists('curso', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'correo.ends_with' => 'El correo debe ser institucional (@royalschool.edu.co)',
            'documento.unique' => 'El documento ya está registrado a un usuario',
            'id_nivel_2.different' => 'El segundo nivel debe ser distinto al nivel principal',
            'id_nivel_2.exists' => 'El segundo nivel seleccionado no existe',
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
            'id_nivel_2',
            'id_curso',
            'telefono',
        ]);

        // Solo agregar contraseña si viene (útil para update). No se hashea acá: el
        // mutator Usuario::setPassAttribute() ya lo hace al hacer update($data), y
        // hashear también acá duplicaba el bcrypt (bcrypt(bcrypt(pass))), dejando al
        // usuario sin poder loguearse con la contraseña que se le asignó.
        if ($this->filled('pass')) {
            $data['pass'] = $this->pass;
        }

        return $data;
    }
}
