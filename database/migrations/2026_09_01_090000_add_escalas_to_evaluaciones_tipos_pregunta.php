<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Completa el catálogo de `evaluaciones_tipos_pregunta` con los tipos que el
     * frontend ya sabe autocompletar en `opcionesPorDefecto.helpers.ts`
     * (escala_likert/escala_real/si_no/calificacion_numerica) — la migración original
     * (2026_08_21_090100) solo sembró seleccion_unica/seleccion_multiple/texto_libre.
     * De paso renombra "seleccion_unica" a "opcion_multiple", que es el slug real que
     * documenta el frontend (CLAUDE.md) para "una opción entre varias" — el nombre
     * anterior fue un supuesto equivocado al reconstruir el catálogo perdido.
     */
    public function up(): void
    {
        DB::table('evaluaciones_tipos_pregunta')
            ->where('slug', 'seleccion_unica')
            ->update(['nombre' => 'Opción múltiple', 'slug' => 'opcion_multiple']);

        foreach ([
            ['nombre' => 'Escala Likert', 'slug' => 'escala_likert'],
            ['nombre' => 'Escala real', 'slug' => 'escala_real'],
            ['nombre' => 'Sí/No', 'slug' => 'si_no'],
            ['nombre' => 'Calificación numérica', 'slug' => 'calificacion_numerica'],
        ] as $tipo) {
            DB::table('evaluaciones_tipos_pregunta')->updateOrInsert(
                ['slug' => $tipo['slug']],
                ['nombre' => $tipo['nombre']]
            );
        }
    }

    public function down(): void
    {
        DB::table('evaluaciones_tipos_pregunta')
            ->whereIn('slug', ['escala_likert', 'escala_real', 'si_no', 'calificacion_numerica'])
            ->delete();

        DB::table('evaluaciones_tipos_pregunta')
            ->where('slug', 'opcion_multiple')
            ->update(['nombre' => 'Selección única', 'slug' => 'seleccion_unica']);
    }
};
