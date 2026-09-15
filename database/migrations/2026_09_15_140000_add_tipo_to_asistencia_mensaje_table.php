<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipo de noticia programada — 'normal' (default, comportamiento de siempre: ventana
     * de 30 días en el contenedor del Home, ver NoticiasService::VENTANA_DIAS) o
     * 'cumpleanos', que se muestra aparte (al lado, no dentro de la lista general) y con
     * una ventana bastante más corta (ver NoticiasService::VENTANA_DIAS_CUMPLEANOS) — un
     * cumpleaños dejó de ser relevante mucho antes que una noticia normal.
     */
    public function up(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->enum('tipo', ['normal', 'cumpleanos'])->default('normal')->after('nivel');
        });
    }

    public function down(): void
    {
        Schema::table('asistencia_mensaje', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
