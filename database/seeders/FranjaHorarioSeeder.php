<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FranjaHorarioSeeder extends Seeder
{
    /**
     * Franjas horarias (academico_franja_horaria).
     * id_dia_semana: 1=Lunes ... 5=Viernes (ajusta si tu convención es distinta, ej. 0=Domingo).
     * orden: posición del bloque dentro del día (1 a 12), tal como aparece en el Excel.
     * tipo: debe ser CLASE, RECESO o ALMUERZO (enum validado en FranjaHorariaRequest).
     */
    public function run(): void
    {
        $idAnioEscolar = 1; // TODO: ajustar al id del año escolar activo

        // [hora_inicio, hora_fin, orden, tipo]
        $bloques = [
            ['07:30:00', '08:20:00', 1,  'CLASE'],
            ['08:20:00', '09:10:00', 2,  'CLASE'],
            ['09:10:00', '10:00:00', 3,  'CLASE'],
            ['10:00:00', '10:15:00', 4,  'RECESO'],
            ['10:15:00', '10:20:00', 5,  'RECESO'],
            ['10:20:00', '11:10:00', 6,  'CLASE'],
            ['11:10:00', '12:00:00', 7,  'CLASE'],
            ['12:00:00', '12:50:00', 8,  'CLASE'],
            ['12:50:00', '13:25:00', 9,  'ALMUERZO'],
            ['13:25:00', '13:30:00', 10, 'RECESO'],
            ['13:30:00', '14:15:00', 11, 'CLASE'],
            ['14:15:00', '15:00:00', 12, 'CLASE'],
        ];

        for ($diaSemana = 1; $diaSemana <= 5; $diaSemana++) {
            foreach ($bloques as [$inicio, $fin, $orden, $tipo]) {
                DB::table('academico_franja_horaria')->updateOrInsert(
                    [
                        'id_anio_escolar' => $idAnioEscolar,
                        'id_dia_semana'   => $diaSemana,
                        'orden'           => $orden,
                    ],
                    [
                        'hora_inicio' => $inicio,
                        'hora_fin'    => $fin,
                        'tipo'        => $tipo,
                    ]
                );
            }
        }
    }
}
