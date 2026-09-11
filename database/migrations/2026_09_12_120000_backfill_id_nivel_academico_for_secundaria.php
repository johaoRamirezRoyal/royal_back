<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La migración 2026_08_25_020000_add_id_nivel_academico_to_nivel_table solo hizo match por
 * nombre contra 'Bachillerato'/'Media'/'Educación media' para vincular el nivel_academico=4
 * (Educación media) — en esta BD la fila de `nivel` id=4 se llama "Secundaria" (nunca se le
 * hizo el rename manual a "Bachillerato" que esa migración anticipaba como alternativa), así
 * que quedó con id_nivel_academico=null. Cualquier docente clasificado con ese nivel no veía
 * NINGÚN curso en "Mi horario" (DocenteHorarioService::verMenu filtra cursos por
 * nivel_academico, y un docente sin id_nivel_academico resuelto se trata como "sin cursos").
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('nivel')
            ->where('nombre', 'Secundaria')
            ->whereNull('id_nivel_academico')
            ->update(['id_nivel_academico' => 4]);
    }

    public function down(): void
    {
        // Dato, no estructura — sin rollback automático, igual que el resto de
        // migraciones de seed/backfill de este repo.
    }
};
