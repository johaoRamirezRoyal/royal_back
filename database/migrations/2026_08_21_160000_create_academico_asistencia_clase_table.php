<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de si un bloque de horario (id_horario_clase, tipo=CLASE) efectivamente se
     * dictó en una fecha dada — DICTADA/CANCELADA/REPROGRAMADA (ver AsistenciaClaseRequest).
     * No confundir con academico_asistencia_estudiante (excepciones de asistencia por
     * alumno dentro de una clase ya dictada). Tabla base del módulo de Gestión Académica
     * sin migración propia en este repo, igual que academico_horario_clase/
     * academico_franja_horaria — ver AsistenciaClaseService/AsistenciaClaseSeeder.
     */
    public function up(): void
    {
        Schema::create('academico_asistencia_clase', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_horario_clase');
            $table->foreign('id_horario_clase')->references('id')->on('academico_horario_clase')->cascadeOnDelete();

            $table->date('fecha');
            $table->string('estado', 20)->default('DICTADA');
            $table->string('observacion', 255)->nullable();

            $table->unique(['id_horario_clase', 'fecha'], 'uq_asistencia_clase_horario_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_asistencia_clase');
    }
};
