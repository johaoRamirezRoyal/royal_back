<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AsignaturaSeeder extends Seeder
{
    /**
     * Catálogo de materias extraído del horario (academico_asignatura).
     */
    public function run(): void
    {
        $colores = [
            '#3498db', '#e74c3c', '#2ecc71', '#9b59b6', '#f39c12', '#1abc9c',
            '#e67e22', '#34495e', '#c0392b', '#16a085', '#27ae60', '#8e44ad',
            '#2980b9', '#d35400', '#7f8c8d', '#bdc3c7', '#2c3e50', '#f1c40f',
        ];

        $materias = [
            'Aritmética',
            'Arts',
            'Biología',
            'Chemistry',
            'Cálculo',
            'Economía y Política',
            'English',
            'Enterprise',
            'Español',
            'Estadística',
            'Estadística y Geometría',
            'Filosofía',
            'Francés',
            'Física',
            'Global Perspective',
            'Guidance',
            'ICT',
            'Lectura Crítica',
            'Medio Ambiente',
            'PE',
            'Química',
            'Religión',
            'Science',
            'Sociales',
            'Technical Drawing',
            'Trigonometría',
            'Álgebra',
            'Ética',
        ];

        foreach ($materias as $i => $nombre) {
            DB::table('academico_asignatura')->updateOrInsert(
                ['nombre' => $nombre],
                [
                    'codigo'       => Str::upper(Str::slug($nombre, '')) ?: 'ASIG'.$i,
                    'abreviatura'  => Str::upper(Str::limit(Str::slug($nombre, ''), 6, '')),
                    'color'        => $colores[$i % count($colores)],
                    'activo'       => 1,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]
            );
        }
    }
}
