<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asignaturas que un docente puede dictar. Tabla base del módulo de Gestión
     * Académica sin migración propia en este repo — ver
     * 2026_08_21_125000_create_dias_semana_table.
     */
    public function up(): void
    {
        Schema::create('academico_docente_asignatura', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->integer('id_docente');
            $table->foreign('id_docente')->references('id_user')->on('usuarios');

            $table->integer('id_asignatura');
            $table->foreign('id_asignatura')->references('id')->on('academico_asignatura');

            $table->boolean('activo')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_docente_asignatura');
    }
};
