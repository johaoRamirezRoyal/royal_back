<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `id_anio_escolar` guarda el año escolar VIGENTE (tabla `anio_escolar`, `activo=1`) en
     * el momento en que se registró la respuesta — resuelto aparte, no derivado de
     * `periodo.id_anio`. `periodos` es un catálogo legacy cuyo `id_anio` puede no reflejar
     * el año escolar realmente activo (ver AGENTS.md, sección Evaluaciones), así que no es
     * confiable para mostrar/guardar "el año de la evaluación".
     */
    public function up(): void
    {
        Schema::table('evaluaciones_respuestas_evaluacion', function (Blueprint $table) {
            $table->integer('id_anio_escolar')->nullable()->after('id_periodo');

            $table->foreign('id_anio_escolar', 'fk_resp_eval_anio_escolar')
                ->references('id')->on('anio_escolar')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones_respuestas_evaluacion', function (Blueprint $table) {
            $table->dropForeign('fk_resp_eval_anio_escolar');
            $table->dropColumn('id_anio_escolar');
        });
    }
};
