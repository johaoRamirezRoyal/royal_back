<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada marcación del dispositivo posterior a la entrada del día (ver
     * AsistenciaGestionService::registrarMarcacion). asistencia_gestion solo guarda entrada y
     * salida (la última marcación válida); aquí queda el rastro de todas las intermedias.
     */
    public function up(): void
    {
        Schema::create('asistencia_gestion_marcaciones', function (Blueprint $table) {
            $table->integer("id")->autoIncrement()->primary();

            $table->integer("id_asistencia");
            $table->foreign("id_asistencia")->references("id")->on("asistencia_gestion")->cascadeOnDelete();

            $table->time("hora");
            $table->timestamp("fechareg")->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_gestion_marcaciones');
    }
};
