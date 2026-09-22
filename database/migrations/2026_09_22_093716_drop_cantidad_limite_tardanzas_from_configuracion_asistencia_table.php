<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revierte add_cantidad_limite_tardanzas_to_configuracion_asistencia_table: se quita el
 * acumulado/límite mensual de llegadas tarde de trabajadores (columna "Tardanzas
 * acumuladas" del reporte, niveles normal/aproximando/advertencia/limite) — el correo de
 * aviso de llegada tarde se mantiene (notificar_llegada_tarde y compañía siguen en la
 * tabla), solo deja de mencionar el acumulado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_asistencia', function (Blueprint $table) {
            $table->dropColumn('cantidad_limite_tardanzas');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_asistencia', function (Blueprint $table) {
            $table->unsignedInteger('cantidad_limite_tardanzas')->default(5)->after('perfiles_notificar_llegada_tarde');
        });
    }
};
