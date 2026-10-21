<?php

namespace App\Models\Areas;

use App\Models\Usuarios\NivelAcademico;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Cursos extends Model
{
    protected $table = "curso";

    protected $primaryKey = 'id';

    public $timestamps = false;

    public $fillable = [
        "nombre",
        "id_nivel",
        "curso_proximo",
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

    public function usuarios()
    {
        return $this->hasMany(Usuario::class, 'id_curso', 'id');
    }

    /**
     * Grado escolar reconocido por el inicio del nombre (sin tildes ni mayúsculas), del menor al
     * mayor. Más largos primero: "pre kinder" antes que "kinder". `curso` no tiene columna de
     * grado y `curso_proximo` no sirve para ordenar (las secciones A y B arrancan en puntos
     * distintos de la cadena).
     */
    private const GRADOS = [
        'parvulos' => 0, 'pre jardin' => 1, 'prejardin' => 1, 'pre kinder' => 1, 'prekinder' => 1,
        'jardin' => 2, 'kinder' => 2, 'transicion' => 3,
        'primero' => 4, 'segundo' => 5, 'tercero' => 6, 'cuarto' => 7, 'quinto' => 8,
        'sexto' => 9, 'septimo' => 10, 'octavo' => 11, 'noveno' => 12,
        'decimo' => 13, 'undecimo' => 14, 'once' => 14,
    ];

    /** Grado del curso por su nombre ("Quinto A", "Pre-Kinder B", "6A", "11°"); null si no se reconoce. */
    public static function gradoDe(string $nombre): ?int
    {
        $n = str_replace('-', ' ', Str::lower(Str::ascii(trim($nombre))));

        foreach (self::GRADOS as $palabra => $grado) {
            if (str_starts_with($n, $palabra)) {
                return $grado;
            }
        }

        // "6A", "11°": el número es el grado (1° = Primero = 4).
        return preg_match('/^(\d{1,2})/', $n, $m) ? 3 + (int) $m[1] : null;
    }

    /**
     * Cursos en orden académico: por nivel (id de nivel_academico, de Preescolar a Media), luego
     * por grado y luego por nombre natural (sección A, B…). Lo que no se reconoce como grado va
     * al final de su nivel, por nombre; sin nivel, al final de todo.
     */
    public static function ordenarPorGrado(Collection $cursos): Collection
    {
        $clave = fn ($c) => [$c->id_nivel ?? PHP_INT_MAX, self::gradoDe((string) $c->nombre) ?? PHP_INT_MAX];

        return $cursos
            ->sort(fn ($a, $b) => ($clave($a) <=> $clave($b)) ?: strnatcasecmp((string) $a->nombre, (string) $b->nombre))
            ->values();
    }
}
