<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Llaves de acceso de soporte (Super Admin) — permiten iniciar sesión como cualquier
 * usuario desde un equipo de terceros sin conocer su contraseña, de un solo uso y corta
 * duración (10 min, ver LlaveMaestraService::generar). Vive en `admin_management`
 * (transversal a tenants, igual que `admin_conexion_activa`) porque el usuario destino
 * puede estar en cualquier connection con tabla `usuarios` — por eso `id_user_objetivo`
 * es un entero plano sin FK, y `connection_objetivo` guarda en cuál connection buscarlo
 * al canjear la llave (ver AuthController::redeemMasterKey).
 */
return new class extends Migration
{
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::create('llaves_maestras', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();

            $table->integer('id_user_objetivo');
            $table->string('connection_objetivo', 60);
            $table->integer('generado_por');

            $table->string('token_hash', 64);
            $table->timestamp('usado_en')->nullable();
            $table->string('ip_uso', 45)->nullable();
            $table->timestamp('expira_en')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llaves_maestras');
    }
};
