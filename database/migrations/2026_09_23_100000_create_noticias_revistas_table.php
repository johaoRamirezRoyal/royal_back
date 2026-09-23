<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revistas institucionales en PDF (módulo Noticias). La más reciente activa se incrusta
 * como flipbook en el Home — ver NoticiasService::obtenerParaMostrar. El PDF vive en
 * Cloudinary: `url` para mostrarlo, `public_id` para poder borrarlo (mismo par que
 * banner_informativo.imagen / imagen_public_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('noticias_revistas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->string('url', 500);
            $table->string('public_id', 255);
            $table->boolean('activo')->default(true);
            $table->integer('id_log')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('noticias_revistas');
    }
};
