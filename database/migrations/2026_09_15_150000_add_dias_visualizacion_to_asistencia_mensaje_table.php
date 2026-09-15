<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuántos días, contados desde `fecha`, se mantiene visible una noticia programada
     * en el contenedor del Home — reemplaza las ventanas fijas que antes vivían como
     * constantes en NoticiasService (30 días para 'normal', 3 para 'cumpleanos'): ahora
     * es configurable por noticia, y NULL (sin definir) cae al valor por defecto
     * (NoticiasService::DIAS_VISUALIZACION_DEFECTO, 10 días) resuelto en la consulta, no
     * guardado como literal — así que si el default cambia más adelante, aplica también
     * retroactivamente a las filas que ya lo dejaron en blanco.
     */
    public function up(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->unsignedInteger('dias_visualizacion')->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->dropColumn('dias_visualizacion');
        });
    }
};
