<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Observaciones de estudiantes por día (positivas/negativas), agrupadas por periodo
     * académico (periodo_academico): el "acta" de un estudiante en un periodo es el conjunto
     * de sus filas con ese id_periodo_academico. Ver ObservacionEstudianteService.
     *
     * - id_docente: quien la registró (docente, psicología, coordinación…).
     * - id_asignatura: nullable — quien no dicta asignaturas en el curso registra sin ella.
     * - id_curso: el curso del estudiante AL registrarla (el estudiante cambia de curso cada
     *   año; las actas de periodos pasados tienen que seguir mostrando el curso de entonces).
     */
    public function up(): void
    {
        Schema::create('academico_observacion_estudiante', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();

            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->cascadeOnDelete();

            // id_periodo_academico e id_asignatura sin FK: periodo_academico y
            // academico_asignatura son MyISAM (no admiten llaves foráneas). Ambos los valida
            // ObservacionEstudianteService (periodo resuelto de la fecha; asignatura de la
            // carga académica del docente).
            $table->integer('id_periodo_academico')->index();

            $table->integer('id_docente');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');

            $table->integer('id_asignatura')->nullable();

            $table->integer('id_curso')->nullable();
            $table->foreign('id_curso')->references('id')->on('curso')->nullOnDelete();

            $table->date('fecha');
            $table->string('tipo', 10); // POSITIVA | NEGATIVA
            $table->text('motivo');

            $table->timestamps();

            $table->index(['id_estudiante', 'id_periodo_academico'], 'ix_obs_estudiante_periodo');
            $table->index('fecha', 'ix_obs_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_observacion_estudiante');
    }
};
