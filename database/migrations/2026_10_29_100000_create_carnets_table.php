<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carnets institucionales: una fila por carnet impreso (cada reimpresión es una versión
 * nueva del mismo usuario). La foto no vive aquí: es `usuarios.foto_carnet`.
 * Sin FK a `usuarios` (tabla legacy sin migración, ver AGENTS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('id_user')->index();
            $table->unsignedSmallInteger('version');
            $table->enum('estado', ['IMPRESO', 'ENTREGADO', 'PERDIDO', 'ANULADO'])->default('IMPRESO');
            $table->dateTime('fecha_emision');
            $table->dateTime('fecha_entrega')->nullable();
            $table->dateTime('fecha_estado')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->unsignedInteger('id_usuario_emite');
            $table->unsignedInteger('id_usuario_estado')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnets');
    }
};
