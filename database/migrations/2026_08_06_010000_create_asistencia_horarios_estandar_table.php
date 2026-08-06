<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horario estándar (llegada/salida esperada) para calcular puntualidad. `grupo_id`
     * usa los mismos ids de hikvisionattendanceService::GROUP_ID_POR_PERFIL (2 Admin.
     * Dept./9 Profesores/10 Trabajadores); `null` = horario global (fallback cuando no
     * hay uno específico para el grupo del usuario).
     */
    public function up(): void
    {
        Schema::create('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->integer("id")->autoIncrement()->primary()->index();

            $table->string("nombre", 100);
            $table->integer("grupo_id")->nullable();

            $table->time("hora_llegada_esperada");
            $table->time("hora_salida_esperada");

            $table->boolean("activo")->default(true);

            $table->timestamp("fechareg")->useCurrent();
            $table->timestamp("fecha_updated")->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_horarios_estandar');
    }
};
