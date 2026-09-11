<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correo(s) del encargado de este salón en particular (separados por coma) — se suman a
 * los correos globales de `configuracion_reservas` al notificar una reserva nueva, ver
 * ReservasServices::notificarReservaCreada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salones', function (Blueprint $table) {
            $table->string('correo_notificacion', 500)->nullable()->after('sonido');
        });
    }

    public function down(): void
    {
        Schema::table('salones', function (Blueprint $table) {
            $table->dropColumn('correo_notificacion');
        });
    }
};
