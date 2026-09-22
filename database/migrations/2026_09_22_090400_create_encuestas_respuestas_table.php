<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Respuesta anónima: sin id_user/IP/nada identificable, por diseño.
        Schema::create('encuestas_respuestas', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_encuesta');
            $table->integer('id_salon');
            $table->timestamps();

            $table->foreign('id_encuesta', 'fk_enc_respuestas_encuesta')
                ->references('id')->on('encuestas')->cascadeOnDelete();
            $table->foreign('id_salon', 'fk_enc_respuestas_salon')
                ->references('id')->on('salones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas_respuestas');
    }
};
