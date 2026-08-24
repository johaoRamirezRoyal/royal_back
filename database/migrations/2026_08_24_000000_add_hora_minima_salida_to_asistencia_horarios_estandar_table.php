<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Piso para marcar salida: una marcación con entrada ya registrada antes de esta hora
     * se descarta en vez de interpretarse como salida (evita que una segunda marcación
     * temprana por error del dispositivo cierre el día del trabajador). No confundir con
     * hora_llegada_esperada/hora_salida_esperada, que son para puntualidad y cierre
     * automático — este es un concepto propio. Se siembra en 09:00 para no cambiar el
     * comportamiento actual hasta que alguien lo edite desde Configuración de asistencia.
     */
    public function up(): void
    {
        Schema::table('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->time('hora_minima_salida')->default('09:00:00')->after('hora_llegada_esperada');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->dropColumn('hora_minima_salida');
        });
    }
};
