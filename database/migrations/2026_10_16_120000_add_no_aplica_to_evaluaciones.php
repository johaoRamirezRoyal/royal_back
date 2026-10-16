<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «No aplica» en evaluaciones: una evaluación de desempeño compartida (p. ej. psicólogas)
 * tiene secciones que no le corresponden a todos los evaluados.
 * - `evaluaciones_preguntas.permite_no_aplica`: la pregunta muestra «No aplica» al responder.
 * - `evaluaciones_respuestas_pregunta.no_aplica`: se marcó así; la pregunta sale del máximo
 *   de su sección (ver EvaluacionesServices::calcularPuntajeRespuesta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones_preguntas', function (Blueprint $table) {
            $table->tinyInteger('permite_no_aplica')->default(0)->after('permite_comentario');
        });

        Schema::table('evaluaciones_respuestas_pregunta', function (Blueprint $table) {
            $table->tinyInteger('no_aplica')->default(0)->after('valor_texto');
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones_preguntas', function (Blueprint $table) {
            $table->dropColumn('permite_no_aplica');
        });

        Schema::table('evaluaciones_respuestas_pregunta', function (Blueprint $table) {
            $table->dropColumn('no_aplica');
        });
    }
};
