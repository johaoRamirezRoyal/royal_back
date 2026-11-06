<?php

namespace App\Http\Requests\Cursos;

use App\Models\Areas\Grado;
use App\Support\Permiso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El nivel sale del grado: si el grado elegido tiene nivel académico, ese es el del curso
     * (manda sobre el que se envíe). Solo un curso sin grado, o con un grado de todos los
     * niveles, necesita `id_nivel` explícito.
     */
    protected function prepareForValidation(): void
    {
        $nivel = $this->filled('id_grado') ? Grado::whereKey($this->input('id_grado'))->value('id_nivel_academico') : null;

        if ($nivel !== null) {
            $this->merge(['id_nivel' => $nivel]);
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'nombre' => ['required', 'string', 'max:200', Rule::unique('curso', 'nombre')->ignore($id)],
            'id_nivel' => ['required', 'integer', 'exists:nivel_academico,id'],
            // Grado del catálogo academico_grado; null = sin grado. Sin enviar al crear, se
            // propone el que encabeza el nombre (Grado::paraNombreDeCurso).
            'id_grado' => ['nullable', 'integer', 'exists:academico_grado,id'],
            // El curso al que pasan sus estudiantes al cerrar el año; no puede ser él mismo.
            'curso_proximo' => ['nullable', 'integer', 'exists:curso,id', Rule::notIn(array_filter([$id]))],
            // Director de grupo: un docente activo. Si ya dirige otro curso, se reasigna a este
            // (CursosServices::reasignarDirector deja el otro sin director).
            'id_director' => [
                'nullable',
                'integer',
                Rule::exists('usuarios', 'id_user')->whereIn('perfil', Permiso::PERFILES_DOCENTE)->where('estado', 'activo'),
            ],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del curso es obligatorio',
            'nombre.unique' => 'Ya existe un curso con ese nombre',
            'id_nivel.required' => 'Selecciona el nivel académico: el curso no tiene grado, o su grado no define el nivel',
            'id_nivel.exists' => 'El nivel académico no existe',
            'id_grado.exists' => 'El grado no existe',
            'curso_proximo.exists' => 'El curso próximo no existe',
            'curso_proximo.not_in' => 'El curso próximo no puede ser el mismo curso',
            'id_director.exists' => 'El director de grupo debe ser un docente activo',
        ];
    }
}
