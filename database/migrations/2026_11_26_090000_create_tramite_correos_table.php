<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tramite_correos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_tramite')->index();
            $table->json('destinatarios');
            $table->string('asunto');
            $table->json('adjuntos')->nullable();
            $table->string('estado', 10)->default('pendiente'); // pendiente | enviado | error
            $table->text('error')->nullable();
            $table->timestamp('intentado_at')->nullable();
            $table->timestamp('enviado_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tramite_correos');
    }
};
