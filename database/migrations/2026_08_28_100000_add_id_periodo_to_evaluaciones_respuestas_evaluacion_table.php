<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `id_periodo` es la base de "una evaluación de desempeño por periodo, 3 veces al
     * año": referencia `periodos` (periodo institucional general), no `periodo_academico`
     * (eso es solo para lo académico). El índice único con `id_evaluacion`+`id_evaluado`
     * es el mecanismo real que impide evaluar dos veces al mismo trabajador en el mismo
     * periodo — MySQL permite múltiples NULL en una unique, así que no rompe filas viejas
     * sin periodo.
     */
    public function up(): void
    {
        Schema::table('evaluaciones_respuestas_evaluacion', function (Blueprint $table) {
            $table->integer('id_periodo')->nullable()->after('id_nivel');

            $table->foreign('id_periodo', 'fk_resp_eval_periodo')
                ->references('id')->on('periodos')->restrictOnDelete();

            $table->unique(['id_evaluacion', 'id_evaluado', 'id_periodo'], 'uq_resp_eval_evaluado_periodo');
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones_respuestas_evaluacion', function (Blueprint $table) {
            $table->dropUnique('uq_resp_eval_evaluado_periodo');
            $table->dropForeign('fk_resp_eval_periodo');
            $table->dropColumn('id_periodo');
        });
    }
};
