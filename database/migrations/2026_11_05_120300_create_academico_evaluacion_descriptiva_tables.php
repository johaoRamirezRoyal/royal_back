<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluación descriptiva (ver docs/contexto/modulos/notas.md): grados que no califican por
 * referentes ni escala (Pre-jardín, Stem...) sino con textos por propósito en campos definidos de
 * antemano (Fortalezas, Por fortalecer, Recomendaciones...).
 *
 * - academico_grado.tipo_evaluacion: REFERENTES (por defecto) | DESCRIPTIVA.
 * - academico_proposito_campo: los campos de cada propósito, en orden. No se borran si ya tienen
 *   textos: se desactivan.
 * - academico_valoracion_descriptiva: el texto de un estudiante en un campo y periodo. Lo escribe
 *   un docente con carga en el curso.
 *
 * id_periodo_academico e id_curso sin FK: periodo_academico y (en algunas BD) curso son MyISAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academico_grado', function (Blueprint $table) {
            $table->string('tipo_evaluacion', 12)->default('REFERENTES')->after('id_nivel_academico');
        });

        Schema::create('academico_proposito_campo', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_proposito');
            $table->foreign('id_proposito')->references('id')->on('academico_proposito')->cascadeOnDelete();
            $table->string('nombre', 80);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('id_proposito');
        });

        Schema::create('academico_valoracion_descriptiva', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->cascadeOnDelete();
            // Sin cascada: un campo con textos no se borra, se desactiva.
            $table->integer('id_campo');
            $table->foreign('id_campo')->references('id')->on('academico_proposito_campo');
            $table->integer('id_periodo_academico');
            // Foto del curso al escribir: la promoción cambia usuarios.id_curso.
            $table->integer('id_curso');
            $table->text('texto');
            $table->integer('id_docente');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');
            $table->timestamps();

            $table->unique(['id_estudiante', 'id_campo', 'id_periodo_academico'], 'uq_descriptiva_estudiante_campo_periodo');
            $table->index(['id_curso', 'id_periodo_academico'], 'ix_descriptiva_curso_periodo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_valoracion_descriptiva');
        Schema::dropIfExists('academico_proposito_campo');
        Schema::table('academico_grado', fn (Blueprint $table) => $table->dropColumn('tipo_evaluacion'));
    }
};
