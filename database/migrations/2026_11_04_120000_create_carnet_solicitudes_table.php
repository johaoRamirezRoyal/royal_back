<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * carnet_solicitudes: pedidos de carnet. Los hace el propio usuario (Mi carnet) o el director de
 * grupo para un estudiante de su curso (Mi curso), con la opción 147; los atiende quien tiene la
 * 144. Al emitir el carnet, la pendiente de esa persona queda ATENDIDA sola (CarnetService::emitir).
 * Una sola PENDIENTE por persona (lo valida el service). Sin FK a usuarios (legacy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnet_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->integer('id_user')->index(); // para quién es el carnet
            $table->enum('tipo', ['PRIMERA_VEZ', 'PERDIDA', 'DANO', 'CAMBIO_DATOS']);
            $table->string('observacion', 255)->nullable();
            $table->enum('estado', ['PENDIENTE', 'ATENDIDA', 'RECHAZADA'])->default('PENDIENTE')->index();
            $table->integer('id_solicita');
            $table->dateTime('fecha');
            $table->integer('id_atiende')->nullable();
            $table->dateTime('fecha_atencion')->nullable();
            $table->string('respuesta', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carnet_solicitudes');
    }
};
