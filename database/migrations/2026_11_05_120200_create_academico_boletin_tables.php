<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre del trimestre y boletines de notas cualitativas (ver docs/contexto/modulos/notas.md):
 *
 * - academico_observacion_general: "Observaciones Generales del Director de Grupo", una por
 *   estudiante y periodo.
 * - academico_cierre_periodo: el coordinador cierra un curso en un periodo; desde ahí no se
 *   califica, ajusta ni observa en ese curso y periodo.
 * - academico_boletin: foto de los datos del boletín de cada estudiante al cerrar (JSON). No
 *   cambia aunque luego se corrija una nota; el PDF se genera desde ella. Queda el historial
 *   por periodo.
 *
 * id_periodo_academico e id_curso sin FK: periodo_academico y (en algunas BD) curso son MyISAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academico_observacion_general', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->cascadeOnDelete();
            $table->integer('id_periodo_academico');
            // Foto del curso al escribirla: la promoción cambia usuarios.id_curso.
            $table->integer('id_curso');
            $table->text('observacion');
            $table->integer('id_docente');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');
            $table->timestamps();

            $table->unique(['id_estudiante', 'id_periodo_academico'], 'uq_observacion_estudiante_periodo');
            $table->index(['id_curso', 'id_periodo_academico'], 'ix_observacion_curso_periodo');
        });

        Schema::create('academico_cierre_periodo', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_curso');
            $table->integer('id_periodo_academico');
            $table->integer('id_usuario');
            $table->foreign('id_usuario')->references('id_user')->on('usuarios');
            $table->timestamp('fecha')->useCurrent();

            $table->unique(['id_curso', 'id_periodo_academico'], 'uq_cierre_curso_periodo');
        });

        Schema::create('academico_boletin', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_cierre');
            $table->foreign('id_cierre')->references('id')->on('academico_cierre_periodo')->cascadeOnDelete();
            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->cascadeOnDelete();
            $table->integer('id_periodo_academico');
            $table->integer('id_curso');
            $table->longText('datos'); // JSON: ver NotasService::fotoBoletin
            $table->timestamps();

            $table->unique(['id_estudiante', 'id_periodo_academico'], 'uq_boletin_estudiante_periodo');
            $table->index('id_cierre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_boletin');
        Schema::dropIfExists('academico_cierre_periodo');
        Schema::dropIfExists('academico_observacion_general');
    }
};
