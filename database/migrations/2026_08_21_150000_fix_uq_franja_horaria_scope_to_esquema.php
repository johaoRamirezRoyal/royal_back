<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `uq_franja_horaria` (2026_08_21_125100_create_academico_franja_horaria_table) quedó
     * en (id_anio_escolar, id_dia_semana, orden) — nunca se actualizó cuando
     * 2026_08_21_130100_add_id_esquema_to_academico_franja_horaria_table introdujo
     * `id_esquema`. Efecto real: dos niveles (esquemas distintos) del mismo año escolar no
     * podían tener franjas en el mismo día+orden, aunque
     * FranjaHorariaService::añadirFranjaHoraria ya valida duplicados por id_esquema, no por
     * año — el índice de BD estaba más restrictivo que la regla de negocio real.
     */
    public function up(): void
    {
        Schema::table('academico_franja_horaria', function (Blueprint $table) {
            // uq_franja_horaria (id_anio_escolar, ...) es también el índice que respalda la
            // FK de id_anio_escolar — MySQL no deja borrarlo sin un índice de reemplazo que
            // cubra esa columna primero.
            $table->index('id_anio_escolar', 'idx_franja_horaria_anio_escolar');
            $table->unique(['id_esquema', 'id_dia_semana', 'orden'], 'uq_franja_horaria_esquema');
            $table->dropUnique('uq_franja_horaria');
        });
    }

    public function down(): void
    {
        Schema::table('academico_franja_horaria', function (Blueprint $table) {
            $table->unique(['id_anio_escolar', 'id_dia_semana', 'orden'], 'uq_franja_horaria');
            $table->dropUnique('uq_franja_horaria_esquema');
            $table->dropIndex('idx_franja_horaria_anio_escolar');
        });
    }
};
