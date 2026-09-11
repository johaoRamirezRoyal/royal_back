<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pivot de responsables de un bloque (N a N) — asistentes de nivel/coordinadores. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloque_usuario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_bloque');
            $table->integer('id_user');
            $table->timestamp('fechareg')->useCurrent();

            $table->foreign('id_bloque')->references('id')->on('bloques')->cascadeOnDelete();
            $table->unique(['id_bloque', 'id_user']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloque_usuario');
    }
};
