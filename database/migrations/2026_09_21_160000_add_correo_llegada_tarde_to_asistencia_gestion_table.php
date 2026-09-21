<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado del correo de llegada tarde de cada registro (ver
 * AsistenciaGestionService::notificarLlegadaTarde): 'enviado', 'fallido' (algún envío falló)
 * u 'omitido' (había que avisar pero no hubo a quién — sin correo del trabajador ni perfiles
 * destinatarios, o demasiados destinatarios). NULL = no aplica (no fue tarde, o el aviso
 * estaba apagado / es un registro anterior a esta función).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->string('correo_llegada_tarde', 20)->nullable()->after('revocado');
            $table->dateTime('correo_llegada_tarde_at')->nullable()->after('correo_llegada_tarde');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->dropColumn(['correo_llegada_tarde', 'correo_llegada_tarde_at']);
        });
    }
};
