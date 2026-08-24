<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FranjaHorarioSeeder extends Seeder
{
    /**
     * Franjas horarias (academico_franja_horaria). Debe correr DESPUÉS de
     * EsquemaHorarioSeeder: cada franja pertenece a un esquema (nivel + año escolar), no
     * directo a un año escolar — ver 2026_08_21_130100_add_id_esquema_to_...
     * id_anio_escolar sigue siendo NOT NULL en la tabla real (columna legacy, se deriva del
     * esquema — ver FranjaHorariaService::añadirFranjaHoraria) pero ya no se usa como filtro.
     *
     * No tiene columna `tipo` (eso vive en academico_horario_clase.tipo, no en la franja) —
     * las franjas de receso/almuerzo simplemente no tienen ningún horario_clase encima.
     *
     * id_dia_semana: 1=Lunes ... 5=Viernes.
     * orden: posición del bloque dentro del día (1 a 12), igual para ambos esquemas.
     */
    public function run(): void
    {
        $idAnioEscolar = DB::table('anio_escolar')->where('activo', 1)->latest('id')->value('id');

        if (!$idAnioEscolar) {
            $this->command?->warn('FranjaHorarioSeeder: no hay ningún año escolar activo — se omite.');
            return;
        }

        $esquemas = DB::table('academico_esquema_horario')
            ->where('id_anio_escolar', $idAnioEscolar)
            ->pluck('id', 'id_nivel');

        if ($esquemas->isEmpty()) {
            $this->command?->warn('FranjaHorarioSeeder: no hay esquemas de horario para el año activo — corre EsquemaHorarioSeeder primero.');
            return;
        }

        // [hora_inicio, hora_fin, orden] — se mantiene la misma numeración de orden 1-12
        // (incluye los bloques de receso/almuerzo como franjas normales, ya sin columna
        // `tipo` porque esa vivía en esta tabla antes del refactor a esquemas y ya no
        // existe) porque HorarioSeeder referencia estos números de orden directamente.
        // Los de receso/almuerzo (4, 5, 9, 10) simplemente no reciben ningún
        // horario_clase encima — igual que antes.
        $bloques = [
            ['07:30:00', '08:20:00', 1],
            ['08:20:00', '09:10:00', 2],
            ['09:10:00', '10:00:00', 3],
            ['10:00:00', '10:15:00', 4],
            ['10:15:00', '10:20:00', 5],
            ['10:20:00', '11:10:00', 6],
            ['11:10:00', '12:00:00', 7],
            ['12:00:00', '12:50:00', 8],
            ['12:50:00', '13:25:00', 9],
            ['13:25:00', '13:30:00', 10],
            ['13:30:00', '14:15:00', 11],
            ['14:15:00', '15:00:00', 12],
        ];

        foreach ($esquemas as $idEsquema) {
            for ($diaSemana = 1; $diaSemana <= 5; $diaSemana++) {
                foreach ($bloques as [$inicio, $fin, $orden]) {
                    DB::table('academico_franja_horaria')->updateOrInsert(
                        [
                            'id_esquema'    => $idEsquema,
                            'id_dia_semana' => $diaSemana,
                            'orden'         => $orden,
                        ],
                        [
                            'id_anio_escolar' => $idAnioEscolar,
                            'hora_inicio'     => $inicio,
                            'hora_fin'        => $fin,
                        ]
                    );
                }
            }
        }
    }
}
