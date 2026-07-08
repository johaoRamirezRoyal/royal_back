<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden de ejecución importante: cada seeder depende de que
     * los anteriores ya hayan insertado sus datos base.
     *
     * php artisan db:seed
     */
    public function run(): void
    {
        $this->call([
            AsignaturaSeeder::class,          // academico_asignatura
            EstudianteSeeder::class,          // usuarios (perfil=16, requiere que los cursos 05A-11B ya existan en `curso`)
            FranjaHorarioSeeder::class,       // academico_franja_horaria
            DocenteAsignaturaSeeder::class,   // academico_docente_asignatura (requiere usuarios ya cargados)
            CargaAcademicaSeeder::class,      // academico_carga_academica
            HorarioSeeder::class,             // academico_horario_clase
            AsistenciaClaseSeeder::class,     // academico_asistencia_clase (requiere academico_horario_clase)
        ]);
    }
}
