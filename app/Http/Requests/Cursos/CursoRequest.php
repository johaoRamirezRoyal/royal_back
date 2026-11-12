<?php

namespace App\Http\Requests\Cursos;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;
use App\Support\Permiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nombre' => ['required', 'string', 'max:200', Rule::unique('curso', 'nombre')->ignore($id)],
            'id_nivel' => ['required', 'integer', 'exists:nivel_academico,id'],
            // El curso al que pasan sus estudiantes al cerrar el año; no puede ser él mismo.
            'curso_proximo' => ['nullable', 'integer', 'exists:curso,id', Rule::notIn(array_filter([$id]))],
            // Director de grupo: un docente activo. Si ya dirige otro curso, se reasigna a este
            // (CursosServices::reasignarDirector deja el otro sin director).
            'id_director' => [
                'nullable',
                'integer',
                Rule::exists('usuarios', 'id_user')->where(fn ($q) => $q->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::DOCENTE)))->where('estado', 'activo'),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del curso es obligatorio',
            'nombre.unique' => 'Ya existe un curso con ese nombre',
            'id_nivel.required' => 'Selecciona el nivel académico',
            'id_nivel.exists' => 'El nivel académico no existe',
            'curso_proximo.exists' => 'El curso próximo no existe',
            'curso_proximo.not_in' => 'El curso próximo no puede ser el mismo curso',
            'id_director.exists' => 'El director de grupo debe ser un docente activo',
        ];
    }
}
