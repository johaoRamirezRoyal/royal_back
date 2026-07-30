<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evita la condición de carrera "check-then-insert" en
     * LlegadasTarde::agregarLlegadaTarde(): sin esta constraint, dos pushes
     * casi simultáneos del mismo alumno (p. ej. un reintento del dispositivo)
     * pueden pasar el exists() antes de que el primero inserte, duplicando la
     * llegada tarde. No hay duplicados existentes en esta tabla (verificado),
     * así que no hace falta limpieza previa.
     */
    public function up(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->unique(['id_alumno', 'fecha'], 'llegadas_tardes_alumno_fecha_unique');
        });
    }

    public function down(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->dropUnique('llegadas_tardes_alumno_fecha_unique');
        });
    }
};
