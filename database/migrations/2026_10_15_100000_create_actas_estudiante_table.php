<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de actas de estudiantes (diálogo formativo / convivencial) — ver
 * ActasEstudianteController. id_curso es la foto del curso del estudiante al registrar el
 * acta, para que las estadísticas históricas no cambien cuando el estudiante pase de curso.
 * id_periodo_academico sin FK: `periodo_academico` es MyISAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actas_estudiante', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();

            $table->integer('id_estudiante');
            $table->foreign('id_estudiante')->references('id_user')->on('usuarios')->restrictOnDelete();

            // curso es MyISAM en algunas BD (no admite FK): ahí basta idx_actas_anio_curso.
            $table->integer('id_curso');
            if (DB::selectOne("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curso'")?->ENGINE === 'InnoDB') {
                $table->foreign('id_curso')->references('id')->on('curso')->restrictOnDelete();
            }

            $table->integer('id_periodo_academico');

            $table->integer('id_anio_escolar');
            $table->foreign('id_anio_escolar')->references('id')->on('anio_escolar')->restrictOnDelete();

            $table->enum('tipo', ['dialogo_formativo', 'convivencial']);
            $table->date('fecha');
            $table->text('observacion')->nullable();

            $table->integer('id_usuario_registra')->nullable();
            $table->foreign('id_usuario_registra')->references('id_user')->on('usuarios')->nullOnDelete();

            $table->timestamps();

            $table->index(['id_estudiante', 'id_periodo_academico', 'tipo'], 'idx_actas_est_periodo_tipo');
            $table->index(['id_anio_escolar', 'id_curso'], 'idx_actas_anio_curso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actas_estudiante');
    }
};
