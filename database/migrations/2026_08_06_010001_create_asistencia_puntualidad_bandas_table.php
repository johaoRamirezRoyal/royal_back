<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bandas de puntualidad de un horario estándar (ej. "A tiempo" 00:00-07:15,
     * "Atrasado" 07:15 en adelante). `hora_hasta` nulo = sin límite superior, para la
     * última banda del horario.
     */
    public function up(): void
    {
        Schema::create('asistencia_puntualidad_bandas', function (Blueprint $table) {
            $table->integer("id")->autoIncrement()->primary()->index();

            $table->integer("id_horario");
            $table->foreign("id_horario")->references("id")->on("asistencia_horarios_estandar")->cascadeOnDelete();

            $table->string("nombre", 100);
            $table->time("hora_desde")->nullable();
            $table->time("hora_hasta")->nullable();
            $table->integer("orden")->default(0);

            $table->timestamp("fechareg")->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_puntualidad_bandas');
    }
};
