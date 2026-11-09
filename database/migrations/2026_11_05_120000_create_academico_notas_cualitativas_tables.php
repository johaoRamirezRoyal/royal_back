<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notas cualitativas (ver docs/contexto/modulos/notas.md):
 *
 * - nivel_academico.metodo_calificacion: CUALITATIVA | CUANTITATIVA | AMBAS.
 * - nivel_academico.tipo_unidad: DIMENSION | AREA | ASIGNATURA, qué unidad evaluable usa el nivel.
 * - nivel_academico.calificador: DIRECTOR_GRUPO | DOCENTE_ASIGNATURA, quién califica en el nivel
 *   (ver NotasService::calificadorPermite).
 * - periodo_academico.fecha_limite_notas: plazo para calificar y corregir (NULL = fecha_fin).
 * - academico_escala_valoracion (+ _nivel): desempeños; sin niveles = escala general.
 * - academico_dimension (+ academico_grado_dimension): cada fila es una **unidad evaluable**
 *   (`tipo` DIMENSION | AREA | ASIGNATURA) y los grados que se evalúan con ella. DIMENSION en
 *   preescolar; AREA y ASIGNATURA apuntan a academico_area / academico_asignatura (`id_area` /
 *   `id_asignatura`, cada uno a lo sumo una vez) SIN FK: academico_asignatura es MyISAM. Los
 *   valida NotasService (y el CHECK ck_unidad_tipo donde MySQL lo aplica, ≥ 8.0.16). La columna
 *   y las tablas conservan el nombre "dimension" (id_dimension) para no romper la API.
 * - academico_logro: indicadores por grado + periodo, cada uno en una dimensión del grado (no
 *   dependen de la asignatura). Primero A y Primero B comparten los de Primero.
 * - academico_logro_plan (+ _historial): estado de los logros del grado en el periodo.
 * - academico_valoracion (+ _logro): por estudiante, dimensión y periodo: desempeño final de la
 *   dimensión, observación y desempeño por logro. La pone el director de grupo del curso.
 *
 * id_periodo_academico e id_curso sin FK: periodo_academico y (en algunas BD) curso son MyISAM.
 * Los valida NotasService. Las dos tablas legacy se alteran con ALTER directo (no hay
 * doctrine/dbal). academico_grado viene de 2026_11_05_110000.
 *
 * Fusiona lo que en desarrollo fueron varias migraciones (escala por niveles, descripción,
 * plan de logros, dimensiones, método por nivel, un `tipo` de asignatura ya revertido y logros y
 * dimensiones por asignatura, que pasaron a ser por grado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nivel_academico', function (Blueprint $table) {
            $table->string('metodo_calificacion', 12)->default('CUALITATIVA')->after('nombre');
            $table->string('tipo_unidad', 12)->default('DIMENSION')->after('metodo_calificacion'); // DIMENSION | AREA | ASIGNATURA
            $table->string('calificador', 20)->default('DIRECTOR_GRUPO')->after('tipo_unidad');    // DIRECTOR_GRUPO | DOCENTE_ASIGNATURA
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

        Schema::create('academico_dimension', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('nombre', 80);
            $table->string('descripcion', 500)->nullable();
            $table->string('tipo', 12)->default('DIMENSION');
            $table->integer('id_area')->nullable()->unique();
            $table->integer('id_asignatura')->nullable()->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        DB::statement("ALTER TABLE academico_dimension ADD CONSTRAINT ck_unidad_tipo CHECK (
            (tipo = 'DIMENSION' AND id_area IS NULL AND id_asignatura IS NULL)
            OR (tipo = 'AREA' AND id_area IS NOT NULL AND id_asignatura IS NULL)
            OR (tipo = 'ASIGNATURA' AND id_asignatura IS NOT NULL AND id_area IS NULL))");

        Schema::create('academico_grado_dimension', function (Blueprint $table) {
            $table->integer('id_grado');
            $table->foreign('id_grado')->references('id')->on('academico_grado')->cascadeOnDelete();
            $table->integer('id_dimension');
            $table->foreign('id_dimension')->references('id')->on('academico_dimension')->cascadeOnDelete();
            $table->primary(['id_grado', 'id_dimension']);
            $table->index('id_dimension');
        });

        Schema::create('academico_logro', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_grado');
            $table->foreign('id_grado')->references('id')->on('academico_grado');
            $table->integer('id_periodo_academico');
            $table->integer('id_dimension');
            $table->foreign('id_dimension')->references('id')->on('academico_dimension');
            $table->text('descripcion');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->integer('id_docente');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');
            $table->timestamps();

            $table->index(['id_grado', 'id_periodo_academico'], 'ix_logro_grado_periodo');
        });

        Schema::create('academico_logro_plan', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_grado');
            $table->foreign('id_grado')->references('id')->on('academico_grado');
            $table->integer('id_periodo_academico');
            $table->string('estado', 15)->default('BORRADOR'); // BORRADOR | EN_REVISION | APROBADO | DEVUELTO
            $table->timestamps();

            $table->unique(['id_grado', 'id_periodo_academico'], 'uq_logro_plan');
            $table->index(['id_periodo_academico', 'estado'], 'ix_logro_plan_periodo_estado');
        });

        Schema::create('academico_logro_plan_historial', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_plan');
            $table->foreign('id_plan')->references('id')->on('academico_logro_plan')->cascadeOnDelete();
            $table->integer('id_usuario');
            $table->foreign('id_usuario')->references('id_user')->on('usuarios');
            // LOGRO_CREADO | LOGRO_EDITADO | LOGRO_ELIMINADO | ENVIADO | APROBADO | DEVUELTO | REABIERTO
            $table->string('accion', 20);
            $table->text('detalle')->nullable(); // texto del logro o comentario
            $table->timestamp('fecha')->useCurrent();

            $table->index('id_plan');
        });

        Schema::create('academico_valoracion', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->cascadeOnDelete();
            $table->integer('id_dimension');
            $table->foreign('id_dimension')->references('id')->on('academico_dimension');
            $table->integer('id_periodo_academico');
            // Foto del curso al calificar: la promoción cambia usuarios.id_curso.
            $table->integer('id_curso')->index();
            $table->integer('id_escala')->nullable();
            $table->foreign('id_escala')->references('id')->on('academico_escala_valoracion');
            $table->text('observacion')->nullable();
            $table->integer('id_docente');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');
            $table->timestamps();

            $table->unique(['id_estudiante', 'id_dimension', 'id_periodo_academico'], 'uq_valoracion_estudiante_dim_periodo');
            $table->index(['id_curso', 'id_periodo_academico'], 'ix_valoracion_curso_periodo');
        });

        Schema::create('academico_valoracion_logro', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_valoracion');
            $table->foreign('id_valoracion')->references('id')->on('academico_valoracion')->cascadeOnDelete();
            $table->integer('id_logro');
            $table->foreign('id_logro')->references('id')->on('academico_logro')->cascadeOnDelete();
            $table->integer('id_escala');
            $table->foreign('id_escala')->references('id')->on('academico_escala_valoracion');

            $table->unique(['id_valoracion', 'id_logro'], 'uq_valoracion_logro');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_valoracion_logro');
        Schema::dropIfExists('academico_valoracion');
        Schema::dropIfExists('academico_logro_plan_historial');
        Schema::dropIfExists('academico_logro_plan');
        Schema::dropIfExists('academico_logro');
        Schema::dropIfExists('academico_grado_dimension');
        Schema::dropIfExists('academico_dimension');
        Schema::dropIfExists('academico_escala_valoracion_nivel');
        Schema::dropIfExists('academico_escala_valoracion');
        DB::statement('ALTER TABLE periodo_academico DROP COLUMN fecha_limite_notas');
        Schema::table('nivel_academico', function (Blueprint $table) {
            $table->dropColumn(['metodo_calificacion', 'tipo_unidad', 'calificador']);
        });
    }
};
