<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas_opciones_pregunta', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_pregunta');
            $table->string('texto');
            $table->integer('orden')->default(0);

            $table->foreign('id_pregunta', 'fk_enc_opciones_pregunta')
                ->references('id')->on('encuestas_preguntas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas_opciones_pregunta');
    }
};
