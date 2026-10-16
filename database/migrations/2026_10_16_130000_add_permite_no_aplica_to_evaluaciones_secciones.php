<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «No aplica» por sección: con el flag, todas las preguntas de la sección (también las que
 * se agreguen después) permiten «No aplica» al responder, sin marcarlas una por una. Se
 * suma (OR) al `evaluaciones_preguntas.permite_no_aplica` de cada pregunta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones_secciones', function (Blueprint $table) {
            $table->tinyInteger('permite_no_aplica')->default(0)->after('porcentaje');
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones_secciones', function (Blueprint $table) {
            $table->dropColumn('permite_no_aplica');
        });
    }
};
