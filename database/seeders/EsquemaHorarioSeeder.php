<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EsquemaHorarioSeeder extends Seeder
{
    /**
     * Un esquema de horario (academico_esquema_horario) por nivel académico, para el año
     * escolar activo — las franjas horarias ya no cuelgan directo de un año, cuelgan de un
     * esquema (nombre + nivel + año), ver 2026_08_21_130000_create_academico_esquema_horario_table.
     * Solo se cubren los niveles que realmente tienen cursos usados por los demás seeders
     * de Gestión Académica (Primaria, Secundaria y Media — ver FranjaHorarioSeeder/
     * HorarioSeeder; Preescolar no tiene cursos en esos seeders, se omite igual que antes).
     *
     * id_nivel apunta a nivel_academico (no a `nivel`) desde
     * 2026_08_25_030000_migrate_curso_and_esquema_nivel_to_nivel_academico — Bachillerato
     * (6°-11° bajo un solo esquema) se separó en Secundaria (6°-9°) y Media (10°-11°).
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

        // id_nivel: 2 = Primaria, 3 = Secundaria, 4 = Media (tabla `nivel_academico`).
        $esquemas = [
            2 => "Primaria {$sufijo}",
            3 => "Secundaria {$sufijo}",
            4 => "Media {$sufijo}",
        ];

        foreach ($esquemas as $idNivel => $nombre) {
            DB::table('academico_esquema_horario')->updateOrInsert(
                ['id_nivel' => $idNivel, 'id_anio_escolar' => $idAnioEscolar],
                ['nombre' => $nombre, 'activo' => 1]
            );
        }
    }
}
