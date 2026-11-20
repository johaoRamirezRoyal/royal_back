<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observación interna del docente por estudiante y referente (ver docs/contexto/modulos/notas.md):
 * no sale en el boletín. Tabla aparte de las valoraciones: quitar la nota no borra la observación.
 *
 * id_periodo_academico e id_curso sin FK: periodo_academico y (en algunas BD) curso son MyISAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academico_observacion_referente', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->cascadeOnDelete();
            $table->integer('id_referente');
            $table->foreign('id_referente')->references('id')->on('academico_referente')->cascadeOnDelete();
            $table->integer('id_periodo_academico');
            $table->integer('id_curso');
            $table->text('observacion');
            $table->integer('id_docente');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');
            $table->timestamps();

            $table->unique(['id_estudiante', 'id_referente'], 'uq_observacion_referente');
            $table->index(['id_curso', 'id_periodo_academico'], 'ix_observacion_referente_curso_periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_observacion_referente');
    }
};
