<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encuestas_salon', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_salon');
            // Encuesta activa de este salón; null = sin encuesta asignada. Una fila por
            // salón (no historial) — cambiar la activa es un UPDATE de esta columna.
            $table->integer('id_encuesta')->nullable();
            // Identificador del link/QR público — no el id del salón, para no permitir
            // enumerar salones desde una URL adivinada.
            $table->string('token_publico', 64)->unique();
            $table->timestamps();

            $table->foreign('id_salon', 'fk_enc_salon_salon')
                ->references('id')->on('salones')->cascadeOnDelete();
            $table->foreign('id_encuesta', 'fk_enc_salon_encuesta')
                ->references('id')->on('encuestas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encuestas_salon');
    }
};
