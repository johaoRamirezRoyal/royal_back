<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluaciones_respuestas_evaluacion', function (Blueprint $table) {
            $table->integer('id_evaluado')->nullable()->after('id_user');

            $table->foreign('id_evaluado', 'fk_resp_eval_evaluado')
                ->references('id_user')->on('usuarios');
            $table->index('id_evaluado', 'idx_resp_eval_evaluado');
        });
    }

    public function down(): void
    {
        Schema::table('evaluaciones_respuestas_evaluacion', function (Blueprint $table) {
            $table->dropForeign('fk_resp_eval_evaluado');
            $table->dropIndex('idx_resp_eval_evaluado');
            $table->dropColumn('id_evaluado');
        });
    }
};
