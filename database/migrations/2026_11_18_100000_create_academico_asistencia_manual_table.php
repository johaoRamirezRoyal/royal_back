<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inasistencias y llegadas tarde del boletín escritas a mano (ver docs/contexto/modulos/notas.md):
 * reemplazan al conteo de los registros (asistencia de clases / portería). Null = usar el conteo.
 *
 * id_periodo_academico e id_curso sin FK: periodo_academico y (en algunas BD) curso son MyISAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academico_asistencia_manual', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->cascadeOnDelete();
            $table->integer('id_periodo_academico');
            $table->integer('id_curso');
            $table->unsignedSmallInteger('inasistencias')->nullable();
            $table->unsignedSmallInteger('llegadas_tarde')->nullable();
            $table->integer('id_usuario');
            $table->foreign('id_usuario')->references('id_user')->on('usuarios');
            $table->timestamps();

            $table->unique(['id_estudiante', 'id_periodo_academico'], 'uq_asistencia_manual');
            $table->index(['id_curso', 'id_periodo_academico'], 'ix_asistencia_manual_curso_periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_asistencia_manual');
    }
};
