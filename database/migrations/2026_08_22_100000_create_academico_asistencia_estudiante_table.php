<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Excepciones de asistencia por estudiante dentro de una clase ya DICTADA —
     * AUSENTE/TARDE/PERMISO (ver AsistenciaEstudianteRequest/AsistenciaEstudianteService).
     * No hay fila por cada alumno presente, solo por las excepciones. Tabla base del
     * módulo de Gestión Académica sin migración propia en este repo, igual que
     * academico_asistencia_clase — ver 2026_08_21_160000_create_academico_asistencia_clase_table.
     */
    public function up(): void
    {
        Schema::create('academico_asistencia_estudiante', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_asistencia_clase');
            $table->foreign('id_asistencia_clase')->references('id')->on('academico_asistencia_clase')->cascadeOnDelete();

            $table->integer('id_alumno');
            $table->foreign('id_alumno')->references('id_user')->on('usuarios')->cascadeOnDelete();

            $table->string('estado', 20);
            $table->string('observacion', 255)->nullable();

            // Solo created_at: el modelo declara const UPDATED_AT = null (no se registran ediciones).
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id_asistencia_clase', 'id_alumno'], 'uq_asistencia_estudiante_clase_alumno');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_asistencia_estudiante');
    }
};
