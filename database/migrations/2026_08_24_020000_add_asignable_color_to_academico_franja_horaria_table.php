<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Franjas que no se pueden asignar a ninguna clase/docente (ej. receso, almuerzo),
     * identificadas por un color en la grilla en vez de crear un HorarioClase tipo
     * RECESO/ALMUERZO por cada curso/docente. `etiqueta` es el nombre visible junto al
     * color (ej. "Receso") — sin esto, dos franjas no_asignable solo se distinguirían por
     * memorizar el color. Default asignable=true para no afectar las franjas existentes.
     */
    public function up(): void
    {
        Schema::table('academico_franja_horaria', function (Blueprint $table) {
            $table->boolean('asignable')->default(true)->after('orden');
            $table->string('color', 20)->nullable()->after('asignable');
            $table->string('etiqueta', 100)->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('academico_franja_horaria', function (Blueprint $table) {
            $table->dropColumn(['asignable', 'color', 'etiqueta']);
        });
    }
};
