<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Motivo cuando una fila se modifica automáticamente (ej. cierre automático de salida
     * por AsistenciaGestionService::cerrarAsistenciasVencidas) en vez de por marcación real.
     */
    public function up(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->text('observacion')->nullable()->after('hora_salida');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
    }
};
