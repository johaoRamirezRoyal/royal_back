<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloque = edificio/sección física asociada a un único nivel (nivel.id) —
 * contiene áreas/salones y, opcionalmente, inventario asignado directo (sin
 * área). Ver AGENTS.md "Áreas Comunes".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bloques', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->integer('id_nivel')->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('user_log')->nullable();
            $table->timestamp('fechareg')->useCurrent();

            $table->foreign('id_nivel')->references('id')->on('nivel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloques');
    }
};
