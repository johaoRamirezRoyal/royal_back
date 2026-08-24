<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carga académica: qué docente(+asignatura) dicta en qué curso. Tabla base del
     * módulo de Gestión Académica sin migración propia en este repo — ver
     * 2026_08_21_125000_create_dias_semana_table. Único (id_docente_asignatura, id_curso)
     * porque CargaAcademicaService::añadirCargaAcademicaDocente hace firstOrCreate sobre
     * ese par. `id_curso` no lleva constraint física: `curso` es una tabla legacy MyISAM
     * (no soporta foreign keys) — la relación sigue siendo válida a nivel de Eloquent
     * (Cursos::class), solo no está forzada por el motor de BD.
     */
    public function up(): void
    {
        Schema::create('academico_carga_academica', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_docente_asignatura');
            $table->foreign('id_docente_asignatura')->references('id')->on('academico_docente_asignatura');

            $table->integer('id_curso')->index();

            $table->boolean('activo')->default(true);

            $table->timestamps();

            $table->unique(['id_docente_asignatura', 'id_curso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_carga_academica');
    }
};
