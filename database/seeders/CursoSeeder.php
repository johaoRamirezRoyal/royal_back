<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CursoSeeder extends Seeder
{
    /**
     * Cursos/grupos extraídos del horario (tabla `curso`, modelo App\Models\Areas\Cursos).
     * id_nivel = grado (5 a 11). curso_proximo se deja null; ajústalo si
     * manejas promoción automática (ej. 05A -> 06A).
     */
    public function run(): void
    {
        $cursos = [
            ['05A', 5], ['05B', 5],
            ['06A', 6], ['06B', 6],
            ['07A', 7], ['07B', 7],
            ['08A', 8], ['08B', 8],
            ['09A', 9], ['09B', 9],
            ['10A', 10], ['10B', 10],
            ['11A', 11], ['11B', 11],
        ];

        foreach ($cursos as [$nombre, $nivel]) {
            DB::table('curso')->updateOrInsert(
                ['nombre' => $nombre],
                [
                    'id_nivel'        => $nivel,
                    'curso_proximo'   => null,
                    'activo'          => 1,
                    'id_log'          => 1, // TODO: ajustar al id real del usuario que registra
                    'fecharegdatetime' => now(),
                ]
            );
        }
    }
}
