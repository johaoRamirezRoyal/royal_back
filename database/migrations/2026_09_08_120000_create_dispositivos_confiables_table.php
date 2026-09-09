<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispositivos ya verificados por código para el login del sistema general
 * (AuthController::login) — el token crudo vive en la cookie `device_token` del
 * navegador, acá solo se guarda su hash (sha256). Un hit contra (id_user, token_hash)
 * salta la verificación por correo; sin hit (cookie ausente, distinta, o expirada del
 * lado del navegador) se exige el código. Ver AuthServices::dispositivoEsConfiable/
 * registrarDispositivoConfiable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos_confiables', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();

            $table->integer('id_user');
            $table->foreign('id_user')->references('id_user')->on('usuarios')->cascadeOnDelete();

            $table->string('token_hash', 64);
            $table->string('nombre_dispositivo', 100)->nullable();
            $table->string('ip_registro', 45)->nullable();
            $table->timestamp('ultima_vez')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id_user', 'token_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_confiables');
    }
};
