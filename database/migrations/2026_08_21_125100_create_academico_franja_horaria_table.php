<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloques horarios por año escolar y día de la semana. Tabla base del módulo de
     * Gestión Académica sin migración propia en este repo — ver
     * 2026_08_21_125000_create_dias_semana_table. `id_esquema` (nivel × año escolar) se
     * agrega después, en 2026_08_21_130100_add_id_esquema_to_academico_franja_horaria_table,
     * para no asumir su existencia en entornos donde esta tabla ya viene creada de antes.
     */
    public function up(): void
    {
        Schema::create('academico_franja_horaria', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_anio_escolar');
            $table->foreign('id_anio_escolar')->references('id')->on('anio_escolar')->cascadeOnDelete();

            $table->integer('id_dia_semana');
            $table->foreign('id_dia_semana')->references('id')->on('dias_semana');

            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->integer('orden');

            $table->unique(['id_anio_escolar', 'id_dia_semana', 'orden'], 'uq_franja_horaria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_franja_horaria');
    }
};
