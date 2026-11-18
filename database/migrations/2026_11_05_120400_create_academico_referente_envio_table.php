<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envío a revisión por asignatura (ver docs/contexto/modulos/notas.md): cada docente envía los
 * referentes de su asignatura; cuando todas las asignaturas con carga en el grado los enviaron, el
 * plan pasa a EN_REVISION. Devolver o reabrir el plan borra los envíos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academico_referente_envio', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_plan');
            $table->foreign('id_plan')->references('id')->on('academico_referente_plan')->cascadeOnDelete();
            $table->integer('id_asignatura');
            $table->integer('id_usuario');
            $table->foreign('id_usuario')->references('id_user')->on('usuarios');
            $table->timestamp('fecha')->useCurrent();

            $table->unique(['id_plan', 'id_asignatura'], 'uq_referente_envio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_referente_envio');
    }
};
