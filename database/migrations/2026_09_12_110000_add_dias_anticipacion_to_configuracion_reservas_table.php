<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reemplaza la ventana fija "solo el día siguiente" de ReservasServices::validarFechaReserva
 * por una ventana de anticipación configurable (en días calendario, fines de semana
 * incluidos) — default 1/1 reproduce exactamente el comportamiento anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_reservas', function (Blueprint $table) {
            $table->unsignedInteger('dias_min_anticipacion')->default(1)->after('correo_notificacion');
            $table->unsignedInteger('dias_max_anticipacion')->default(1)->after('dias_min_anticipacion');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_reservas', function (Blueprint $table) {
            $table->dropColumn(['dias_min_anticipacion', 'dias_max_anticipacion']);
        });
    }
};
