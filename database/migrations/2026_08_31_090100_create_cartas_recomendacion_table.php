<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cartas_recomendacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_institucion')->constrained('instituciones')->cascadeOnDelete();
            $table->text('contenido');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cartas_recomendacion');
    }
};
