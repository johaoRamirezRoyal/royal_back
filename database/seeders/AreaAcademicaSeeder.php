<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaAcademicaSeeder extends Seeder
{
    /**
     * Áreas académicas (academico_area) + backfill de id_area en academico_asignatura.
     * Debe correr DESPUÉS de AsignaturaSeeder (necesita que las materias ya existan).
     */
    public function run(): void
    {
        $materiasPorArea = [
            'Matemáticas' => ['Aritmética', 'Cálculo', 'Estadística', 'Estadística y Geometría', 'Trigonometría', 'Álgebra'],
            'Ciencias Naturales' => ['Biología', 'Chemistry', 'Física', 'Química', 'Science', 'Medio Ambiente'],
            'Ciencias Sociales' => ['Economía y Política', 'Sociales', 'Filosofía', 'Ética', 'Religión', 'Global Perspective', 'Guidance'],
            'Lenguaje e Idiomas' => ['English', 'Español', 'Francés', 'Lectura Crítica'],
            'Artes' => ['Arts', 'Technical Drawing'],
            'Tecnología e Informática' => ['ICT', 'Enterprise'],
            'Educación Física' => ['PE'],
        ];

        foreach ($materiasPorArea as $nombreArea => $materias) {
            DB::table('academico_area')->updateOrInsert(
                ['nombre' => $nombreArea],
                ['activo' => 1, 'created_at' => now(), 'updated_at' => now()]
            );

            $idArea = DB::table('academico_area')->where('nombre', $nombreArea)->value('id');

            DB::table('academico_asignatura')->whereIn('nombre', $materias)->update(['id_area' => $idArea]);
        }
    }
}
