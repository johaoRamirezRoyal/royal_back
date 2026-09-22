<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas_respuestas_pregunta', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_respuesta');
            $table->integer('id_pregunta');
            $table->integer('id_opcion')->nullable();
            $table->text('valor_texto')->nullable();

            $table->foreign('id_respuesta', 'fk_enc_resp_pregunta_respuesta')
                ->references('id')->on('encuestas_respuestas')->cascadeOnDelete();
            // cascadeOnDelete (no la RESTRICT por defecto): borrar una pregunta/opción — o
            // la encuesta completa, que cascada hasta acá por dos caminos a la vez (via
            // encuestas_preguntas->encuestas_opciones_pregunta y via encuestas_respuestas)
            // — debe poder limpiar también las respuestas que la referenciaban, sin quedar
            // bloqueado por esta FK (visto de verdad: eliminar una encuesta con respuestas
            // fallaba con error 1451 antes de este cascade).
            $table->foreign('id_pregunta', 'fk_enc_resp_pregunta_pregunta')
                ->references('id')->on('encuestas_preguntas')->cascadeOnDelete();
            $table->foreign('id_opcion', 'fk_enc_resp_pregunta_opcion')
                ->references('id')->on('encuestas_opciones_pregunta')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas_respuestas_pregunta');
    }
};
