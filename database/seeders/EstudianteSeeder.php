<?php

namespace Database\Seeders;

use App\Models\Usuarios\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EstudianteSeeder extends Seeder
{
    /**
     * Crea 2 estudiantes (perfil=16 en `perfiles`) por cada curso sintético
     * sembrado en CursoSeeder (05A-11B).
     *
     * IMPORTANTE: solo apunta a esos nombres de curso explícitamente -- la
     * tabla `curso` tiene datos reales de producción (Quinto A, Kinder,
     * Egresado, etc.) que no deben recibir estudiantes ficticios.
     */
    public function run(): void
    {
        $perfilEstudiante = 16;

        $nombresCursosSinteticos = [
            '05A', '05B', '06A', '06B', '07A', '07B', '08A', '08B',
            '09A', '09B', '10A', '10B', '11A', '11B',
        ];

        $cursos = DB::table('curso')
            ->whereIn('nombre', $nombresCursosSinteticos)
            ->select('id', 'nombre', 'id_nivel')
            ->get();

        foreach ($cursos as $curso) {
            for ($i = 1; $i <= 2; $i++) {
                $documento = "EST{$curso->nombre}{$i}";

                Usuario::updateOrCreate(
                    ['documento' => $documento],
                    [
                        'nombre'    => "Estudiante{$i}",
                        'apellido'  => "Curso{$curso->nombre}",
                        'correo'    => strtolower("estudiante{$i}.{$curso->nombre}@sami.edu.co"),
                        'user'      => strtolower("est{$curso->nombre}{$i}"),
                        'pass'      => 'estudiante123', // se hashea vía Usuario::setPassAttribute
                        'perfil'    => $perfilEstudiante,
                        'id_curso'  => $curso->id,
                        'id_nivel'  => $curso->id_nivel,
                        'estado'    => 'activo',
                    ]
                );
            }
        }
    }
}
