<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hora a la que AsistenciaGestionService::cerrarAsistenciasVencidas() marca la salida
     * automática. Nullable: si no se define, el horario no tiene cierre automático (no se
     * marca la salida sola a hora_salida_esperada).
     */
    public function up(): void
    {
        Schema::table('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->time('hora_cierre_automatico')->nullable()->after('hora_salida_esperada');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->dropColumn('hora_cierre_automatico');
        });
    }
};
