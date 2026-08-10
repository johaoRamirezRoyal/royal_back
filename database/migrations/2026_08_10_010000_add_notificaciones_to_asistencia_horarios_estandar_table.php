<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controlan si AsistenciaGestionService::cerrarAsistenciasVencidas() envía el correo de
     * salida automática a cada destinatario. Default true: mismo comportamiento (envía a
     * ambos) hasta que alguien lo desactive explícitamente para un horario.
     */
    public function up(): void
    {
        Schema::table('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->boolean('notificar_trabajador')->default(true)->after('hora_cierre_automatico');
            $table->boolean('notificar_rh')->default(true)->after('notificar_trabajador');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->dropColumn(['notificar_trabajador', 'notificar_rh']);
        });
    }
};
