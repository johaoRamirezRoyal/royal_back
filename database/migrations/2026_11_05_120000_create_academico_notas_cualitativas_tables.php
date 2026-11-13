<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notas cualitativas (ver docs/contexto/modulos/notas.md):
 *
 * - nivel_academico.metodo_calificacion: CUALITATIVA | CUANTITATIVA | AMBAS.
 * - periodo_academico.fecha_limite_notas: plazo para calificar y corregir (NULL = fecha_fin).
 * - academico_escala_valoracion (+ _nivel): desempeños; sin niveles = escala general.
 * - academico_proposito: catálogo único de propósitos del colegio (los mismos para todos).
 * - academico_referente: referentes de un grado en un periodo, cada uno en un propósito y una
 *   asignatura (`id_asignatura` → academico_asignatura, SIN FK: es MyISAM). Un referente puede
 *   tener subreferentes (`id_padre`, un solo nivel), que heredan grado, periodo, propósito y
 *   asignatura del padre. Primero A y Primero B comparten los de Primero.
 * - academico_referente_plan (+ _historial): estado de los referentes del grado en el periodo.
 * - academico_valoracion_referente: desempeño de un estudiante en un referente o subreferente.
 *   Lo pone el docente de la asignatura (`id_escala_docente`); en un referente, el coordinador
 *   puede ajustarlo (`id_escala_ajuste`, quién y cuándo). El final es el ajuste o, si no hay, el
 *   del docente.
 *
 * id_periodo_academico e id_curso sin FK: periodo_academico y (en algunas BD) curso son MyISAM.
 * Los valida NotasService. Las dos tablas legacy se alteran con ALTER directo (no hay
 * doctrine/dbal). academico_grado viene de 2026_11_05_110000.
 *
 * Fusiona lo que en desarrollo fueron varias migraciones (dimensiones por grado, logros por
 * dimensión o asignatura y modos por nivel), reemplazadas por propósitos y referentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nivel_academico', function (Blueprint $table) {
            $table->string('metodo_calificacion', 12)->default('CUALITATIVA')->after('nombre');
        });
        DB::statement('ALTER TABLE periodo_academico ADD COLUMN fecha_limite_notas DATE NULL AFTER fecha_fin');

        Schema::create('academico_escala_valoracion', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('nombre', 50);
            $table->string('abreviatura', 10)->nullable();
            $table->string('descripcion', 500)->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('academico_escala_valoracion_nivel', function (Blueprint $table) {
            $table->integer('id_escala');
            $table->foreign('id_escala')->references('id')->on('academico_escala_valoracion')->cascadeOnDelete();
            $table->integer('id_nivel_academico')->index();
            $table->primary(['id_escala', 'id_nivel_academico']);
        });

        Schema::create('academico_proposito', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('nombre', 120);
            $table->string('descripcion', 500)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('academico_referente', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_grado');
            $table->foreign('id_grado')->references('id')->on('academico_grado');
            $table->integer('id_periodo_academico');
            $table->integer('id_proposito');
            $table->foreign('id_proposito')->references('id')->on('academico_proposito');
            $table->integer('id_asignatura');
            // Subreferente: el referente del que cuelga (un solo nivel). Borrar el padre borra los suyos.
            $table->integer('id_padre')->nullable();
            $table->foreign('id_padre')->references('id')->on('academico_referente')->cascadeOnDelete();
            $table->text('descripcion');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->integer('id_docente');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');
            $table->timestamps();

            $table->index(['id_grado', 'id_periodo_academico'], 'ix_referente_grado_periodo');
            $table->index('id_padre');
        });

        Schema::create('academico_referente_plan', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_grado');
            $table->foreign('id_grado')->references('id')->on('academico_grado');
            $table->integer('id_periodo_academico');
            $table->string('estado', 15)->default('BORRADOR'); // BORRADOR | EN_REVISION | APROBADO | DEVUELTO
            $table->timestamps();

            $table->unique(['id_grado', 'id_periodo_academico'], 'uq_referente_plan');
            $table->index(['id_periodo_academico', 'estado'], 'ix_referente_plan_periodo_estado');
        });

        Schema::create('academico_referente_plan_historial', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_plan');
            $table->foreign('id_plan')->references('id')->on('academico_referente_plan')->cascadeOnDelete();
            $table->integer('id_usuario');
            $table->foreign('id_usuario')->references('id_user')->on('usuarios');
            // REFERENTE_CREADO | REFERENTE_EDITADO | REFERENTE_ELIMINADO | ENVIADO | APROBADO | DEVUELTO | REABIERTO
            $table->string('accion', 20);
            $table->text('detalle')->nullable(); // texto del referente o comentario
            $table->timestamp('fecha')->useCurrent();

            $table->index('id_plan');
        });

        Schema::create('academico_valoracion_referente', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->cascadeOnDelete();
            // Sin cascada: NotasService no deja quitar un referente con notas.
            $table->integer('id_referente');
            $table->foreign('id_referente')->references('id')->on('academico_referente');
            // El del referente, repetido para filtrar por curso y periodo sin unir.
            $table->integer('id_periodo_academico');
            // Foto del curso al calificar: la promoción cambia usuarios.id_curso.
            $table->integer('id_curso');
            $table->integer('id_escala_docente')->nullable();
            $table->foreign('id_escala_docente')->references('id')->on('academico_escala_valoracion');
            $table->integer('id_docente')->nullable();
            $table->foreign('id_docente')->references('id_user')->on('usuarios');
            // Ajuste del coordinador (solo en referentes, no en subreferentes).
            $table->integer('id_escala_ajuste')->nullable();
            $table->foreign('id_escala_ajuste')->references('id')->on('academico_escala_valoracion');
            $table->integer('id_ajustado_por')->nullable();
            $table->foreign('id_ajustado_por')->references('id_user')->on('usuarios');
            $table->timestamp('ajustado_at')->nullable();
            $table->timestamps();

            $table->unique(['id_estudiante', 'id_referente'], 'uq_valoracion_estudiante_referente');
            $table->index(['id_curso', 'id_periodo_academico'], 'ix_valoracion_curso_periodo');
            $table->index('id_referente');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_valoracion_referente');
        Schema::dropIfExists('academico_referente_plan_historial');
        Schema::dropIfExists('academico_referente_plan');
        Schema::dropIfExists('academico_referente');
        Schema::dropIfExists('academico_proposito');
        Schema::dropIfExists('academico_escala_valoracion_nivel');
        Schema::dropIfExists('academico_escala_valoracion');
        DB::statement('ALTER TABLE periodo_academico DROP COLUMN fecha_limite_notas');
        Schema::table('nivel_academico', function (Blueprint $table) {
            $table->dropColumn('metodo_calificacion');
        });
    }
};
