<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * hora_asistencia sigue siendo la hora de entrada (nombre histórico: no se
     * renombra para no romper los reportes que ya lo usan). hora_salida es nueva
     * y nullable porque se completa después, cuando el trabajador marca salida.
     */
    public function up(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->time('hora_salida')->nullable()->after('hora_asistencia');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_gestion', function (Blueprint $table) {
            $table->dropColumn('hora_salida');
        });
    }
};
