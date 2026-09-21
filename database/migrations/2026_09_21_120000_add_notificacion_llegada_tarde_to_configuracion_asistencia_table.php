<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso por correo cuando un trabajador registra su entrada tarde (ver
 * AsistenciaGestionService::notificarLlegadaTarde). Apagado por defecto: al desplegar no
 * empieza a enviar correos hasta que RH lo active y elija los perfiles desde
 * Configuración de asistencia. `perfiles_notificar_llegada_tarde` = ids de `perfiles` que
 * reciben el aviso (JSON); el propio trabajador se controla aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_asistencia', function (Blueprint $table) {
            $table->boolean('notificar_llegada_tarde')->default(false)->after('hora_minima_salida_defecto');
            $table->boolean('notificar_llegada_tarde_trabajador')->default(true)->after('notificar_llegada_tarde');
            $table->json('perfiles_notificar_llegada_tarde')->nullable()->after('notificar_llegada_tarde_trabajador');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_asistencia', function (Blueprint $table) {
            $table->dropColumn(['notificar_llegada_tarde', 'notificar_llegada_tarde_trabajador', 'perfiles_notificar_llegada_tarde']);
        });
    }
};
