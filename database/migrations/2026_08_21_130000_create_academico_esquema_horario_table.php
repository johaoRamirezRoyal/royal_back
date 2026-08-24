<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plantilla de franjas horarias por (nivel × año escolar) — ver
     * 2026_08_21_130100_add_id_esquema_to_academico_franja_horaria_table. `academico_franja_horaria`
     * no tiene migración propia en este repo (tabla creada directamente en la BD compartida),
     * así que esta es la primera migración que la toca.
     */
    public function up(): void
    {
        Schema::create('academico_esquema_horario', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();

            $table->string('nombre', 255);

            $table->integer('id_nivel');
            $table->foreign('id_nivel')->references('id')->on('nivel')->cascadeOnDelete();

            $table->integer('id_anio_escolar');
            $table->foreign('id_anio_escolar')->references('id')->on('anio_escolar')->cascadeOnDelete();

            $table->boolean('activo')->default(true);

            $table->unique(['id_nivel', 'id_anio_escolar']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academico_esquema_horario');
    }
};
