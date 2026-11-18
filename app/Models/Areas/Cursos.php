<?php

namespace App\Models\Areas;

use App\Models\Usuarios\NivelAcademico;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Cursos extends Model
{
    protected $table = "curso";

    protected $primaryKey = 'id';

    public $timestamps = false;

    public $fillable = [
        "nombre",
        "id_nivel",
        // Grado del catálogo academico_grado; NULL = sin grado. Ver create_academico_grado_table.
        "id_grado",
        "curso_proximo",
        "id_director",
        "activo",
        "id_log"
    ];

    // id_nivel apunta a nivel_academico (no a `nivel`) desde
    // 2026_08_25_030000_migrate_curso_and_esquema_nivel_to_nivel_academico — lo académico
    // usa nivel_academico, `nivel` es solo para clasificar usuarios y otros módulos.
    public function nivel()
    {
        return $this->belongsTo(NivelAcademico::class, 'id_nivel', 'id');
    }

    /** Docente director de grupo (opcional). */
    public function director()
    {
        return $this->belongsTo(Usuario::class, 'id_director', 'id_user');
    }

    /** Curso al que pasan sus estudiantes al cerrar el año escolar. */
    public function proximo()
    {
        return $this->belongsTo(self::class, 'curso_proximo', 'id');
    }

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'id_curso', 'id');
    }

    /** Grado del catálogo (Primero, Segundo…). Agrupa los cursos que comparten referentes en Notas. */
    public function grado()
    {
        return $this->belongsTo(Grado::class, 'id_grado', 'id');
    }

    /**
     * Cursos en orden académico: por nivel (id de nivel_academico, de Preescolar a Media), luego
     * por el orden de su grado (`curso.id_grado` → academico_grado.orden) y luego por nombre
     * natural (sección A, B…). Sin grado va al final de su nivel; sin nivel, al final de todo.
     * La consulta debe traer `id_grado`.
     */
    public static function ordenarPorGrado(Collection $cursos): Collection
    {
        $ordenGrado = Grado::pluck('orden', 'id');
        $clave = fn ($c) => [$c->id_nivel ?? PHP_INT_MAX, $ordenGrado[$c->id_grado] ?? PHP_INT_MAX];

        return $cursos
            ->sort(fn ($a, $b) => ($clave($a) <=> $clave($b)) ?: strnatcasecmp((string) $a->nombre, (string) $b->nombre))
            ->values();
    }
}
