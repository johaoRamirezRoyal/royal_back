<?php

namespace App\Http\Requests\Cursos;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;
use App\Http\Requests\Usuarios\RegistrarUsuarioRequest;
use App\Models\Areas\Cursos;
use App\Models\Usuarios\Nivel;
use App\Services\Cursos\CursosServices;
use Illuminate\Support\Facades\DB;

/**
 * Estudiante nuevo desde un curso: las mismas reglas que crear un usuario, pero el perfil, el
 * curso, el nivel y el usuario de acceso no los manda el cliente.
 */
class CrearEstudianteRequest extends RegistrarUsuarioRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $curso = Cursos::find((int) $this->route('id'));

        $this->merge([
            'perfil' => Perfil::idParaCrear(TipoPerfil::ESTUDIANTE),
            'id_curso' => $curso?->id,
            'id_nivel' => $curso ? self::nivelDeUsuario($curso->id_nivel) : null,
            // Los estudiantes legacy entran con el documento como usuario.
            'user' => $this->documento,
        ]);
    }

    /**
     * `nivel` (clasificación del usuario) a partir del nivel académico del curso: el nivel de
     * usuario que lo abarca (nivel_nivel_academico; Prejardín → Preescolar). Sin ninguno, el de
     * Media (4), como antes.
     */
    private static function nivelDeUsuario(?int $idNivelAcademico): ?int
    {
        if (! $idNivelAcademico) {
            return null;
        }

        return DB::table('nivel_nivel_academico')->where('id_nivel_academico', $idNivelAcademico)->orderBy('id_nivel')->value('id_nivel')
            ?? Nivel::where('id_nivel_academico', 4)->value('id');
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'id_curso.required' => 'El curso no existe',
            'id_nivel.required' => 'El curso no tiene nivel académico; asígnaselo antes de crear estudiantes',
        ];
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['id_curso'] = ['required', 'integer'];

        return $rules;
    }
}
