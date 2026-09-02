<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluaciones_respuestas_pregunta', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_respuesta_evaluacion');
            $table->integer('id_pregunta');
            $table->integer('id_opcion')->nullable();
            $table->text('valor_texto')->nullable();
            $table->text('comentario')->nullable();
            $table->timestamps();

            $table->foreign('id_respuesta_evaluacion', 'fk_resp_pregunta_resp_eval')
                ->references('id')->on('evaluaciones_respuestas_evaluacion')->cascadeOnDelete();
            $table->foreign('id_pregunta', 'fk_resp_pregunta_pregunta')
                ->references('id')->on('evaluaciones_preguntas');
            $table->foreign('id_opcion', 'fk_resp_pregunta_opcion')
                ->references('id')->on('evaluaciones_opciones_pregunta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_respuestas_pregunta');
    }
};
