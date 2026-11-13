<?php

namespace App\Models\Areas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Grado escolar (Primero, Segundo…), editable en Configuración académica. Los cursos con el
 * mismo grado (Primero A y B) comparten referentes en Notas. No se borran: se desactivan.
 */
class Grado extends Model
{
    protected $table = 'academico_grado';

    protected $fillable = ['nombre', 'id_nivel_academico', 'orden', 'activo'];

    protected $casts = ['activo' => 'boolean', 'orden' => 'integer', 'id_nivel_academico' => 'integer'];

    /**
     * Grado activo cuyo nombre encabeza el del curso ("Primero A" → Primero), para proponerlo al
     * crear un curso. Los nombres más largos primero ("Pre-jardín" antes que "Jardín").
     */
    public static function paraNombreDeCurso(string $nombreCurso): ?int
    {
        $norm = fn (string $s) => str_replace('-', ' ', Str::lower(Str::ascii(trim($s))));
        $curso = $norm($nombreCurso);

        return self::where('activo', 1)->get(['id', 'nombre'])
            ->sortByDesc(fn ($g) => strlen($norm($g->nombre)))
            ->first(fn ($g) => str_starts_with($curso, $norm($g->nombre)))?->id;
    }
}
