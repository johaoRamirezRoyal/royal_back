<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Horario suelto": un bloque de horario (tipo=CLASE) para un docente + curso sin pasar
 * por una asignatura puntual (tutoría, disponibilidad, etc.) — ver
 * CargaAcademicaService::añadirCargaAcademicaSuelta. `id_docente_asignatura` deja de ser
 * obligatoria: una carga académica ahora representa docente+curso (con asignatura, vía
 * `id_docente_asignatura`) O docente+curso sin asignatura (vía este `id_docente` directo)
 * — exactamente una de las dos, nunca ambas ni ninguna, validado en el servicio, no acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ->change() de Blueprint requiere doctrine/dbal, no instalado en este proyecto —
        // ALTER TABLE crudo, mismo criterio que
        // 2026_08_08_000000_add_dias_habiles_to_asistencia_horarios_estandar_table.
        DB::statement('ALTER TABLE academico_carga_academica MODIFY id_docente_asignatura INT NULL');

        Schema::table('academico_carga_academica', function (Blueprint $table) {
            $table->integer('id_docente')->nullable()->after('id_docente_asignatura');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');
            $table->unique(['id_docente', 'id_curso']);
        });
    }

    public function down(): void
    {
        Schema::table('academico_carga_academica', function (Blueprint $table) {
            $table->dropUnique(['id_docente', 'id_curso']);
            $table->dropForeign(['id_docente']);
            $table->dropColumn('id_docente');
        });

        DB::statement('ALTER TABLE academico_carga_academica MODIFY id_docente_asignatura INT NOT NULL');
    }
};
