<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Límite de llegadas tarde de un trabajador dentro del período del acumulado — mismo
 * criterio de niveles que ya usa llegadas tarde de estudiantes (configuracion_llegadas_tarde
 * .cantidad_limite, default 5): 1..limite-1 normal (limite-1 = "aproximándose"), limite =
 * "en el límite", más de limite = "límite superado". 0 = sin límite (todo "normal").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_asistencia', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_limite_tardanzas')->default(5)->after('perfiles_notificar_llegada_tarde');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_asistencia', function (Blueprint $table) {
            $table->dropColumn('cantidad_limite_tardanzas');
        });
    }
};
