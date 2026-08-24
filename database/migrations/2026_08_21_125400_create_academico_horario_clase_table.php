<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bloques de horario: qué ocurre (CLASE/PLANEACION/REUNION/CLUB/LIBRE/RECESO/
     * ALMUERZO) en cada franja horaria. id_carga_academica es nullable — los bloques sin
     * docente (recesos, almuerzos) son compartidos por todo el colegio, ver
     * HorarioClaseService::franjaDisponibleParaCarga. Tabla base del módulo de Gestión
     * Académica sin migración propia en este repo — ver
     * 2026_08_21_125000_create_dias_semana_table.
     */
    public function up(): void
    {
        Schema::create('academico_horario_clase', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_carga_academica')->nullable();
            $table->foreign('id_carga_academica')->references('id')->on('academico_carga_academica')->nullOnDelete();

            $table->integer('id_franja_horaria');
            $table->foreign('id_franja_horaria')->references('id')->on('academico_franja_horaria')->restrictOnDelete();

            $table->string('tipo', 20);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_horario_clase');
    }
};
