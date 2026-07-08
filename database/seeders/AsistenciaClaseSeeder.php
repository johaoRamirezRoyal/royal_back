<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AsistenciaClaseSeeder extends Seeder
{
    /**
     * academico_asistencia_clase: una asistencia (DICTADA) por cada bloque de
     * tipo CLASE en academico_horario_clase, para el día que le corresponde
     * dentro de la semana actual (lunes a viernes).
     */
    public function run(): void
    {
        $horarios = DB::table('academico_horario_clase as h')
            ->join('academico_franja_horaria as f', 'h.id_franja_horaria', '=', 'f.id')
            ->where('h.tipo', 'CLASE')
            ->select('h.id as id_horario_clase', 'f.id_dia_semana')
            ->get();

        $lunes = Carbon::now()->startOfWeek(Carbon::MONDAY);

        foreach ($horarios as $horario) {
            $dia = (int) $horario->id_dia_semana; // 1=Lunes ... 5=Viernes

            if ($dia < 1 || $dia > 5) {
                continue;
            }

            $fecha = $lunes->copy()->addDays($dia - 1)->toDateString();

            DB::table('academico_asistencia_clase')->updateOrInsert(
                [
                    'id_horario_clase' => $horario->id_horario_clase,
                    'fecha'            => $fecha,
                ],
                [
                    'estado'      => 'DICTADA',
                    'observacion' => null,
                ]
            );
        }
    }
}
