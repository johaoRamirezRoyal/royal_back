<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EsquemaHorarioSeeder extends Seeder
{
    /**
     * Un esquema de horario (academico_esquema_horario) por nivel, para el año escolar
     * activo — las franjas horarias ya no cuelgan directo de un año, cuelgan de un esquema
     * (nombre + nivel + año), ver 2026_08_21_130000_create_academico_esquema_horario_table.
     * Solo se cubren los niveles que realmente tienen cursos usados por los demás seeders
     * de Gestión Académica (Primaria y Bachillerato — ver FranjaHorarioSeeder/HorarioSeeder).
     */
    public function run(): void
    {
        $idAnioEscolar = DB::table('anio_escolar')->where('activo', 1)->latest('id')->value('id');

        if (!$idAnioEscolar) {
            $this->command?->warn('EsquemaHorarioSeeder: no hay ningún año escolar activo — se omite.');
            return;
        }

        $anio = DB::table('anio_escolar')->where('id', $idAnioEscolar)->first();
        $sufijo = "{$anio->anio_inicio}-{$anio->anio_fin}";

        // id_nivel: 3 = Primaria, 4 = Bachillerato (tabla `nivel`).
        $esquemas = [
            3 => "Primaria {$sufijo}",
            4 => "Bachillerato {$sufijo}",
        ];

        foreach ($esquemas as $idNivel => $nombre) {
            DB::table('academico_esquema_horario')->updateOrInsert(
                ['id_nivel' => $idNivel, 'id_anio_escolar' => $idAnioEscolar],
                ['nombre' => $nombre, 'activo' => 1]
            );
        }
    }
}
