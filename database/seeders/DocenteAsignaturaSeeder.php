<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Concerns\ResolvesDocentePorNombre;

class DocenteAsignaturaSeeder extends Seeder
{
    use ResolvesDocentePorNombre;

    /**
     * academico_docente_asignatura: cruza id_docente (usuarios.id_user) con id_asignatura.
     *
     * Los docentes se resuelven con un match difuso por palabras (ver
     * Concerns\ResolvesDocentePorNombre) — un match exacto de "nombre apellido" no
     * encuentra nada porque `usuarios.nombre`/`apellido` viene con datos reales sucios
     * (nombre completo en un solo campo, apellido="." de relleno).
     */
    public function run(): void
    {
        $pares = [
            ['Corina Corpas', 'Biología'],
            ['Corina Corpas', 'Medio Ambiente'],
            ['Derys Mora', 'English'],
            ['Derys Mora', 'Global Perspective'],
            ['Diana Palma', 'Español'],
            ['Diógenes Visbal', 'Química'],
            ['Dorian Peña', 'Física'],
            ['Douglas Donado', 'Filosofía'],
            ['Douglas Donado', 'Religión'],
            ['Douglas Donado', 'Ética'],
            ['Edgar Fuentes', 'Economía y Política'],
            ['Edgar Fuentes', 'Sociales'],
            ['Erika Rodríguez', 'Español'],
            ['Fadia Ruíz', 'Francés'],
            ['Federico Sánchez', 'Chemistry'],
            ['Federico Sánchez', 'Science'],
            ['Ingrid Almendrales', 'Español'],
            ['Ingrid Almendrales', 'Lectura Crítica'],
            ['Jaime Mejía', 'PE'],
            ['Jhon Arrieta', 'English'],
            ['Juan Pablo García', 'Chemistry'],
            ['Juan Pablo García', 'Science'],
            ['Katty Mercado', 'Aritmética'],
            ['Kenny Cohen', 'Enterprise'],
            ['Kenny Cohen', 'Global Perspective'],
            ['Linda Cera', 'English'],
            ['Linda Cera', 'Global Perspective'],
            ['Loly Algarín', 'Arts'],
            ['Loly Algarín', 'Technical Drawing'],
            ['Mario Esmeral', 'ICT'],
            ['Mary Torres', 'Guidance'],
            ['Nilson Santiz', 'PE'],
            ['Paulo Álvarez', 'Cálculo'],
            ['Paulo Álvarez', 'Trigonometría'],
            ['Samir Peñate', 'Filosofía'],
            ['Samir Peñate', 'Sociales'],
            ['Santiago Charris', 'Estadística'],
            ['Santiago Charris', 'Estadística y Geometría'],
            ['Santiago Charris', 'Álgebra'],
            ['Yesid Oliveros', 'Estadística'],
            ['Yesid Oliveros', 'Estadística y Geometría'],
            ['Yesid Oliveros', 'Álgebra'],
        ];

        $noEncontrados = [];

        foreach ($pares as [$nombreDocente, $nombreMateria]) {
            $idDocente = $this->resolverDocenteId($nombreDocente);
            $idAsignatura = DB::table('academico_asignatura')->where('nombre', $nombreMateria)->value('id');

            if (!$idDocente) {
                $noEncontrados[] = $nombreDocente;
                continue;
            }

            if (!$idAsignatura) {
                continue; // materia no encontrada, revisar AsignaturaSeeder
            }

            DB::table('academico_docente_asignatura')->updateOrInsert(
                ['id_docente' => $idDocente, 'id_asignatura' => $idAsignatura],
                ['activo' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        if (!empty($noEncontrados)) {
            $this->command?->warn(
                'Docentes no encontrados en usuarios: '.implode(', ', array_unique($noEncontrados))
            );
        }
    }
}
